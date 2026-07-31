<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\CallerDigitalService;
use App\Services\LeadService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Next-day "did you watch the masterclass?" follow-up for interested leads.
 * Runs once per lead (stamped on the lead): a WhatsApp nudge always, plus an
 * AI follow-up call when auto-calling is enabled. After this, HR decides —
 * leads who watched and are still keen get manually allocated to a bootcamp
 * batch from the lead page.
 */
class MasterclassFollowUps extends Command
{
    protected $signature = 'leads:masterclass-followup';

    protected $description = 'Follow up interested leads the day after their masterclass link (WhatsApp + AI call)';

    public function handle(LeadService $leads, WhatsAppService $whatsApp, CallerDigitalService $caller): int
    {
        $due = Lead::query()
            ->whereHas('status', fn ($q) => $q->where('slug', 'interested'))
            ->whereNotNull('masterclass_link_sent_at')
            ->where('masterclass_link_sent_at', '<=', now()->subHours(20))
            ->whereNull('masterclass_followup_at')
            ->get();

        $followedUp = 0;

        foreach ($due as $lead) {
            $name = $lead->name ?: 'there';
            $link = rtrim((string) config('services.lms.masterclass_watch_url'), '/')
                .(filled($lead->interested_course_slug) ? '/'.$lead->interested_course_slug : '');
            $showTime = (string) config('services.lms.masterclass_show_time_label', '8:00 PM');

            // Approved template first (delivers outside the 24h session window);
            // session text as the fallback while approval is pending.
            $template = config('services.whatsapp.templates.masterclass_followup');
            $viaTemplate = $template
                ? $whatsApp->sendTemplate($lead->mobile, $template, 'en', [$name, $showTime, $link]) !== null
                : false;

            if (! $viaTemplate) {
                $whatsApp->sendText(
                    $lead->mobile,
                    "Hi {$name}! Did you get a chance to watch the masterclass? 🎓\n\n"
                    ."If yes — reply *YES* and our team will reserve your seat in the free 7-day bootcamp.\n"
                    ."If you missed it, no problem: it runs again TODAY at {$showTime} — {$link}",
                );
            }

            // The AI agent re-engages by voice too, when auto-calling is on.
            if (config('services.caller_digital.auto_call') && $caller->isConfigured() && ! $leads->aiCallCapReached()) {
                $leads->triggerAiCall($lead);
            }

            $lead->forceFill(['masterclass_followup_at' => now()])->save();
            $followedUp++;
        }

        $this->info("Followed up {$followedUp} lead(s).");

        return self::SUCCESS;
    }
}
