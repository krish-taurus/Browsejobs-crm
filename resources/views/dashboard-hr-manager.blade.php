@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
	<style>
		.dash-hero { border-radius: .9rem; background: linear-gradient(115deg, #1b6df0 0%, #4d94ff 45%, #7c3aed 100%); color: #fff; position: relative; overflow: hidden; }
		.dash-hero::after { content: ""; position: absolute; right: -60px; top: -80px; width: 280px; height: 280px; border-radius: 50%; background: rgba(255, 255, 255, .10); }
		.dash-hero .hero-inner { position: relative; z-index: 1; }
		.dash-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: rgba(255, 255, 255, .18); }

		.dash-stat { border: 1px solid var(--bs-border-color); border-radius: .75rem; position: relative; overflow: hidden; transition: transform .12s, box-shadow .12s; height: 100%; }
		.dash-stat::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--stat-accent); }
		a:hover > .dash-stat { transform: translateY(-3px); box-shadow: 0 10px 26px rgba(10, 18, 32, .10); }
		.dash-stat .stat-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: .625rem; font-size: 1.15rem; }

		.team-row { display: flex; align-items: center; gap: .75rem; padding: .65rem .25rem; border-bottom: 1px solid var(--bs-border-color); }
		.team-row:last-child { border-bottom: 0; }
		.dash-avatar { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-weight: 600; font-size: .78rem; color: #fff; flex: 0 0 auto; }
		.dash-bar { height: 6px; border-radius: 3px; background: var(--bs-secondary-bg); overflow: hidden; min-width: 70px; }
		.dash-bar > span { display: block; height: 100%; }
	</style>
@endpush

@section('content')

	@php
		$hour = (int) now()->format('G');
		$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
		$name = $user->first_name ?: $user->full_name;
		$palette = ['#1b6df0', '#7c3aed', '#0ba860', '#f59e0b', '#d64545', '#0891b2'];
	@endphp

	<div class="dash-hero p-4 mb-3">
		<div class="hero-inner d-flex align-items-center justify-content-between gap-3 flex-wrap">
			<div>
				<h4 class="mb-1 text-white">{{ $greeting }}, {{ $name }} 👋</h4>
				<div class="d-flex align-items-center gap-2 flex-wrap">
					<span class="dash-pill"><i class="ti ti-id-badge-2"></i>{{ $roleName }}</span>
					<span class="dash-pill"><i class="ti ti-users"></i>Supervising {{ $callerCount }} caller{{ $callerCount === 1 ? '' : 's' }}</span>
					<span class="dash-pill">
						@if ($loggedInToday)
							<i class="ti ti-circle-check"></i> Marked present today
						@else
							<i class="ti ti-alert-circle"></i> Not logged in yet today
						@endif
					</span>
				</div>
			</div>
			<a href="{{ route('leads.performance') }}" class="btn btn-light fw-semibold">
				<i class="ti ti-chart-bar me-1"></i>Open HR Performance
			</a>
		</div>
	</div>

	<div class="row g-3 mb-3">
		@php
			$stats = [
				['Leads with the team', $assigned, 'ti-users', '#1b6df0', route('leads.index'), 'currently assigned'],
				['Not actioned >24h', $untouched, 'ti-alarm', $untouched > 0 ? '#d64545' : '#0ba860', route('leads.performance'), $untouched > 0 ? 'chase the caller' : 'team is on top of it'],
				['Waiting to be assigned', $unassigned, 'ti-inbox', $unassigned > 0 ? '#f59e0b' : '#0ba860', route('leads.index', ['assignment' => 'unassigned']), $unassigned > 0 ? 'needs handing out' : 'nothing queued'],
				['Joined this month', $joinedThisMonth, 'ti-trophy', '#7c3aed', route('leads.index'), 'team conversions'],
			];
		@endphp
		@foreach ($stats as [$label, $value, $icon, $accent, $url, $hint])
			<div class="col-xl-3 col-md-6">
				<a href="{{ $url }}" class="text-decoration-none d-block h-100">
					<div class="card dash-stat mb-0" style="--stat-accent: {{ $accent }};">
						<div class="card-body ps-4">
							<div class="d-flex align-items-start justify-content-between gap-2">
								<div>
									<p class="text-muted mb-1 fs-13">{{ $label }}</p>
									<h3 class="mb-0" style="color: {{ $accent }};">{{ number_format($value) }}</h3>
								</div>
								<span class="stat-icon" style="background: {{ $accent }}1a; color: {{ $accent }};"><i class="ti {{ $icon }}"></i></span>
							</div>
							<p class="fs-12 text-muted mb-0 mt-2">{{ $hint }}</p>
						</div>
					</div>
				</a>
			</div>
		@endforeach
	</div>

	<div class="row g-3">
		<div class="col-lg-7">
			<div class="card h-100 mb-0">
				<div class="card-header d-flex align-items-center justify-content-between">
					<h6 class="mb-0"><i class="ti ti-headset me-1 text-primary"></i>My calling team</h6>
					<a href="{{ route('leads.performance') }}" class="btn btn-sm btn-outline-primary">Full board</a>
				</div>
				<div class="card-body pt-2">
					@forelse ($team as $member)
						@php
							$memberName = $member->user->full_name ?: $member->user->first_name;
							$initials = collect(explode(' ', trim($memberName)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
							$pct = $member->done_pct;
							$barColor = $pct === null ? '#adb5bd' : ($pct >= 80 ? '#0ba860' : ($pct >= 50 ? '#f59e0b' : '#d64545'));
						@endphp
						<div class="team-row">
							<span class="dash-avatar" style="background: {{ $palette[$loop->index % count($palette)] }};">{{ $initials }}</span>
							<div class="flex-grow-1 min-w-0">
								<div class="fw-semibold text-truncate">{{ $memberName }}</div>
								<div class="fs-12 text-muted">{{ $member->assigned }} assigned · {{ $member->joined }} joined</div>
							</div>
							<div style="width: 96px;">
								@if ($pct === null)
									<span class="fs-12 text-muted">no leads</span>
								@else
									<div class="dash-bar"><span style="width: {{ $pct }}%; background: {{ $barColor }};"></span></div>
									<div class="fs-12 mt-1" style="color: {{ $barColor }};">{{ $pct }}% actioned</div>
								@endif
							</div>
							@if ($member->pending > 0)
								<span class="badge bg-danger-transparent text-danger" title="Assigned but not yet actioned">{{ $member->pending }} pending</span>
							@else
								<span class="badge bg-success-transparent text-success">clear</span>
							@endif
							<a href="{{ route('leads.index', ['assigned_user_id' => $member->user->id]) }}" class="btn btn-sm btn-icon btn-outline-light" title="See {{ $memberName }}'s leads"><i class="ti ti-eye"></i></a>
						</div>
					@empty
						<p class="text-muted text-center py-4 mb-0">No active callers in the team yet.</p>
					@endforelse
				</div>
			</div>
		</div>

		<div class="col-lg-5">
			<div class="card mb-3">
				<div class="card-header d-flex align-items-center justify-content-between">
					<h6 class="mb-0"><i class="ti ti-inbox me-1 text-warning"></i>Waiting to be assigned</h6>
					@if ($unassigned > 0)
						<a href="{{ route('leads.index', ['assignment' => 'unassigned']) }}" class="btn btn-sm btn-outline-primary">Assign</a>
					@endif
				</div>
				<div class="card-body pt-2">
					@forelse ($unassignedLeads as $lead)
						<div class="team-row">
							<div class="flex-grow-1 min-w-0">
								<div class="fw-semibold text-truncate">{{ $lead->name ?: '(no name)' }}</div>
								<div class="fs-12 text-muted">{{ $lead->mobile }} · {{ $lead->created_at?->diffForHumans() }}</div>
							</div>
							@if ($lead->status)
								<span class="badge rounded-pill" style="background: {{ $lead->status->color ?: '#6c757d' }};color:#fff;">{{ $lead->status->name }}</span>
							@endif
							<a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-icon btn-outline-light"><i class="ti ti-eye"></i></a>
						</div>
					@empty
						<div class="text-center py-4">
							<i class="ti ti-circle-check fs-2 text-success d-block mb-1"></i>
							<p class="text-muted mb-0 fs-13">Every lead is with a caller.</p>
						</div>
					@endforelse
				</div>
			</div>

			<div class="card mb-0">
				<div class="card-header"><h6 class="mb-0"><i class="ti ti-calendar-off me-1 text-primary"></i>On leave today</h6></div>
				<div class="card-body pt-2">
					@forelse ($onLeaveToday as $leave)
						<div class="team-row">
							<div class="flex-grow-1">
								<div class="fw-semibold fs-13">{{ $leave->user->full_name ?? '—' }}</div>
								<div class="fs-12 text-muted">{{ $leave->leaveType->name ?? 'Leave' }}</div>
							</div>
						</div>
					@empty
						<p class="text-muted mb-0 fs-13 text-center py-3">Everyone is in today.</p>
					@endforelse
				</div>
			</div>
		</div>
	</div>

@endsection
