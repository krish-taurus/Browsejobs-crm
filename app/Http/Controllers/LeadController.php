<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadStatus;
use App\Models\LeadStatusHistory;
use App\Models\Lms\Batch as LmsBatch;
use App\Models\Lms\Course;
use App\Models\User;
use App\Services\AiLeadAnalysisService;
use App\Services\CallerDigitalService;
use App\Services\LeadService;
use App\Services\LmsCommandRunner;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    /**
     * Roles that actually call leads, so these are the only people a lead may
     * be assigned to and the only ones measured on the HR Performance board.
     * HR_MANAGER is deliberately NOT here: the manager supervises the callers,
     * they do not carry a lead list themselves.
     */
    private const HR_ASSIGNABLE_ROLES = ['HR', 'HR_TEAM_LEAD', 'HR_ADMIN'];

    /** Roles allowed to assign leads (they hand work out, they don't receive it). */
    private const CAN_ASSIGN_ROLES = ['SUPER_ADMIN', 'ADMIN', 'HEAD_OF_OPERATIONS', 'HR_MANAGER'];

    public function __construct(private LeadService $leads) {}

    public function index(Request $request): View
    {
        $query = Lead::with(['status', 'assignee', 'creator',
            // Latest AI call per lead, for the "AI Call" column.
            'calls' => fn ($q) => $q->where('type', 'ai'),
        ]);

        // Only "view all" roles see every lead; everyone else sees their own (assigned or added).
        if (! $this->canViewAll()) {
            $uid = Auth::id();
            $query->where(fn ($q) => $q->where('assigned_to_user_id', $uid)->orWhere('added_by_user_id', $uid));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->filled('status_id')) {
            $query->where('current_status_id', $request->input('status_id'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        match ($request->input('assignment')) {
            'unassigned' => $query->whereNull('assigned_to_user_id'),
            'assigned' => $query->whereNotNull('assigned_to_user_id'),
            'mine' => $query->where('assigned_to_user_id', Auth::id()),
            default => null,
        };

        // Drill-down from the HR performance board: show one HR's leads.
        if ($this->canViewAll() && $request->filled('assigned_user_id')) {
            $query->where('assigned_to_user_id', $request->input('assigned_user_id'));
        }

        $leads = $query->latest()->paginate(20)->withQueryString();
        $statuses = LeadStatus::orderBy('sort_order')->get();
        $sources = Lead::select('source')->whereNotNull('source')->distinct()->pluck('source');

        return view('leads.index', [
            'leads' => $leads,
            'statuses' => $statuses,
            'sources' => $sources,
            'isMediaStrategist' => $this->roleCode() === 'MEDIA_STRATEGIST',
            'canCreate' => $this->canCreate(),
        ]);
    }

    /**
     * Lightweight poll used by the leads index to auto-refresh: returns a
     * fingerprint of the current user's visible lead set (same role scoping
     * as index) that changes whenever a lead is added, removed, or reassigned.
     */
    public function poll(): JsonResponse
    {
        $query = Lead::query();

        if (! $this->canViewAll()) {
            $uid = Auth::id();
            $query->where(fn ($q) => $q->where('assigned_to_user_id', $uid)->orWhere('added_by_user_id', $uid));
        }

        return response()->json([
            'latest_id' => (clone $query)->max('id'),
            'total' => (clone $query)->count(),
            'last_change' => (clone $query)->max('updated_at'),
        ]);
    }

    /**
     * HR performance board: HR_MANAGER (and the monitoring roles) see, per HR,
     * how many leads were assigned, followed up, still untouched/overdue, calls
     * made, conversions, and how fast they respond after an assignment.
     */
    public function performance(Request $request): View
    {
        abort_unless(in_array($this->roleCode(), self::CAN_ASSIGN_ROLES, true), 403);

        $from = $request->filled('from') ? $request->date('from')->startOfDay() : null;
        $to = $request->filled('to') ? $request->date('to')->endOfDay() : null;

        $assignedLeads = function (int $userId) use ($from, $to) {
            return Lead::where('assigned_to_user_id', $userId)
                ->when($from, fn ($q) => $q->where('assigned_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('assigned_at', '<=', $to));
        };

        // A lead counts as "followed up" when its assignee logged a call on it
        // or moved its status after it was assigned to them.
        $followedUpFilter = function ($q, int $userId) {
            $q->where(function ($q) use ($userId) {
                $q->whereHas('calls', fn ($c) => $c->where('initiated_by_user_id', $userId)
                    ->whereColumn('lead_calls.created_at', '>=', 'leads.assigned_at'))
                    ->orWhereHas('statusHistory', fn ($h) => $h->where('changed_by_user_id', $userId)
                        ->whereColumn('lead_status_history.created_at', '>=', 'leads.assigned_at'));
            });
        };

        $rows = User::query()
            ->withRoleCode(self::HR_ASSIGNABLE_ROLES)
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get()
            ->map(function (User $hr) use ($assignedLeads, $followedUpFilter, $from, $to) {
                $assigned = $assignedLeads($hr->id)->count();
                $followedUp = $assignedLeads($hr->id)->tap(fn ($q) => $followedUpFilter($q, $hr->id))->count();
                $overdue = $assignedLeads($hr->id)
                    ->where('assigned_at', '<', now()->subDay())
                    ->whereDoesntHave('status', fn ($q) => $q->whereIn('slug', ['joined', 'lost', 'not_interested', 'invalid_number']))
                    ->whereNot(fn ($q) => $followedUpFilter($q, $hr->id))
                    ->count();

                $avgResponseMinutes = DB::selectOne('
                    SELECT AVG(TIMESTAMPDIFF(MINUTE, l.assigned_at, fa.first_action)) AS avg_minutes
                    FROM leads l
                    JOIN (
                        SELECT lead_id, MIN(created_at) AS first_action FROM (
                            SELECT lead_id, created_at FROM lead_calls WHERE initiated_by_user_id = ?
                            UNION ALL
                            SELECT lead_id, created_at FROM lead_status_history WHERE changed_by_user_id = ?
                        ) actions GROUP BY lead_id
                    ) fa ON fa.lead_id = l.id
                    WHERE l.assigned_to_user_id = ?
                      AND l.assigned_at IS NOT NULL
                      AND fa.first_action >= l.assigned_at
                      '.($from ? 'AND l.assigned_at >= ?' : '').'
                      '.($to ? 'AND l.assigned_at <= ?' : '').'
                ', array_merge([$hr->id, $hr->id, $hr->id], $from ? [$from] : [], $to ? [$to] : []))->avg_minutes;

                $lastActivity = collect([
                    LeadCall::where('initiated_by_user_id', $hr->id)->max('created_at'),
                    LeadStatusHistory::where('changed_by_user_id', $hr->id)->max('created_at'),
                ])->filter()->max();

                return (object) [
                    'user' => $hr,
                    'assigned' => $assigned,
                    'followed_up' => $followedUp,
                    'pending' => $assigned - $followedUp,
                    'overdue' => $overdue,
                    'calls' => LeadCall::where('initiated_by_user_id', $hr->id)->where('type', 'manual')
                        ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                        ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
                        ->count(),
                    'interested' => $assignedLeads($hr->id)->whereHas('status', fn ($q) => $q->whereIn('slug', ['interested', 'follow_up']))->count(),
                    'joined' => $assignedLeads($hr->id)->whereHas('status', fn ($q) => $q->where('slug', 'joined'))->count(),
                    'avg_response_minutes' => $avgResponseMinutes !== null ? (int) round($avgResponseMinutes) : null,
                    'last_activity' => $lastActivity ? Carbon::parse($lastActivity) : null,
                ];
            });

        return view('leads.performance', [
            'rows' => $rows,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ]);
    }

    /**
     * Manual add from the CRM. MEDIA_STRATEGIST needs only a mobile number; other roles need name + mobile.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($this->canCreate(), 403, 'Your role is not allowed to add leads.');

        // MEDIA_STRATEGIST only needs a mobile number; everyone else also needs a name.
        $nameRule = $this->roleCode() === 'MEDIA_STRATEGIST' ? 'nullable' : 'required';

        $validated = $request->validate([
            'mobile' => 'required|string|max:20',
            'name' => $nameRule.'|string|max:150',
            'email' => 'nullable|email|max:150',
            'campaign_name' => 'nullable|string|max:150',
        ]);

        $lead = $this->leads->create($validated, 'manual', Auth::id());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'lead_id' => $lead->id]);
        }

        return redirect()->route('leads.show', $lead)->with('success', 'Lead added and team notified.');
    }

    public function show(Lead $lead): View
    {
        // Scoped users can only open their own leads.
        abort_unless(
            $this->canViewAll() || $lead->assigned_to_user_id === Auth::id() || $lead->added_by_user_id === Auth::id(),
            403
        );

        $lead->load(['status', 'assignee', 'assignedBy', 'creator', 'calls.initiatedBy', 'statusHistory.status', 'statusHistory.changedBy']);

        // Course catalogue + active batches from the LMS for the "interested
        // course" picker and the manual "Allocate to Batch" card.
        // Best-effort: the page still works if the LMS DB is down.
        try {
            $lmsCourses = Course::query()->orderBy('name')->get(['slug', 'name']);
            $lmsBatches = LmsBatch::query()->with('course')
                ->where('status', 'active')
                ->orderBy('type')->orderByDesc('starts_on')
                ->get(['id', 'number', 'type', 'course_id', 'starts_on']);
        } catch (QueryException|\PDOException) {
            $lmsCourses = collect();
            $lmsBatches = collect();
        }

        return view('leads.show', [
            'lead' => $lead,
            'statuses' => LeadStatus::orderBy('sort_order')->get(),
            'hrUsers' => User::query()->withRoleCode(self::HR_ASSIGNABLE_ROLES)->where('is_active', true)->orderBy('full_name')->get(),
            'canAssign' => in_array($this->roleCode(), self::CAN_ASSIGN_ROLES, true),
            'callConfigured' => app(CallerDigitalService::class)->isConfigured(),
            'aiProviders' => app(AiLeadAnalysisService::class)->configuredProviders(),
            'aiAnalyses' => $lead->aiAnalyses()->with('requestedBy')->take(5)->get(),
            'lmsCourses' => $lmsCourses,
            'lmsBatches' => $lmsBatches,
        ]);
    }

    /**
     * The manual conversion moment: HR allocates the lead into an LMS batch.
     * The LMS finds-or-creates the student account, seats them, and sends the
     * batch number + login credentials; the lead is then marked Joined.
     */
    public function allocateToBatch(Request $request, Lead $lead, LmsCommandRunner $lms): RedirectResponse
    {
        $validated = $request->validate(['lms_batch_id' => 'required|integer']);

        try {
            $batch = LmsBatch::query()->with('course')->findOrFail($validated['lms_batch_id']);
        } catch (QueryException|\PDOException) {
            return back()->with('error', 'The LMS database is not reachable right now — try again shortly.');
        }

        $args = [
            'batch:add-student',
            (string) $batch->id,
            $lead->displayName(),
            '--status=enrolled',
            '--credentials',
            '--phone='.$lead->mobile,
        ];
        if (filled($lead->email)) {
            $args[] = '--email='.$lead->email;
        }

        $result = $lms->run($args);

        if (! $result['ok']) {
            return back()->with('error', 'Could not allocate: '.mb_substr($result['output'], 0, 300));
        }

        $lead->forceFill(['allocated_batch_number' => $batch->number])->save();
        $this->leads->markJoined($lead, "Allocated to LMS batch {$batch->number} ({$batch->course?->name}) — student account created, OTP sign-in link sent.");

        return back()->with('success', "Lead moved into batch {$batch->number} — student account created; batch number + sign-in link sent on WhatsApp + email (OTP login, no password).");
    }

    /**
     * Set which course the lead is interested in. If the lead is already
     * Interested, the LMS handoff runs immediately so the next weekend
     * masterclass for that course seats them.
     */
    public function updateCourse(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'interested_course_slug' => 'nullable|string|max:255',
        ]);

        $lead->update(['interested_course_slug' => $validated['interested_course_slug'] ?: null]);

        if ($lead->fresh()->status?->slug === 'interested') {
            $this->leads->pushInterestedToLms($lead->fresh());
        }

        return back()->with('success', 'Interested course saved.');
    }

    /**
     * Run an AI analysis of this lead's calls/transcripts/history and return
     * the feedback (why it didn't convert, approach issues, next actions).
     */
    public function analyzeWithAi(Request $request, Lead $lead, AiLeadAnalysisService $ai): JsonResponse
    {
        abort_unless(
            $this->canViewAll() || $lead->assigned_to_user_id === Auth::id() || $lead->added_by_user_id === Auth::id(),
            403
        );

        $validated = $request->validate(['provider' => 'required|string|in:anthropic,openai,kimi']);

        try {
            $analysis = $ai->analyze($lead, $validated['provider'], Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('AI lead analysis failed', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Analysis failed — check the server log.'], 500);
        }

        return response()->json([
            'success' => true,
            'analysis' => $analysis->analysis,
            'provider' => $analysis->provider,
            'model' => $analysis->model,
            'created_at' => $analysis->created_at->format('d M Y, h:i A'),
        ]);
    }

    /**
     * HR Manager assigns the lead to one of the calling roles. The target is
     * re-checked here, not just filtered in the dropdown — otherwise a hand-made
     * request could park a lead on someone who never calls (a manager, a
     * trainer, an accountant) and it would sit there unworked.
     */
    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless(in_array($this->roleCode(), self::CAN_ASSIGN_ROLES, true), 403);

        $validated = $request->validate([
            'assigned_to_user_id' => [
                'required',
                Rule::exists('users', 'id')->where('is_active', true),
                function (string $attribute, mixed $value, callable $fail) {
                    $code = User::find($value)?->role?->role_code;
                    if (! in_array($code, self::HR_ASSIGNABLE_ROLES, true)) {
                        $fail('Leads can only be assigned to a calling HR role — managers supervise, they do not carry a lead list.');
                    }
                },
            ],
        ]);

        $lead->update([
            'assigned_to_user_id' => $validated['assigned_to_user_id'],
            'assigned_by_user_id' => Auth::id(),
            'assigned_at' => now(),
        ]);

        // Keep an assignment audit trail.
        DB::table('lead_assignments')->insert([
            'lead_id' => $lead->id,
            'assigned_to_user_id' => $validated['assigned_to_user_id'],
            'assigned_by_user_id' => Auth::id(),
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Notify the assignee: email + WhatsApp + web-push (with sound).
        if ($assignee = User::find($validated['assigned_to_user_id'])) {
            $this->leads->notifyLeadAssigned($lead, $assignee, Auth::user());
        }

        return back()->with('success', 'Lead assigned and the HR user has been notified.');
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'status_id' => 'required|exists:lead_statuses,id',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $lead->update(['current_status_id' => $validated['status_id']]);

        LeadStatusHistory::create([
            'lead_id' => $lead->id,
            'status_id' => $validated['status_id'],
            'changed_by_user_id' => Auth::id(),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // Interested candidates get the masterclass watch link right away
        // (WhatsApp + email) and appear on the LMS Batch Funnel page — batch
        // allocation itself stays manual (the "Allocate to Batch" card).
        if ($lead->fresh()->status?->slug === 'interested') {
            $this->leads->handleInterested($lead->fresh());
        }

        return back()->with('success', 'Status updated.');
    }

    /**
     * Manually (re)trigger an outbound AI voice call via Caller.Digital.
     */
    public function triggerAiCall(Lead $lead, CallerDigitalService $caller): RedirectResponse
    {
        if (! $caller->isConfigured()) {
            return back()->with('error', 'Caller.Digital is not configured. Set CALLER_DIGITAL_* in .env.');
        }

        if ($this->leads->aiCallCapReached()) {
            return back()->with('error', "Today's AI call limit has been reached. Try again tomorrow or raise CALLER_DIGITAL_DAILY_CAP.");
        }

        $call = $this->leads->triggerAiCall($lead, Auth::id());

        if ($call && $call->status === 'failed') {
            return back()->with('error', 'Could not start the AI call: '.($call->meta['error'] ?? 'unknown error'));
        }

        return back()->with('success', 'AI call triggered. Results will appear here once the call completes.');
    }

    /**
     * HR logs a call made manually — status, transcript, and an optional audio recording.
     */
    public function storeManualCall(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,no_answer,busy,failed',
            'disposition' => 'nullable|string|max:150',
            'duration_seconds' => 'nullable|integer|min:0',
            'transcript' => 'nullable|string',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/webm,audio/ogg|max:20480',
        ]);

        $audioPath = null;
        if ($request->hasFile('audio')) {
            $audioPath = $request->file('audio')->store('lead-call-recordings', 'local');
        }

        LeadCall::create([
            'lead_id' => $lead->id,
            'type' => 'manual',
            'status' => $validated['status'],
            'disposition' => $validated['disposition'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
            'transcript' => $validated['transcript'] ?? null,
            'audio_path' => $audioPath,
            'to_number' => $lead->mobile,
            'initiated_by_user_id' => Auth::id(),
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        return back()->with('success', 'Call logged.');
    }

    public function downloadAudio(LeadCall $call): StreamedResponse
    {
        abort_unless($call->audio_path && Storage::disk('local')->exists($call->audio_path), 404);

        return Storage::disk('local')->download($call->audio_path, 'call-'.$call->id.'-recording'.'.'.pathinfo($call->audio_path, PATHINFO_EXTENSION));
    }

    /**
     * Public website capture endpoint (token-protected). Requires name + email + phone.
     */
    public function apiCapture(Request $request)
    {
        $token = config('services.lead_capture_token');
        if (blank($token) || $request->header('X-Lead-Token') !== $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'mobile' => 'required|string|max:20',
            'campaign_name' => 'nullable|string|max:150',
        ]);

        // Dedupe: the same number submitting again within 24h returns the existing lead
        // instead of creating a duplicate (and re-firing notifications + a paid AI call).
        $existing = Lead::where('mobile', $validated['mobile'])
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Lead already captured', 'lead_id' => $existing->id, 'duplicate' => true], 200);
        }

        $lead = $this->leads->create($validated, 'website', null);

        return response()->json(['message' => 'Lead captured', 'lead_id' => $lead->id], 201);
    }

    private function roleCode(): ?string
    {
        return Auth::user()?->role?->role_code;
    }

    private function canCreate(): bool
    {
        return (bool) Auth::user()?->hasPermission('create_lead');
    }

    private function canViewAll(): bool
    {
        return (bool) Auth::user()?->hasPermission('view_all_leads');
    }
}
