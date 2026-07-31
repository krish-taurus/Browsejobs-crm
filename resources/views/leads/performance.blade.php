@extends('layouts.app')

@section('title', 'HR Performance')

@push('styles')
	<style>
		/* Scoped to this page — the rest of the CRM keeps the stock table styling. */
		.perf-kpi { border: 1px solid var(--bs-border-color); border-radius: .75rem; overflow: hidden; position: relative; }
		.perf-kpi::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--kpi-accent, var(--bs-primary)); }
		.perf-kpi .kpi-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: .625rem; font-size: 1.1rem; }

		.perf-table { border-collapse: separate; border-spacing: 0 .5rem; }
		.perf-table > tbody > tr { background: var(--bs-body-bg); box-shadow: 0 1px 2px rgba(10, 18, 32, .06); }
		.perf-table > tbody > tr > td { border: 0; border-top: 1px solid var(--bs-border-color); border-bottom: 1px solid var(--bs-border-color); vertical-align: middle; padding: .85rem .75rem; }
		.perf-table > tbody > tr > td:first-child { border-left: 1px solid var(--bs-border-color); border-radius: .625rem 0 0 .625rem; }
		.perf-table > tbody > tr > td:last-child { border-right: 1px solid var(--bs-border-color); border-radius: 0 .625rem .625rem 0; }
		.perf-table > thead > tr > th { border: 0; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); padding: 0 .75rem .25rem; white-space: nowrap; }
		.perf-table > tbody > tr:hover { box-shadow: 0 4px 14px rgba(10, 18, 32, .10); }

		.perf-rank { width: 26px; height: 26px; display: grid; place-items: center; border-radius: 50%; font-size: .72rem; font-weight: 700; background: var(--bs-secondary-bg); color: var(--bs-secondary-color); }
		.perf-rank.is-top { background: linear-gradient(135deg, #f5c451, #e0a217); color: #4a3200; }

		.perf-avatar { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 600; font-size: .8rem; color: #fff; flex: 0 0 auto; }

		.perf-bar { height: 5px; border-radius: 3px; background: var(--bs-secondary-bg); overflow: hidden; min-width: 68px; }
		.perf-bar > span { display: block; height: 100%; border-radius: 3px; }

		.perf-metric { font-size: .95rem; font-weight: 600; line-height: 1.1; }
		.perf-sub { font-size: .72rem; color: var(--bs-secondary-color); }
		.perf-chip { display: inline-flex; align-items: center; gap: .25rem; padding: .15rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }

		th[data-sort] { cursor: pointer; user-select: none; }
		th[data-sort]:hover { color: var(--bs-primary); }
		th[data-sort] .ti { opacity: .35; font-size: .8rem; }
		th[data-sort].sorted .ti { opacity: 1; color: var(--bs-primary); }
	</style>
@endpush

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-0">HR Performance</h4>
			<nav class="fs-13 text-muted">
				<a href="{{ url('/') }}" class="text-muted">Home</a> &gt; <a href="{{ route('leads.index') }}" class="text-muted">Leads</a> &gt; <span>HR Performance</span>
			</nav>
		</div>
		<a href="{{ route('leads.index') }}" class="btn btn-outline-light"><i class="ti ti-arrow-left me-1"></i> Back to Leads</a>
	</div>

	@php
		$totalAssigned = $rows->sum('assigned');
		$totalFollowedUp = $rows->sum('followed_up');
		$totalOverdue = $rows->sum('overdue');
		$totalJoined = $rows->sum('joined');
		$followRate = $totalAssigned ? round($totalFollowedUp / $totalAssigned * 100) : 0;
		$convRate = $totalAssigned ? round($totalJoined / $totalAssigned * 100) : 0;

		// Rank by conversions first, then follow-up rate — the two things the
		// manager actually manages on.
		$ranked = $rows->sortByDesc(fn ($r) => [$r->joined, $r->assigned ? $r->followed_up / $r->assigned : 0])->values();
		$palette = ['#1b6df0', '#7c3aed', '#0ba860', '#f59e0b', '#d64545', '#0891b2', '#db2777', '#65a30d'];
	@endphp

	<div class="row g-3 mb-3">
		@php
			$kpis = [
				['Leads assigned', $totalAssigned, null, 'ti-users', '#1b6df0', 'across '.$rows->count().' HR staff'],
				['Followed up', $totalFollowedUp, $followRate.'%', 'ti-phone-check', '#0ba860', $totalAssigned - $totalFollowedUp.' still untouched'],
				['Overdue >24h', $totalOverdue, null, 'ti-alarm', $totalOverdue > 0 ? '#d64545' : '#0ba860', $totalOverdue > 0 ? 'needs chasing today' : 'nothing overdue'],
				['Joined', $totalJoined, $convRate.'%', 'ti-trophy', '#7c3aed', 'conversion rate'],
			];
		@endphp
		@foreach ($kpis as [$label, $value, $suffix, $icon, $accent, $hint])
			<div class="col-6 col-lg-3">
				<div class="card mb-0 perf-kpi h-100" style="--kpi-accent: {{ $accent }};">
					<div class="card-body ps-4">
						<div class="d-flex align-items-start justify-content-between gap-2">
							<div>
								<p class="text-muted mb-1 fs-13">{{ $label }}</p>
								<h3 class="mb-0 d-flex align-items-baseline gap-2" style="color: {{ $accent }};">
									{{ $value }}
									@if ($suffix)
										<span class="fs-13 fw-medium text-muted">{{ $suffix }}</span>
									@endif
								</h3>
							</div>
							<span class="kpi-icon" style="background: {{ $accent }}1a; color: {{ $accent }};"><i class="ti {{ $icon }}"></i></span>
						</div>
						<p class="perf-sub mb-0 mt-2">{{ $hint }}</p>
					</div>
				</div>
			</div>
		@endforeach
	</div>

	<div class="card">
		<div class="card-body">
			<div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
				<h6 class="mb-0"><i class="ti ti-list-numbers me-1 text-primary"></i>Team leaderboard</h6>
				<div class="d-flex gap-2 flex-wrap">
					<input type="search" id="perf-search" class="form-control form-control-sm" style="width: 190px;" placeholder="Find an HR…">
					<form method="GET" action="{{ route('leads.performance') }}" class="d-flex gap-2">
						<input type="date" name="from" class="form-control form-control-sm" style="width: 150px;" value="{{ $from }}" title="Assigned from">
						<input type="date" name="to" class="form-control form-control-sm" style="width: 150px;" value="{{ $to }}" title="Assigned to">
						<button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-filter"></i></button>
						@if ($from || $to)
							<a href="{{ route('leads.performance') }}" class="btn btn-sm btn-outline-light">Reset</a>
						@endif
					</form>
				</div>
			</div>

			<div class="table-responsive">
				<table class="table perf-table align-middle mb-0" id="perf-table">
					<thead>
						<tr>
							<th style="width:52px;">#</th>
							<th data-sort="text">HR <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num" class="text-center">Assigned <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num" style="min-width:150px;">Follow-up rate <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num" class="text-center">Pending <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num" class="text-center">Overdue <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num" class="text-center">Calls <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num" class="text-center">Pipeline <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num" class="text-center">Joined <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num">Avg response <i class="ti ti-arrows-sort"></i></th>
							<th data-sort="num">Last activity <i class="ti ti-arrows-sort"></i></th>
							<th class="text-end">Leads</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($ranked as $i => $row)
							@php
								$pct = $row->assigned > 0 ? (int) round($row->followed_up / $row->assigned * 100) : null;
								$barColor = $pct === null ? '#adb5bd' : ($pct >= 80 ? '#0ba860' : ($pct >= 50 ? '#f59e0b' : '#d64545'));
								$mins = $row->avg_response_minutes;
								$respColor = $mins === null ? 'text-muted' : ($mins <= 60 ? 'text-success' : ($mins <= 240 ? 'text-warning' : 'text-danger'));
								$name = $row->user->full_name ?: $row->user->first_name;
								$initials = collect(explode(' ', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
								$accent = $palette[$i % count($palette)];
							@endphp
							<tr data-name="{{ mb_strtolower($name) }}">
								<td>
									<span class="perf-rank {{ $i === 0 && $row->joined > 0 ? 'is-top' : '' }}">
										{{ $i === 0 && $row->joined > 0 ? '★' : $i + 1 }}
									</span>
								</td>
								<td data-value="{{ mb_strtolower($name) }}">
									<div class="d-flex align-items-center gap-2">
										<span class="perf-avatar" style="background: {{ $accent }};">{{ $initials }}</span>
										<div class="min-w-0">
											<div class="fw-semibold text-truncate">{{ $name }}</div>
											<div class="perf-sub">{{ $row->user->role->role_name ?? '—' }}</div>
										</div>
									</div>
								</td>
								<td class="text-center" data-value="{{ $row->assigned }}">
									<span class="perf-metric">{{ $row->assigned }}</span>
								</td>
								<td data-value="{{ $pct ?? -1 }}">
									@if ($pct === null)
										<span class="text-muted">—</span>
									@else
										<div class="d-flex align-items-center gap-2">
											<div class="perf-bar flex-grow-1"><span style="width: {{ $pct }}%; background: {{ $barColor }};"></span></div>
											<span class="perf-metric" style="color: {{ $barColor }};">{{ $pct }}%</span>
										</div>
										<div class="perf-sub">{{ $row->followed_up }} of {{ $row->assigned }} actioned</div>
									@endif
								</td>
								<td class="text-center" data-value="{{ $row->pending }}">
									<span class="perf-metric {{ $row->pending > 0 ? 'text-warning' : 'text-muted' }}">{{ $row->pending }}</span>
								</td>
								<td class="text-center" data-value="{{ $row->overdue }}">
									@if ($row->overdue > 0)
										<span class="perf-chip" style="background: #d6454518; color: #d64545;"><i class="ti ti-alert-triangle"></i>{{ $row->overdue }}</span>
									@else
										<span class="perf-chip" style="background: #0ba86018; color: #0ba860;"><i class="ti ti-check"></i>0</span>
									@endif
								</td>
								<td class="text-center" data-value="{{ $row->calls }}">
									<span class="perf-metric">{{ $row->calls }}</span>
								</td>
								<td class="text-center" data-value="{{ $row->interested }}">
									<span class="perf-metric text-info">{{ $row->interested }}</span>
									<div class="perf-sub">interested</div>
								</td>
								<td class="text-center" data-value="{{ $row->joined }}">
									@if ($row->joined > 0)
										<span class="perf-chip" style="background: #7c3aed18; color: #7c3aed;"><i class="ti ti-trophy"></i>{{ $row->joined }}</span>
									@else
										<span class="text-muted">0</span>
									@endif
								</td>
								<td data-value="{{ $mins ?? 999999 }}">
									@if ($mins === null)
										<span class="text-muted">—</span>
									@else
										<span class="perf-metric {{ $respColor }}">
											{{ $mins < 60 ? $mins.' min' : round($mins / 60, 1).' h' }}
										</span>
										<div class="perf-sub">to first action</div>
									@endif
								</td>
								<td data-value="{{ $row->last_activity?->timestamp ?? 0 }}">
									@if ($row->last_activity)
										<span class="fs-13">{{ $row->last_activity->diffForHumans() }}</span>
										<div class="perf-sub">{{ $row->last_activity->format('d M, H:i') }}</div>
									@else
										<span class="text-muted fs-13">No activity yet</span>
									@endif
								</td>
								<td class="text-end">
									<a href="{{ route('leads.index', ['assigned_user_id' => $row->user->id]) }}" class="btn btn-sm btn-icon btn-outline-light" title="View {{ $name }}'s leads">
										<i class="ti ti-eye"></i>
									</a>
								</td>
							</tr>
						@empty
							<tr><td colspan="12" class="text-center py-4 text-muted">No active HR users found.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<p class="perf-sub mt-3 mb-0">
				<i class="ti ti-info-circle me-1"></i>A lead counts as <strong>followed up</strong> when the assigned HR logged a call on it or changed its status after the assignment.
				<strong>Overdue</strong> = assigned more than 24 hours ago, still open, and no action by the HR yet. Ranked by conversions, then follow-up rate.
			</p>
		</div>
	</div>

@endsection

@push('scripts')
	<script>
		(function () {
			var table = document.getElementById('perf-table');
			if (!table) { return; }
			var tbody = table.querySelector('tbody');

			// Filter by name.
			var search = document.getElementById('perf-search');
			search?.addEventListener('input', function () {
				var term = this.value.trim().toLowerCase();
				tbody.querySelectorAll('tr[data-name]').forEach(function (tr) {
					tr.style.display = !term || tr.dataset.name.includes(term) ? '' : 'none';
				});
			});

			// Click a header to sort. data-value carries the sortable value so
			// "2.2 h" and "23 min" order correctly rather than alphabetically.
			table.querySelectorAll('th[data-sort]').forEach(function (th, i) {
				var index = Array.prototype.indexOf.call(th.parentNode.children, th);
				th.addEventListener('click', function () {
					var numeric = th.dataset.sort === 'num';
					var asc = th.classList.contains('sorted') ? !(th.dataset.asc === 'true') : !numeric;

					table.querySelectorAll('th[data-sort]').forEach(function (o) { o.classList.remove('sorted'); });
					th.classList.add('sorted');
					th.dataset.asc = asc;

					var rows = Array.from(tbody.querySelectorAll('tr[data-name]'));
					rows.sort(function (a, b) {
						var av = a.children[index]?.dataset.value ?? '';
						var bv = b.children[index]?.dataset.value ?? '';
						var cmp = numeric ? (parseFloat(av) || 0) - (parseFloat(bv) || 0) : String(av).localeCompare(String(bv));
						return asc ? cmp : -cmp;
					});
					rows.forEach(function (r) { tbody.appendChild(r); });

					// Rank column follows the new order.
					rows.forEach(function (r, n) {
						var badge = r.querySelector('.perf-rank');
						if (badge) { badge.textContent = n + 1; badge.classList.remove('is-top'); }
					});
				});
			});
		})();
	</script>
@endpush
