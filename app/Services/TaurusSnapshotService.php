<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\Lms\Attendance;
use App\Models\Lms\BatchMember;
use App\Models\Lms\JobApplicationRound;
use App\Models\Lms\LiveSession;
use App\Models\Lms\MockInterview;
use App\Models\Lms\Payment;
use App\Models\Lms\ProductPurchase;
use App\Models\TaurusAction;
use App\Models\TaurusSubscription;
use App\Models\TaurusTarget;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PDOException;

/**
 * Every number on the Taurus Neural Ops page.
 *
 * Two databases feed it: this CRM (leads, expenses, staff, targets) and the
 * read-only `lms` connection (fees, attendance, mocks, placements). LMS reads
 * are wrapped so that an unreachable LMS degrades one panel to "—" instead of
 * 500-ing the whole dashboard — the CRM half stays useful either way.
 *
 * Money is handled in the unit each source stores it in and only converted at
 * the edge: LMS amounts are integer paise, CRM expenses are decimal rupees.
 */
class TaurusSnapshotService
{
    /** Money below this is shown in rupees; at or above it, in lakhs. */
    private const LAKH = 100000;

    /**
     * Per-instance memo. Panels read each other — the ticker quotes both the
     * sentinel and the team signal — and without this a single snapshot would
     * run those queries twice.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $compute
     * @return TValue
     */
    private function once(string $key, callable $compute): mixed
    {
        return $this->memo[$key] ??= $compute();
    }

    /**
     * The whole dashboard in one array — also the exact payload handed to the
     * AI agent, so the model can never answer from numbers the founder is not
     * looking at.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [
            'generated_at' => now()->toDateTimeString(),
            'pipeline' => $this->pipeline(),
            'finance' => $this->finance(),
            'vitals' => $this->vitals(),
            'subscriptions' => $this->subscriptions(),
            'team' => $this->team(),
            'events' => $this->events(),
            'pending_actions' => $this->pendingActions(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Placement pipeline */
    /* ------------------------------------------------------------------ */

    /**
     * Mocks, real interview rounds and offers — today, month to date, and a
     * seven-day trend for the sparklines.
     *
     * @return array<string, mixed>
     */
    public function pipeline(): array
    {
        return $this->fromLms(fn () => [
            'mocks' => $this->pipelineBlock(
                fn () => MockInterview::query(), 'started_at', config('taurus.owners.mocks')
            ),
            'interviews' => $this->pipelineBlock(
                fn () => JobApplicationRound::query(), 'happened_on', config('taurus.owners.interviews')
            ),
            'offers' => $this->pipelineBlock(
                fn () => JobApplicationRound::query()->where('outcome', 'offer'),
                'happened_on',
                config('taurus.owners.offers')
            ),
            'available' => true,
        ], [
            'mocks' => $this->emptyPipelineBlock(config('taurus.owners.mocks')),
            'interviews' => $this->emptyPipelineBlock(config('taurus.owners.interviews')),
            'offers' => $this->emptyPipelineBlock(config('taurus.owners.offers')),
            'available' => false,
        ]);
    }

    /**
     * @param  callable(): \Illuminate\Database\Eloquent\Builder<*>  $query
     * @return array{today: int, mtd: int, spark: array<int, int>, owner: string}
     */
    private function pipelineBlock(callable $query, string $dateColumn, ?string $ownerId): array
    {
        $countBetween = fn (CarbonInterface $from, CarbonInterface $to): int => $query()
            ->whereBetween($dateColumn, [$from, $to])
            ->count();

        $spark = [];
        for ($daysBack = 6; $daysBack >= 0; $daysBack--) {
            $day = now()->subDays($daysBack);
            $spark[] = $countBetween($day->copy()->startOfDay(), $day->copy()->endOfDay());
        }

        return [
            'today' => $countBetween(now()->startOfDay(), now()->endOfDay()),
            'mtd' => $countBetween(now()->startOfMonth(), now()),
            'spark' => $spark,
            'owner' => $this->ownerName($ownerId),
        ];
    }

    /**
     * @return array{today: int, mtd: int, spark: array<int, int>, owner: string}
     */
    private function emptyPipelineBlock(?string $ownerId): array
    {
        return ['today' => 0, 'mtd' => 0, 'spark' => array_fill(0, 7, 0), 'owner' => $this->ownerName($ownerId)];
    }

