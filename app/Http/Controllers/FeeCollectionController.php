<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\AccessBlock;
use App\Models\Lms\Batch;
use App\Models\Lms\FeePlan;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Batch-wise fee collection report (founder request, Jul 2026): who has paid,
 * who is due, who is overdue/blocked — with phone numbers, so the team can run
 * manual follow-up calls without opening the LMS. Reads the LMS database live.
 */
class FeeCollectionController extends Controller
{
    use ReadsLmsDatabase;

    public function __construct(private readonly LmsCommandRunner $lms) {}

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $plans = FeePlan::query()
                ->with(['user', 'batch.course', 'instalments', 'payments' => fn ($q) => $q->orderByDesc('captured_at')])
                ->whereIn('status', ['active', 'paid'])
                ->when($request->input('batch_id'), fn ($q, $batchId) => $q->where('batch_id', $batchId))
                ->when($request->input('search'), function ($q, $search) {
                    $q->whereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                })
                ->get();

            $blockedUserIds = AccessBlock::query()
                ->whereNull('lifted_at')
                ->whereIn('user_id', $plans->pluck('user_id'))
                ->get()
                ->groupBy('user_id')
                ->map(fn ($rows) => $rows->contains(fn ($b) => $b->level === 'hard') ? 'hard' : 'soft');

            $today = Carbon::today();

            $rows = $plans->map(function (FeePlan $plan) use ($blockedUserIds, $today) {
                $paid = $plan->instalments->where('status', 'paid');
                $unpaid = $plan->instalments->where('status', '!=', 'paid')->sortBy('seq');
                $next = $unpaid->first();
                $dueOn = $next?->due_on ? Carbon::parse($next->due_on) : null;
                $overdueDays = $dueOn !== null && $dueOn->lt($today) ? (int) $dueOn->diffInDays($today) : 0;
                $lastPayment = $plan->payments->firstWhere('status', 'captured');

                $bucket = match (true) {
                    $next === null => 'paid',
                    ($blockedUserIds[$plan->user_id] ?? null) !== null => 'blocked',
                    $overdueDays > 0 => 'overdue',
                    $dueOn !== null && $dueOn->lte($today->copy()->addDays(7)) => 'due_soon',
                    default => 'on_track',
                };

                return (object) [
                    'plan' => $plan,
                    'student' => $plan->user,
                    'batch' => $plan->batch,
                    'next_instalment_id' => $next?->id,
                    'has_link' => $next !== null && ! empty($next->payment_link_url),
                    'total_paise' => (int) $plan->total_paise - (int) $plan->discount_paise,
                    'collected_paise' => (int) $paid->sum('amount_paise'),
                    'outstanding_paise' => (int) $unpaid->sum('amount_paise'),
                    'paid_count' => $paid->count(),
                    'instalment_count' => $plan->instalments->count(),
                    'next_amount_paise' => $next !== null ? (int) $next->amount_paise : 0,
                    'next_due_on' => $dueOn,
                    'overdue_days' => $overdueDays,
                    'block' => $blockedUserIds[$plan->user_id] ?? null,
                    'last_paid_at' => $lastPayment?->captured_at,
                    'bucket' => $bucket,
                ];
            });

            if ($bucket = $request->input('status')) {
                $rows = $rows->where('bucket', $bucket);
            }

            // Follow-up order: blocked first, then most-overdue, then nearest due.
            $order = ['blocked' => 0, 'overdue' => 1, 'due_soon' => 2, 'on_track' => 3, 'paid' => 4];
            $rows = $rows->sortBy([
                fn ($a, $b) => ($order[$a->bucket] ?? 9) <=> ($order[$b->bucket] ?? 9),
                fn ($a, $b) => $b->overdue_days <=> $a->overdue_days,
            ])->values();

            $stats = [
                'expected' => $rows->sum('total_paise'),
                'collected' => $rows->sum('collected_paise'),
                'outstanding' => $rows->sum('outstanding_paise'),
                'follow_ups' => $rows->whereIn('bucket', ['blocked', 'overdue', 'due_soon'])->count(),
            ];

            $batchOptions = Batch::with('course')->whereIn('type', ['paid', 'self_paced'])->orderByDesc('created_at')->get();

            return view('fee-collections.index', compact('rows', 'stats', 'batchOptions'));
        });
    }

    /**
     * Create the Razorpay link for an instalment and WhatsApp/email it to the
     * student (LMS `fees:send-link`). The student pays from their phone; the
     * reconcile sync marks it paid everywhere.
     */
    public function sendLink(int $instalment): RedirectResponse
    {
        $result = $this->lms->run(['fees:send-link', (string) $instalment], 60);

        return $result['ok']
            ? back()->with('success', trim($result['output']) ?: 'Payment link sent.')
            : back()->with('error', 'Could not send the link: '.mb_substr($result['output'], 0, 300));
    }

    /**
     * Pull payment confirmations from Razorpay now (LMS `fees:reconcile-links`)
     * — the on-demand version of the every-15-minutes scheduled sync.
     */
    public function syncPayments(): RedirectResponse
    {
        $result = $this->lms->run(['fees:reconcile-links'], 120);

        return $result['ok']
            ? back()->with('success', trim($result['output']) ?: 'Payments synced.')
            : back()->with('error', 'Sync failed: '.mb_substr($result['output'], 0, 300));
    }
}
