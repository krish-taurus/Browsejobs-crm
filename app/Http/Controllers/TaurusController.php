<?php

namespace App\Http\Controllers;

use App\Models\TaurusAction;
use App\Models\TaurusSubscription;
use App\Models\TaurusTarget;
use App\Models\User;
use App\Services\TaurusAgentService;
use App\Services\TaurusSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Taurus Neural Ops — the Super Admin command centre.
 *
 * Every route here sits behind `auth` + `super-admin` (see routes/web.php).
 * The page shows company-wide finance, per-employee performance and lead
 * activity on one screen, so there is no partial-access mode: either the
 * whole thing opens or none of it does.
 */
class TaurusController extends Controller
{
    public function __construct(
        private readonly TaurusSnapshotService $snapshot,
        private readonly TaurusAgentService $agent,
    ) {}

    public function index(): View
    {
        $user = Auth::user();

        return view('taurus.index', [
            'snapshot' => $this->snapshot->all(),
            'agentReady' => $this->agent->isConfigured(),
            'targetMetrics' => config('taurus.target_metrics'),
            'staff' => User::where('is_active', true)
                ->where('employment_status', 'Active')
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'period' => now()->format('Y-m'),
            'greetName' => $this->firstName($user),
        ]);
    }

    /**
     * First name for the greeting line — `first_name` where it is set, else the
     * leading word of `full_name`, else a neutral fallback.
     */
    private function firstName(?User $user): string
    {
        $name = filled($user?->first_name)
            ? $user->first_name
            : (string) ($user?->full_name ?? '');

        return trim(explode(' ', trim($name))[0] ?: '') ?: 'there';
    }

    /**
     * Whole-snapshot refresh for the page's polling loop — one query pass
     * instead of the kit's six separate endpoints.
     */
    public function snapshot(): JsonResponse
    {
        return response()->json($this->snapshot->all());
    }

    /* ------------------------------------------------------------------ */
    /* Agent */
    /* ------------------------------------------------------------------ */

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:1000'],
        ]);

        return $this->agentResponse(fn () => $this->agent->ask($validated['query'])['reply']);
    }

    public function brief(): JsonResponse
    {
        return $this->agentResponse(fn () => $this->agent->brief());
    }

    public function analyse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'panel' => ['required', 'in:expenses,team'],
        ]);

        return $this->agentResponse(fn () => $validated['panel'] === 'expenses'
            ? $this->agent->analyseExpenses()
            : $this->agent->analyseTeam());
    }

    /**
     * A failed AI call must never take the dashboard down with it — the
     * numbers are the point, the commentary is a bonus.
     *
     * @param  callable(): string  $callback
     */
    private function agentResponse(callable $callback): JsonResponse
    {
        if (! $this->agent->isConfigured()) {
            return response()->json([
                'reply' => 'No AI provider is configured. Add ANTHROPIC_API_KEY (or another provider key) to .env, then run php artisan config:clear.',
            ]);
        }

        try {
            return response()->json(['reply' => $callback()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['reply' => 'Taurus could not reach the model: '.$e->getMessage()], 200);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Subscriptions (expense sentinel) */
    /* ------------------------------------------------------------------ */

    public function storeSubscription(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'monthly_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'last_used_on' => ['nullable', 'date', 'before_or_equal:today'],
            'usage_note' => ['nullable', 'string', 'max:500'],
        ]);

        TaurusSubscription::create($validated + [
            'is_active' => true,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Subscription added to the sentinel.');
    }

    public function updateSubscription(Request $request, TaurusSubscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'monthly_cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'last_used_on' => ['nullable', 'date', 'before_or_equal:today'],
            'usage_note' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $subscription->update($validated + [
            'is_active' => $request->boolean('is_active'),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Subscription updated.');
    }

    public function destroySubscription(TaurusSubscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('success', 'Subscription removed.');
    }

    /* ------------------------------------------------------------------ */
    /* Targets (team signal) */
    /* ------------------------------------------------------------------ */

    public function storeTarget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'metric' => ['required', 'in:'.implode(',', array_keys(config('taurus.target_metrics')))],
            'target_value' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'period' => ['required', 'date_format:Y-m'],
        ]);

        // One target per person, per metric, per month — re-submitting the
        // same combination edits it rather than erroring on the unique index.
        TaurusTarget::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'metric' => $validated['metric'],
                'period' => $validated['period'],
            ],
            [
                'target_value' => $validated['target_value'],
                'set_by_user_id' => Auth::id(),
            ]
        );

        return back()->with('success', 'Target saved.');
    }

    public function destroyTarget(TaurusTarget $target): RedirectResponse
    {
        $target->delete();

        return back()->with('success', 'Target removed.');
    }

    /* ------------------------------------------------------------------ */
    /* Approval queue */
    /* ------------------------------------------------------------------ */

    /**
     * Queue a proposed change. Nothing is executed here — see TaurusAction.
     */
    public function queueAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:cancel_subscription'],
            'id' => ['required', 'integer'],
        ]);

        $subscription = TaurusSubscription::findOrFail($validated['id']);

        $existing = TaurusAction::pending()
            ->where('action', $validated['action'])
            ->where('target_type', 'taurus_subscription')
            ->where('target_id', $subscription->id)
            ->first();

        if ($existing) {
            return response()->json(['queued' => true, 'id' => $existing->id, 'duplicate' => true]);
        }

        $action = TaurusAction::create([
            'action' => $validated['action'],
            'target_type' => 'taurus_subscription',
            'target_id' => $subscription->id,
            'summary' => 'Cancel '.$subscription->name.' — ₹'
                .number_format((float) $subscription->monthly_cost).'/mo',
            'status' => TaurusAction::STATUS_PENDING,
            'requested_by_user_id' => Auth::id(),
        ]);

        return response()->json(['queued' => true, 'id' => $action->id]);
    }

    /**
     * Approving a cancellation marks the subscription inactive so it leaves
     * the sentinel. Cancelling with the vendor is still a human errand — this
     * records the decision, it does not call anyone's billing API.
     */
    public function decideAction(Request $request, TaurusAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,dismissed'],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($action->status !== TaurusAction::STATUS_PENDING) {
            return back()->with('error', 'That request has already been decided.');
        }

        $action->update([
            'status' => $validated['decision'],
            'decision_note' => $validated['decision_note'] ?? null,
            'decided_by_user_id' => Auth::id(),
            'decided_at' => now(),
        ]);

        if ($validated['decision'] === TaurusAction::STATUS_APPROVED
            && $action->action === 'cancel_subscription'
            && $action->target_type === 'taurus_subscription') {
            TaurusSubscription::where('id', $action->target_id)
                ->update(['is_active' => false, 'updated_by' => Auth::id()]);
        }

        return back()->with('success', $validated['decision'] === TaurusAction::STATUS_APPROVED
            ? 'Approved — marked inactive here. Cancel it with the vendor to stop the billing.'
            : 'Request dismissed.');
    }
}