    /* ------------------------------------------------------------------ */
    /* Finance */
    /* ------------------------------------------------------------------ */

    /**
     * Revenue is defined exactly as the existing Revenue Summary page defines
     * it — captured LMS fee payments plus captured store purchases — so the
     * two screens can never disagree. Expenses are CRM expenses excluding
     * cancelled ones.
     *
     * @return array<string, mixed>
     */
    public function finance(): array
    {
        $expensesBetween = fn (CarbonInterface $from, CarbonInterface $to): int => (int) round(
            $this->expensesInWindow($from, $to)->sum('amount') * 100
        );

        $revenueBetween = fn (CarbonInterface $from, CarbonInterface $to): int => $this->fromLms(
            fn () => (int) Payment::query()->where('status', 'captured')->whereBetween('captured_at', [$from, $to])->sum('amount_paise')
                + (int) ProductPurchase::query()->where('status', 'captured')->whereBetween('captured_at', [$from, $to])->sum('total_paise'),
            0
        );

        $revToday = $revenueBetween(now()->startOfDay(), now()->endOfDay());
        $revMtd = $revenueBetween(now()->startOfMonth(), now());
        $revYtd = $revenueBetween(now()->startOfYear(), now());
        $expMtd = $expensesBetween(now()->startOfMonth(), now());
        $expYtd = $expensesBetween(now()->startOfYear(), now());

        // Calendar months of the current year up to now, for the trend chart.
        $months = [];
        $revByMonth = [];
        $expByMonth = [];
        for ($month = 1; $month <= now()->month; $month++) {
            $start = Carbon::create(now()->year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $months[] = $start->format('M');
            $revByMonth[] = $this->toLakhs($revenueBetween($start, $end));
            $expByMonth[] = $this->toLakhs($expensesBetween($start, $end));
        }

        return [
            'revTodayPaise' => $revToday,
            'revToday' => $this->rupees($revToday),
            'revMTD' => $this->toLakhs($revMtd),
            'revYTD' => $this->toLakhs($revYtd),
            'expMTD' => $this->toLakhs($expMtd),
            'expYTD' => $this->toLakhs($expYtd),
            'netMTD' => $this->toLakhs($revMtd - $expMtd),
            'netYTD' => $this->toLakhs($revYtd - $expYtd),
            'marginMTD' => $revMtd > 0 ? (int) round(($revMtd - $expMtd) / $revMtd * 100) : null,
            'months' => $months,
            'revByMonth' => $revByMonth,
            'expByMonth' => $expByMonth,
            'owners' => [
                'revenue' => $this->ownerName(config('taurus.owners.revenue')),
                'expenses' => $this->ownerName(config('taurus.owners.expenses')),
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Business vitals */
    /* ------------------------------------------------------------------ */

    /**
     * Conversion, funnel, attendance on both sides of the house, and paid
     * marketing efficiency.
     *
     * @return array<string, mixed>
     */
    public function vitals(): array
    {
        $monthStart = now()->startOfMonth();

        $leadsMtd = Lead::whereBetween('created_at', [$monthStart, now()])->count();

        $inStages = fn (array $slugs): int => Lead::query()
            ->whereBetween('created_at', [$monthStart, now()])
            ->whereHas('status', fn ($q) => $q->whereIn('slug', $slugs))
            ->count();

        $engaged = $inStages(config('taurus.funnel.engaged'));
        $joined = $inStages(config('taurus.funnel.won'));

        // This CRM's stand-in for "demo booked": the masterclass watch link
        // going out is the point a lead agreed to see the product.
        $masterclass = Lead::query()
            ->whereBetween('created_at', [$monthStart, now()])
            ->whereNotNull('masterclass_link_sent_at')
            ->count();

        $adSpendPaise = $this->adSpendPaise($monthStart, now());

        return [
            'leadsMTD' => $leadsMtd,
            'joinedMTD' => $joined,
            'convRate' => $leadsMtd > 0 ? round($joined / $leadsMtd * 100, 1) : 0.0,
            'funnel' => [
                'labels' => ['Leads', 'Engaged', 'Masterclass', 'Joined'],
                'data' => [$leadsMtd, $engaged, $masterclass, $joined],
            ],
            'attendance' => [
                'students' => $this->studentAttendanceToday(),
                'staff' => $this->staffAttendanceToday(),
            ],
            'adSpendMTD' => $adSpendPaise > 0 ? $this->rupees($adSpendPaise) : '—',
            'adSpendPaise' => $adSpendPaise,
            'cpl' => ($adSpendPaise > 0 && $leadsMtd > 0) ? $this->rupees((int) round($adSpendPaise / $leadsMtd)) : '—',
            'owners' => [
                'conversion' => $this->ownerName(config('taurus.owners.conversion')),
                'attendance' => $this->ownerName(config('taurus.owners.attendance')),
                'ads' => $this->ownerName(config('taurus.owners.ads')),
            ],
        ];
    }

    /**
     * Paid-marketing spend in the window, in paise. `expenses.category` is
     * free text, so match on the fragments configured in config/taurus.php.
     */
    private function adSpendPaise(CarbonInterface $from, CarbonInterface $to): int
    {
        $query = $this->expensesInWindow($from, $to)->where(function ($q) {
            foreach (config('taurus.ad_spend_categories') as $fragment) {
                $q->orWhere('category', 'like', "%{$fragment}%");
            }
        });

        return (int) round($query->sum('amount') * 100);
    }

    /**
     * Live expenses inside a date window.
     *
     * The bounds are full timestamps, not bare dates, on purpose: `expense_date`
     * is cast to a date but Laravel serialises it as "Y-m-d H:i:s", so a bare
     * "2026-08-05" upper bound sorts *before* "2026-08-05 00:00:00" and silently
     * drops everything spent on the last day of the window.
     *
     * @return Builder<Expense>
     */
    private function expensesInWindow(CarbonInterface $from, CarbonInterface $to)
    {
        return Expense::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('expense_date', [
                $from->copy()->startOfDay()->toDateTimeString(),
                $to->copy()->endOfDay()->toDateTimeString(),
            ]);
    }

    /**
     * Attendance for classes scheduled today. "Present" means an attendance
     * row with a non-zero attended_pct — marking someone absent writes a zero
     * rather than deleting the row.
     *
     * @return array{present: int, roster: int, pct: int|null, sessions: int, available: bool}
     */
    private function studentAttendanceToday(): array
    {
        return $this->fromLms(function () {
            $sessionIds = LiveSession::query()->whereDate('scheduled_start', today())->pluck('id');

            if ($sessionIds->isEmpty()) {
                return ['present' => 0, 'roster' => 0, 'pct' => null, 'sessions' => 0, 'available' => true];
            }

            $batchIds = LiveSession::query()->whereIn('id', $sessionIds)->pluck('batch_id')->unique();
            $roster = BatchMember::query()->whereIn('batch_id', $batchIds)->distinct()->count('user_id');

            $present = Attendance::query()
                ->whereIn('live_session_id', $sessionIds)
                ->where('attended_pct', '>', 0)
                ->distinct()
                ->count('user_id');

            return [
                'present' => $present,
                'roster' => $roster,
                'pct' => $roster > 0 ? (int) round($present / $roster * 100) : null,
                'sessions' => $sessionIds->count(),
                'available' => true,
            ];
        }, ['present' => 0, 'roster' => 0, 'pct' => null, 'sessions' => 0, 'available' => false]);
    }

    /**
     * Staff attendance uses the same definition as the main dashboard: a
     * distinct login recorded today, against the active headcount.
     *
     * @return array{present: int, total: int, pct: int|null, on_leave: int}
     */
    private function staffAttendanceToday(): array
    {
        $present = DB::table('user_login_logs')
            ->whereDate('login_time', today())
            ->distinct()
            ->count('user_id');

        $total = User::where('employment_status', 'Active')->count();

        $onLeave = LeaveRequest::where('status', 'approved')
            ->whereDate('from_date', '<=', today())
            ->whereDate('to_date', '>=', today())
            ->count();

        return [
            'present' => $present,
            'total' => $total,
            'pct' => $total > 0 ? (int) round($present / $total * 100) : null,
            'on_leave' => $onLeave,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Expense sentinel */
    /* ------------------------------------------------------------------ */

    /**
     * Active recurring spend, worst offenders first, with the monthly burn
     * already flagged as waste.
     *
     * @return array{rows: array<int, array<string, mixed>>, waste_monthly: float, total_monthly: float}
     */
    public function subscriptions(): array
    {
        return $this->once('subscriptions', fn () => $this->computeSubscriptions());
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, waste_monthly: float, total_monthly: float}
     */
    private function computeSubscriptions(): array
    {
        $subs = TaurusSubscription::query()->where('is_active', true)->orderBy('name')->get();

        $pendingIds = TaurusAction::pending()
            ->where('action', 'cancel_subscription')
            ->where('target_type', 'taurus_subscription')
            ->pluck('target_id')
            ->all();

        $rows = $subs->map(function (TaurusSubscription $sub) use ($pendingIds) {
            $days = $sub->daysIdle();

            return [
                'id' => $sub->id,
                'name' => $sub->name,
                'vendor' => $sub->vendor,
                'seats' => $sub->seats,
                'last_used_on' => $sub->last_used_on?->toDateString(),
                'usage_note' => $sub->usage_note,
                'cost' => (float) $sub->monthly_cost,
                'cost_display' => '₹'.number_format((float) $sub->monthly_cost),
                'flag' => $sub->sentinelFlag(),
                'note' => $sub->usage_note ?: ($days === null
                    ? 'No usage recorded'
                    : "Last used {$days} day".($days === 1 ? '' : 's').' ago'),
                'cancel_pending' => in_array($sub->id, $pendingIds, true),
            ];
        })->sortBy(fn (array $row) => ['waste' => 0, 'watch' => 1, 'ok' => 2][$row['flag']])->values();

        return [
            'rows' => $rows->all(),
            'waste_monthly' => (float) $rows->where('flag', 'waste')->sum('cost'),
            'total_monthly' => (float) $rows->sum('cost'),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Team signal */
    /* ------------------------------------------------------------------ */

    /**
     * Targets set for the current month, each against a live-measured actual.
     *
     * @return array<int, array<string, mixed>>
     */
    public function team(): array
    {
        return $this->once('team', fn () => $this->computeTeam());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function computeTeam(): array
    {
        $period = now()->format('Y-m');

        $targets = TaurusTarget::with('user.role')->forPeriod($period)->get();

        return $targets->map(function (TaurusTarget $target) {
            $actual = $this->actualFor($target->user_id, $target->metric);
            $goal = (float) $target->target_value;
            $share = $goal > 0 ? $actual / $goal : 0.0;

            return [
                'id' => $target->id,
                'user_id' => $target->user_id,
                'name' => $target->user?->full_name ?? 'Unknown',
                'role' => $target->user?->role?->role_name ?? '—',
                'metric' => $target->metricLabel(),
                'metric_key' => $target->metric,
                'actual' => $actual,
                'target' => $goal,
                'n' => $this->formatNumber($actual).' / '.$this->formatNumber($goal),
                'pct' => (int) round($share * 100),
                'trend' => match (true) {
                    $share >= 1 => 'up',
                    $share < config('taurus.team_signal.track_at') => 'dn',
                    default => 'fl',
                },
                'sig' => match (true) {
                    $share >= config('taurus.team_signal.keep_at') => 'keep',
                    $share >= config('taurus.team_signal.track_at') => 'track',
                    default => 'review',
                },
            ];
        })->sortBy('pct')->values()->all();
    }

    /**
     * Measure one person's month-to-date actual for one metric. Every branch
     * reads the system of record directly — nothing is cached or precomputed,
     * so these numbers always agree with the underlying screens.
     */
    private function actualFor(int $userId, string $metric): float
    {
        $from = now()->startOfMonth();
        $to = now();

        return (float) match ($metric) {
            'leads_added' => Lead::where('added_by_user_id', $userId)
                ->whereBetween('created_at', [$from, $to])
                ->count(),

            // Credit goes to whoever moved the lead to "joined", not to whoever
            // happens to be assigned to it now.
            'leads_converted' => DB::table('lead_status_history')
                ->join('lead_statuses', 'lead_statuses.id', '=', 'lead_status_history.status_id')
                ->where('lead_status_history.changed_by_user_id', $userId)
                ->where('lead_statuses.slug', 'joined')
                ->whereBetween('lead_status_history.created_at', [$from, $to])
                ->distinct()
                ->count('lead_status_history.lead_id'),

            'calls_made' => DB::table('lead_calls')
                ->where('initiated_by_user_id', $userId)
                ->whereBetween('started_at', [$from, $to])
                ->count(),

            'tasks_completed' => DB::table('task_assignees')
                ->where('user_id', $userId)
                ->where('is_completed', true)
                ->whereBetween('completed_at', [$from, $to])
                ->count(),

            default => 0,
        };
    }

    /* ------------------------------------------------------------------ */
    /* Ticker + approval queue */
    /* ------------------------------------------------------------------ */

    /**
     * The live event strip: newest leads, captured payments, offers, and any
     * flagged spend. Lead names are deliberately left out — this strip scrolls
     * on screen, and a course plus a source says as much operationally.
     *
     * @return array<int, array{kind: string, text: string}>
     */
    public function events(): array
    {
        $events = collect();

        Lead::with('status')->latest()->limit(4)->get()
            ->each(function (Lead $lead) use ($events) {
                $events->push([
                    'kind' => 'lead',
                    'text' => 'New lead · '.($lead->interested_course_slug ?: 'Course not set')
                        .' · '.($lead->source ?: 'direct'),
                ]);
            });

        $this->fromLms(function () use ($events) {
            Payment::query()->where('status', 'captured')->latest('captured_at')->limit(3)->get()
                ->each(fn (Payment $payment) => $events->push([
                    'kind' => 'pay',
                    'text' => 'Fee payment captured · '.$this->rupees((int) $payment->amount_paise),
                ]));

            JobApplicationRound::query()->where('outcome', 'offer')->latest('happened_on')->limit(3)->get()
                ->each(fn (JobApplicationRound $round) => $events->push([
                    'kind' => 'pay',
                    'text' => 'Offer received · round '.$round->round_no.' cleared on '
                        .$round->happened_on?->format('d M'),
                ]));

            return null;
        }, null);

        $subs = $this->subscriptions();
        if ($subs['waste_monthly'] > 0) {
            $events->push([
                'kind' => 'alert',
                'text' => 'Expense sentinel · ₹'.number_format($subs['waste_monthly']).'/mo flagged as unused',
            ]);
        }

        foreach ($this->team() as $member) {
            if ($member['sig'] === 'review') {
                $events->push([
                    'kind' => 'alert',
                    'text' => $member['name'].' · '.$member['metric'].' at '.$member['pct'].'% of target',
                ]);
            }
        }

        if ($events->isEmpty()) {
            $events->push(['kind' => 'lead', 'text' => 'No activity recorded yet today.']);
        }

        return $events->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingActions(): array
    {
        return TaurusAction::with('requestedBy')->pending()->latest()->get()
            ->map(fn (TaurusAction $action) => [
                'id' => $action->id,
                'action' => $action->action,
                'summary' => $action->summary,
                'requested_by' => $action->requestedBy?->full_name ?? 'Taurus',
                'requested_at' => $action->created_at?->diffForHumans(),
            ])->all();
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * Run an LMS read, falling back to $onFailure if that database is down.
     * The CRM half of the dashboard must survive an LMS outage.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @param  TValue  $onFailure
     * @return TValue
     */
    private function fromLms(callable $callback, mixed $onFailure): mixed
    {
        try {
            return $callback();
        } catch (QueryException|PDOException $e) {
            report($e);

            return $onFailure;
        }
    }

    /**
     * Resolve a configured owner id to a display name. Unset or deleted users
     * read "Unassigned" rather than showing an invented name.
     */
    private function ownerName(?string $userId): string
    {
        if (blank($userId)) {
            return 'Unassigned';
        }

        /** @var Collection<int, string> $names */
        static $names = null;
        $names ??= User::query()->pluck('full_name', 'id');

        return $names[(int) $userId] ?? 'Unassigned';
    }

    /** Paise to a rupee string. */
    private function rupees(int $paise): string
    {
        return '₹'.number_format($paise / 100);
    }

    /** Paise to lakhs, two decimals — the unit the founder reads in. */
    private function toLakhs(int $paise): float
    {
        return round($paise / 100 / self::LAKH, 2);
    }

    private function formatNumber(float $value): string
    {
        return floor($value) == $value ? (string) (int) $value : number_format($value, 2);
    }
}
