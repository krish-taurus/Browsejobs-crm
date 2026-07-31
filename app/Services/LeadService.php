<?php

namespace App\Services;

use App\Jobs\ProcessNewLeadJob;
use App\Mail\GenericMail;
use App\Mail\LeadCreatedMail;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadStatus;
use App\Models\LeadStatusHistory;
use App\Models\Lms\LmsLead;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\LeadCreatedNotification;
use App\Notifications\LeadEventNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LeadService
{
    /**
     * Roles notified whenever a new lead is generated.
     */
    private const NOTIFY_ROLE_CODES = ['SUPER_ADMIN', 'ADMIN', 'HEAD_OF_OPERATIONS', 'HR_MANAGER'];

    /**
     * Roles notified when an AI-called lead is "good" and needs a human caller assigned.
     */
    private const HR_ROUTE_ROLE_CODES = ['HR_MANAGER', 'HEAD_OF_OPERATIONS'];

    public function __construct(
        private WhatsAppService $whatsApp,
        private CallerDigitalService $caller,
    ) {}

    /**
     * Create a lead, set its initial status, and notify the team. Only the
     * instant channels (bell + web-push) run inline so the add is fast; the
     * slow work (emails, WhatsApp, auto AI call) is queued to the background.
     *
     * @param  array{mobile: string, name?: ?string, email?: ?string, campaign_name?: ?string}  $data
     */
    public function create(array $data, string $source, ?int $addedByUserId): Lead
    {
        $lead = Lead::create([
            'mobile' => $data['mobile'],
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'source' => $source,
            'campaign_name' => $data['campaign_name'] ?? null,
            'added_by_user_id' => $addedByUserId,
            'current_status_id' => $this->initialStatusId(),
        ]);

        $this->pushLeadCreated($lead);

        ProcessNewLeadJob::dispatch($lead->id, $addedByUserId);

        return $lead;
    }

    /**
     * Instant channels for a new lead: in-app bell + web-push banner.
     */
    public function pushLeadCreated(Lead $lead): void
    {
        foreach ($this->leadCreatedRecipients() as $user) {
            $user->notify(new LeadCreatedNotification($lead));
        }
    }

    /**
     * Slow channels for a new lead: email + WhatsApp, recorded per recipient.
     * Runs from the queued ProcessNewLeadJob, not during the web request.
     */
    public function emailAndWhatsAppLeadCreated(Lead $lead): void
    {
        foreach ($this->leadCreatedRecipients() as $user) {
            if (filled($user->email)) {
                try {
                    Mail::to($user->email)->send(new LeadCreatedMail($lead));
                    $this->recordNotification($lead->id, $user->id, 'email');
                } catch (\Throwable $e) {
                    // don't let a mail failure break lead capture
                }
            }

            $number = $user->whatsapp_number ?: $user->phone;
            if (filled($number)) {
                $sent = $this->whatsApp->sendText($number, $this->whatsAppMessage($lead));
                if ($sent) {
                    $this->recordNotification($lead->id, $user->id, 'whatsapp');
                }
            }
        }
    }

    /**
     * Auto-dial the AI agent for a new lead (unless disabled or the daily cap is hit).
     * Skips leads that already have an AI call — the queued job and the
     * leads:auto-call safety net must never double-dial the same person.
     */
    public function maybeAutoCall(Lead $lead, ?int $addedByUserId): void
    {
        if ($lead->calls()->where('type', 'ai')->exists() || $this->candidateAlreadyAiCalled($lead)) {
            return;
        }

        if (config('services.caller_digital.auto_call') && $this->caller->isConfigured() && ! $this->aiCallCapReached()) {
            $this->triggerAiCall($lead, $addedByUserId);
        }
    }

    /**
     * Has this candidate — same phone number, on ANY lead — already had a
     * COMPLETED AI call? Once someone has answered the AI agent, auto-calling
     * must never dial them again, even if they re-enter the CRM as a fresh
     * lead (LMS import, second ad campaign, manual re-entry). Matches on the
     * last 10 digits so +91/0-prefixed formats count as the same number.
     * Manual re-trigger from the lead page is intentionally NOT gated by this.
     */
    public function candidateAlreadyAiCalled(Lead $lead): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $lead->mobile) ?? '';
        $last10 = substr($digits, -10);

        // Strip the separators people type into phone fields before comparing,
        // so "+91 98123-45678" and "9812345678" are the same number.
        $normalizedToNumber = "REPLACE(REPLACE(REPLACE(REPLACE(to_number, ' ', ''), '-', ''), '(', ''), ')', '')";

        return LeadCall::query()
            ->where('type', 'ai')
            ->where('status', 'completed')
            ->where('lead_id', '!=', $lead->id)
            ->when(
                strlen($last10) >= 10,
                fn ($q) => $q->whereRaw("{$normalizedToNumber} LIKE ?", ["%{$last10}"]),
                fn ($q) => $q->where('to_number', $lead->mobile),
            )
            ->exists();
    }

    /**
     * @return Collection<int, User>
     */
    private function leadCreatedRecipients()
    {
        return User::query()
            ->withRoleCode(self::NOTIFY_ROLE_CODES)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Has today's AI-call cap been reached? (0 = unlimited.)
     */
    public function aiCallCapReached(): bool
    {
        $cap = (int) config('services.caller_digital.daily_cap', 0);

        if ($cap <= 0) {
            return false;
        }

        return LeadCall::where('type', 'ai')->whereDate('started_at', today())->count() >= $cap;
    }

    /**
     * Place an outbound AI voice call for a lead via Caller.Digital. Returns the LeadCall
     * record (status 'ringing' on success, 'failed' on error), or null if not configured/capped.
     */
    public function triggerAiCall(Lead $lead, ?int $initiatedByUserId = null): ?LeadCall
    {
        if (! $this->caller->isConfigured() || $this->aiCallCapReached()) {
            return null;
        }

        $call = LeadCall::create([
            'lead_id' => $lead->id,
            'type' => 'ai',
            'provider' => 'caller_digital',
            'status' => 'queued',
            'to_number' => $lead->mobile,
            'from_number' => config('services.caller_digital.from_number'),
            'agent_id' => config('services.caller_digital.agent_id'),
            'language' => config('services.caller_digital.default_language'),
            'initiated_by_user_id' => $initiatedByUserId,
            'started_at' => now(),
        ]);

        try {
            $result = $this->caller->triggerLeadCall(
                $lead->mobile,
                'Lead #'.$lead->id.' — '.$lead->displayName(),
                array_filter([
                    'lead_id' => (string) $lead->id,
                    'customer_name' => (string) $lead->name,
                ]),
            );

            $call->update([
                'external_campaign_id' => $result['campaign_id'],
                'status' => 'ringing',
            ]);

            // While the AI call is in flight, the lead shows as "AI Call Running"
            // (only from New — never clobber a status a human already set).
            if ($lead->status()->value('slug') === 'new') {
                $this->setStatusBySlug($lead, 'ai_call_running', 'AI call placed — waiting for the call to finish.');
            }
        } catch (\Throwable $e) {
            $call->update(['status' => 'failed', 'meta' => ['error' => $e->getMessage()]]);
        }

        return $call;
    }

    /**
     * Notify the HR user a lead was assigned to — email + WhatsApp + web-push (with sound).
     */
    public function notifyLeadAssigned(Lead $lead, User $assignee, ?User $assignedBy): void
    {
        $url = route('leads.show', $lead->id);
        $by = $assignedBy?->full_name ?? 'HR Manager';
        $title = 'New lead assigned to you';
        $message = $lead->displayName().' ('.$lead->mobile.') assigned by '.$by.'. Please follow up.';

        $assignee->notify(new LeadAssignedNotification($lead, $assignedBy));

        if (filled($assignee->email)) {
            try {
                Mail::to($assignee->email)->send(new GenericMail(
                    $title,
                    "<p>{$message}</p><p><a href=\"{$url}\">Open the lead &amp; follow up</a></p>"
                ));
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $number = $assignee->whatsapp_number ?: $assignee->phone;
        if (filled($number)) {
            $this->whatsApp->sendText($number, "*{$title}*\n{$message}\n{$url}");
        }
    }

    /**
     * Apply the outcome of a completed AI call: move the lead to the matching status and,
     * for good/interested leads, alert HR_MANAGER so it can be assigned to a human caller.
     */
    public function handleAiCallOutcome(LeadCall $call): void
    {
        $lead = $call->lead;
        if (! $lead) {
            return;
        }

        $disposition = strtolower((string) $call->disposition);
        $sentiment = strtolower((string) $call->sentiment);

        $notInterested = str_contains($disposition, 'not interest')
            || in_array($disposition, ['declined', 'rejected', 'not_interested'], true)
            || $sentiment === 'negative';

        $interested = ! $notInterested && (
            str_contains($disposition, 'interest')
            || in_array($disposition, ['confirmed', 'hot', 'positive'], true)
            || $sentiment === 'positive'
        );

        $needsHuman = ! $notInterested && ! $interested && (
            str_contains($disposition, 'manual') || str_contains($disposition, 'review')
            || str_contains($disposition, 'callback') || str_contains($disposition, 'call back')
        );

        if ($interested) {
            $this->setStatusBySlug($lead, 'interested', 'AI call: lead sounded interested — route to a human caller.');

            // Fully automatic course routing: if HR hasn't picked a course yet,
            // read it out of the call transcript before the LMS handoff.
            if (blank($lead->interested_course_slug) && ($slug = $this->detectCourseFromCall($call)) !== null) {
                $lead->update(['interested_course_slug' => $slug]);
                $lead->refresh();
            }

            $this->handleInterested($lead);
            $this->notifyHrToAssign($lead, 'interested');
        } elseif ($needsHuman) {
            $this->setStatusBySlug($lead, 'follow_up', 'AI call: needs a human follow-up call.');
            $this->notifyHrToAssign($lead, 'follow-up');
        } elseif ($notInterested) {
            $this->setStatusBySlug($lead, 'not_interested', 'AI call: lead not interested.');
        } elseif ($lead->status()->value('slug') === 'ai_call_running') {
            // The call finished but the disposition matched nothing — never leave
            // a lead stuck in "AI Call Running"; hand it to a human instead.
            $this->setStatusBySlug($lead, 'follow_up', 'AI call completed without a clear outcome — needs human review.');
            $this->notifyHrToAssign($lead, 'follow-up');
        }
    }

    /**
     * Read the course the candidate talked about out of the AI call. Matches
     * the transcript + disposition against the LMS course catalogue (name, or
     * slug with dashes as spaces) and answers only when EXACTLY one course
     * matches — ambiguity stays with HR. Best-effort: null if the LMS is down.
     */
    public function detectCourseFromCall(LeadCall $call): ?string
    {
        $text = mb_strtolower(trim(($call->transcript ?? '').' '.($call->disposition ?? '')));

        if ($text === '') {
            return null;
        }

        try {
            $matches = DB::connection('lms')->table('courses')
                ->get(['slug', 'name'])
                ->filter(function ($course) use ($text) {
                    return str_contains($text, mb_strtolower($course->name))
                        || str_contains($text, str_replace('-', ' ', mb_strtolower($course->slug)));
                })
                ->unique('slug');

            return $matches->count() === 1 ? $matches->first()->slug : null;
        } catch (QueryException|\PDOException $e) {
            report($e);

            return null;
        }
    }

    /**
     * Everything that happens the moment a lead turns Interested — whether the
     * AI decided it or HR set the status by hand: mirror the lead into the LMS
     * (so the Batch Funnel page sees the interest) and send them the
     * masterclass watch link right away. Batches themselves are created and
     * allocated MANUALLY from the CRM — nothing automatic happens after this.
     */
    public function handleInterested(Lead $lead): void
    {
        $this->pushInterestedToLms($lead);
        $this->sendMasterclassInvite($lead);
    }

    /**
     * WhatsApp + email the interested candidate the masterclass link — the
     * daily simulated-live showing, so a Monday lead watches today at the
     * fixed show time instead of waiting for Saturday's live session. Sent
     * exactly once per lead; the did-you-watch follow-up runs the next day
     * (leads:masterclass-followup).
     */
    public function sendMasterclassInvite(Lead $lead): void
    {
        if ($lead->masterclass_link_sent_at !== null) {
            return;
        }

        $base = rtrim((string) config('services.lms.masterclass_watch_url'), '/');
        $link = filled($lead->interested_course_slug) ? $base.'/'.$lead->interested_course_slug : $base;
        $showTime = (string) config('services.lms.masterclass_show_time_label', '8:00 PM');
        $name = $lead->name ?: 'there';

        $message = "Hi {$name}! 🎓 Great talking to you.\n\n"
            ."Your FREE live masterclass runs TODAY at {$showTime}. Join from this link:\n{$link}\n\n"
            .'Open it a few minutes early — the session starts automatically. See you there!';

        $sent = false;

        if (filled($lead->mobile)) {
            // Approved template first (delivers outside the 24h session window);
            // session text as the fallback while approval is pending.
            $template = config('services.whatsapp.templates.masterclass_invite');
            $viaTemplate = $template
                ? $this->whatsApp->sendTemplate($lead->mobile, $template, 'en', [$name, $showTime, $link]) !== null
                : false;

            $sent = $viaTemplate || $this->whatsApp->sendText($lead->mobile, $message) || $sent;
        }

        if (filled($lead->email)) {
            try {
                Mail::to($lead->email)->send(new GenericMail(
                    "Your free masterclass — today at {$showTime}",
                    "<p>Hi {$name},</p><p>Your free live masterclass runs <strong>today at {$showTime}</strong>.</p>"
                    ."<p><a href=\"{$link}\">Click here to join the masterclass</a> — open it a few minutes early, the session starts automatically.</p>"
                    .'<p>See you there!<br>Team BrowseJobs</p>'
                ));
                $sent = true;
            } catch (\Throwable) {
                // best-effort
            }
        }

        if ($sent) {
            $lead->forceFill(['masterclass_link_sent_at' => now()])->save();
        }
    }

    /**
     * Mark a lead Joined — used when HR manually allocates the lead into an
     * LMS batch (the moment a lead becomes a student).
     */
    public function markJoined(Lead $lead, string $remark): void
    {
        $this->setStatusBySlug($lead, 'joined', $remark);
    }

    /**
     * Hand an Interested lead to the LMS as a masterclass lead, so the weekly
     * funnel automation invites them and seats them in the next Saturday
     * masterclass once they register an account. This is the ONE write the CRM
     * makes to the LMS database — an insert into its lead-capture table, the
     * same thing the LMS website form does. Best-effort and deduped by phone;
     * the course is carried over when campaign_name matches an LMS course.
     */
    public function pushInterestedToLms(Lead $lead): void
    {
        try {
            $digits = preg_replace('/\D+/', '', (string) $lead->mobile) ?? '';
            $last10 = substr($digits, -10);

            $existingLmsLead = LmsLead::query()
                ->when(
                    strlen($last10) >= 10,
                    fn ($q) => $q->where('phone_normalized', 'like', "%{$last10}"),
                    fn ($q) => $q->where('phone', $lead->mobile),
                )
                ->first();

            $stage = DB::connection('lms')->table('lead_stages')
                ->where('slug', 'masterclass-registered')
                ->first();

            $tenantId = $stage->tenant_id ?? DB::connection('lms')->table('tenants')->value('id');

            if ($tenantId === null) {
                return;
            }

            // HR's explicit course choice wins; otherwise try to read the course
            // from the ad campaign name. Always validated against the catalogue.
            $courseSlug = null;
            foreach ([$lead->interested_course_slug, $lead->campaign_name] as $candidate) {
                if (blank($candidate)) {
                    continue;
                }

                $courseSlug = DB::connection('lms')->table('courses')
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($candidate) {
                        $q->where('slug', $candidate)->orWhere('name', $candidate);
                    })
                    ->value('slug');

                if ($courseSlug !== null) {
                    break;
                }
            }

            if ($existingLmsLead !== null) {
                // Already handed off (or captured on the site) — just backfill
                // the course if HR picked one and the LMS side has none yet.
                if ($courseSlug !== null && blank($existingLmsLead->course_slug)) {
                    $existingLmsLead->forceFill(['course_slug' => $courseSlug])->save();
                }

                return;
            }

            LmsLead::query()->forceCreate([
                'tenant_id' => $tenantId,
                'lead_type' => 'masterclass',
                'name' => $lead->displayName(),
                'phone' => $lead->mobile,
                'phone_normalized' => $digits,
                'email' => $lead->email,
                'course_slug' => $courseSlug,
                'utm_source' => 'crm',
                'lead_stage_id' => $stage->id ?? null,
                'consented_at' => now(),
                'consent_version' => 'v1',
            ]);
        } catch (QueryException|\PDOException $e) {
            report($e); // LMS unreachable — the funnel picks them up next time HR re-saves the status
        }
    }

    /**
     * The AI call never connected (failed / no answer / busy): drop the lead
     * back to New so it stays visible in the fresh-leads queue for a human.
     */
    public function handleAiCallFailure(LeadCall $call): void
    {
        $lead = $call->lead;

        if (! $lead || $lead->status()->value('slug') !== 'ai_call_running') {
            return;
        }

        $reason = str_replace('_', ' ', $call->status);
        $this->setStatusBySlug($lead, 'new', "AI call {$reason} — back to New for a human attempt.");
    }

    private function setStatusBySlug(Lead $lead, string $slug, string $remark): void
    {
        $status = LeadStatus::where('slug', $slug)->first();

        if (! $status || (int) $lead->current_status_id === (int) $status->id) {
            return;
        }

        $lead->update(['current_status_id' => $status->id]);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'status_id' => $status->id,
            'changed_by_user_id' => null, // system (AI-driven)
            'remarks' => $remark,
        ]);
    }

    /**
     * Email + WhatsApp HR managers that a hot lead is ready to be assigned to a caller.
     */
    private function notifyHrToAssign(Lead $lead, string $reason): void
    {
        $recipients = User::query()
            ->withRoleCode(self::HR_ROUTE_ROLE_CODES)
            ->where('is_active', true)
            ->get();

        $url = route('leads.show', $lead->id);
        $label = $reason === 'interested' ? 'Interested lead' : 'Lead needs a human call';

        foreach ($recipients as $user) {
            if (filled($user->email)) {
                try {
                    Mail::to($user->email)->send(new GenericMail(
                        "{$label}: ".$lead->displayName(),
                        "<p>The AI call marked this lead as <strong>{$reason}</strong>. Please assign it to an HR caller.</p>"
                        .'<p>Name: <strong>'.($lead->name ?: '—')."</strong><br>Mobile: <strong>{$lead->mobile}</strong></p>"
                        ."<p><a href=\"{$url}\">Open lead &amp; assign</a></p>"
                    ));
                } catch (\Throwable $e) {
                    // best-effort
                }
            }

            $number = $user->whatsapp_number ?: $user->phone;
            if (filled($number)) {
                $this->whatsApp->sendText(
                    $number,
                    "*{$label}* — assign a caller\nName: ".($lead->name ?: '—')."\nMobile: {$lead->mobile}\n{$url}"
                );
            }

            $user->notify(new LeadEventNotification(
                $label.' — assign a caller',
                ($lead->name ?: $lead->mobile).' is '.$reason.'. Assign it to an HR caller.',
                $url,
                'ti ti-flame',
            ));
        }
    }

    private function recordNotification(int $leadId, int $userId, string $channel): void
    {
        DB::table('lead_notifications')->insert([
            'lead_id' => $leadId,
            'notify_user_id' => $userId,
            'type' => 'lead_created',
            'channel' => $channel,
            'is_read' => false,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function whatsAppMessage(Lead $lead): string
    {
        return "*New lead generated*\n"
            .'Name: '.($lead->name ?: '—')."\n"
            ."Mobile: {$lead->mobile}\n"
            .($lead->email ? "Email: {$lead->email}\n" : '')
            .'Source: '.($lead->source ?: 'manual');
    }

    private function initialStatusId(): ?int
    {
        return LeadStatus::where('slug', 'new')->value('id')
            ?? LeadStatus::orderBy('sort_order')->value('id');
    }
}
