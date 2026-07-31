<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Visual leads dashboard (founder request, Jul 2026): the assignment pipeline
 * drawn as a node flow — lead arrives → HR Manager routes it → HR follows up
 * → outcome — with live counts on every node instead of tables. The period +
 * source filters re-scope every node's numbers to the chosen slice.
 */
class LeadsDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->input('period', 'all');
        $source = $request->input('source', '');

        $scope = function () use ($period, $source): Builder {
            $q = Lead::query();
            match ($period) {
                'today' => $q->whereDate('leads.created_at', today()),
                '7d' => $q->where('leads.created_at', '>=', now()->subDays(7)),
                '30d' => $q->where('leads.created_at', '>=', now()->subDays(30)),
                default => null,
            };
            if ($source !== '') {
                $q->where('leads.source', $source);
            }

            return $q;
        };

        $total = $scope()->count();
        $today = $scope()->whereDate('leads.created_at', today())->count();
        $week = $scope()->where('leads.created_at', '>=', now()->subDays(7))->count();
        $unassigned = $scope()->whereNull('assigned_to_user_id')->count();
        $assigned = $total - $unassigned;

        $sources = $scope()->select('source', DB::raw('count(*) as n'))
            ->groupBy('source')
            ->orderByDesc('n')
            ->limit(4)
            ->pluck('n', 'source');

        $managers = User::whereHas('role', fn ($q) => $q->whereIn('role_code', ['HR_MANAGER', 'HR_TEAM_LEAD', 'HR_ADMIN']))
            ->get()
            ->map(function (User $u) use ($scope) {
                $u->routed_count = $scope()->where('assigned_by_user_id', $u->id)->count();

                return $u;
            });

        $hrs = User::whereHas('role', fn ($q) => $q->where('role_code', 'HR'))
            ->get()
            ->map(function (User $u) use ($scope) {
                $base = fn () => $scope()->where('assigned_to_user_id', $u->id);
                $u->total_count = $base()->count();
                $u->active_count = $base()->whereHas('status', fn ($s) => $s->whereIn('name', ['New', 'Interested', 'Follow-up', 'AI Call Running']))->count();
                $u->joined_count = $base()->whereHas('status', fn ($s) => $s->where('name', 'Joined'))->count();

                return $u;
            });

        $outcomes = $scope()->join('lead_statuses', 'lead_statuses.id', '=', 'leads.current_status_id')
            ->select('lead_statuses.name', DB::raw('count(*) as n'))
            ->groupBy('lead_statuses.name')
            ->pluck('n', 'name');

        $sourceOptions = Lead::select('source')->distinct()->orderBy('source')->pluck('source')->filter()->values();

        return view('leads-dashboard.index', compact(
            'total', 'today', 'week', 'unassigned', 'assigned', 'sources', 'managers', 'hrs', 'outcomes',
            'period', 'source', 'sourceOptions'
        ));
    }
}
