@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
	<style>
		.dash-hero { border-radius: .9rem; background: linear-gradient(115deg, #1b6df0 0%, #4d94ff 45%, #7c3aed 100%); color: #fff; position: relative; overflow: hidden; }
		.dash-hero::after { content: ""; position: absolute; right: -60px; top: -80px; width: 280px; height: 280px; border-radius: 50%; background: rgba(255, 255, 255, .10); }
		.dash-hero::before { content: ""; position: absolute; right: 90px; bottom: -110px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255, 255, 255, .07); }
		.dash-hero .hero-inner { position: relative; z-index: 1; }
		.dash-pill { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: rgba(255, 255, 255, .18); }

		.dash-stat { border: 1px solid var(--bs-border-color); border-radius: .75rem; position: relative; overflow: hidden; transition: transform .12s, box-shadow .12s; height: 100%; }
		.dash-stat::before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--stat-accent); }
		a:hover > .dash-stat { transform: translateY(-3px); box-shadow: 0 10px 26px rgba(10, 18, 32, .10); }
		.dash-stat .stat-icon { width: 42px; height: 42px; display: grid; place-items: center; border-radius: .625rem; font-size: 1.15rem; }

		.dash-lead { display: flex; align-items: center; gap: .75rem; padding: .7rem .25rem; border-bottom: 1px solid var(--bs-border-color); }
		.dash-lead:last-child { border-bottom: 0; }
		.dash-avatar { width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; font-weight: 600; font-size: .8rem; color: #fff; flex: 0 0 auto; }

		.dash-bar { height: 8px; border-radius: 4px; background: var(--bs-secondary-bg); overflow: hidden; }
		.dash-bar > span { display: block; height: 100%; }

		.dash-tile { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .35rem; padding: .85rem .5rem; border: 1px solid var(--bs-border-color); border-radius: .625rem; text-decoration: none; color: var(--bs-body-color); transition: all .12s; text-align: center; }
		.dash-tile:hover { border-color: var(--bs-primary); color: var(--bs-primary); transform: translateY(-2px); }
		.dash-tile i { font-size: 1.25rem; }
		.dash-tile span { font-size: .75rem; font-weight: 600; }
	</style>
@endpush

@section('content')

	@php
		$hour = (int) now()->format('G');
		$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
		$name = $user->first_name ?: $user->full_name;
		$palette = ['#1b6df0', '#7c3aed', '#0ba860', '#f59e0b', '#d64545', '#0891b2'];
		$accents = ['primary' => '#1b6df0', 'info' => '#0891b2', 'success' => '#0ba860', 'warning' => '#f59e0b', 'danger' => '#d64545'];
	@endphp

	<div class="dash-hero p-4 mb-3">
		<div class="hero-inner d-flex align-items-center justify-content-between gap-3 flex-wrap">
			<div>
				<h4 class="mb-1 text-white">{{ $greeting }}, {{ $name }} 👋</h4>
				<div class="d-flex align-items-center gap-2 flex-wrap">
					<span class="dash-pill"><i class="ti ti-id-badge-2"></i>{{ $roleName }}</span>
					<span class="dash-pill">
						@if ($loggedInToday)
							<i class="ti ti-circle-check"></i> Marked present today
						@else
							<i class="ti ti-alert-circle"></i> Not logged in yet today
						@endif
					</span>
					<span class="dash-pill"><i class="ti ti-calendar"></i>{{ now()->format('l, d M Y') }}</span>
				</div>
			</div>

			@if ($myUntouched > 0)
				<a href="{{ route('leads.index', ['assignment' => 'mine']) }}" class="btn btn-light fw-semibold">
					<i class="ti ti-alarm me-1 text-danger"></i>{{ $myUntouched }} lead{{ $myUntouched === 1 ? '' : 's' }} need chasing
				</a>
			@elseif ($myLeadCount > 0)
				<span class="dash-pill"><i class="ti ti-circle-check"></i>You're on top of your leads</span>
			@endif
		</div>
	</div>

	<div class="row g-3 mb-3">
		@foreach ($cards as $card)
			@php $accent = $accents[$card['color']] ?? '#1b6df0'; @endphp
			<div class="col-xl-3 col-md-6">
				<a href="{{ $card['url'] }}" class="text-decoration-none d-block h-100">
					<div class="card dash-stat mb-0" style="--stat-accent: {{ $accent }};">
						<div class="card-body ps-4 d-flex align-items-center justify-content-between gap-2">
							<div>
								<p class="text-muted mb-1 fs-13">{{ $card['label'] }}</p>
								<h3 class="mb-0" style="color: {{ $accent }};">
									{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}
								</h3>
							</div>
							<span class="stat-icon" style="background: {{ $accent }}1a; color: {{ $accent }};">
								<i class="ti {{ $card['icon'] }}"></i>
							</span>
						</div>
					</div>
				</a>
			</div>
		@endforeach
	</div>

	<div class="row g-3">
		<div class="col-lg-8">
			<div class="card h-100 mb-0">
				<div class="card-header d-flex align-items-center justify-content-between">
					<h6 class="mb-0"><i class="ti ti-user-check me-1 text-primary"></i>My Assigned Leads</h6>
					<a href="{{ route('leads.index', ['assignment' => 'mine']) }}" class="btn btn-sm btn-outline-primary">View all {{ $myLeadCount ? '('.$myLeadCount.')' : '' }}</a>
				</div>
				<div class="card-body pt-2">
					@forelse ($myLeads as $lead)
						@php
							$leadName = $lead->name ?: '(no name)';
							$initials = collect(explode(' ', trim($leadName)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
						@endphp
						<div class="dash-lead">
							<span class="dash-avatar" style="background: {{ $palette[$loop->index % count($palette)] }};">{{ $initials }}</span>
							<div class="flex-grow-1 min-w-0">
								<div class="fw-semibold text-truncate">{{ $leadName }}</div>
								<div class="fs-12 text-muted">
									<i class="ti ti-phone fs-11"></i> {{ $lead->mobile }}
									<span class="ms-2"><i class="ti ti-clock fs-11"></i> {{ $lead->created_at?->diffForHumans() }}</span>
								</div>
							</div>
							@if ($lead->status)
								<span class="badge rounded-pill" style="background: {{ $lead->status->color ?: '#6c757d' }}; color:#fff;">{{ $lead->status->name }}</span>
							@endif
							<div class="d-flex gap-1">
								@if ($lead->mobile)
									<a href="tel:{{ $lead->mobile }}" class="btn btn-sm btn-icon btn-outline-light" title="Call {{ $leadName }}"><i class="ti ti-phone"></i></a>
								@endif
								<a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-icon btn-outline-light" title="Open lead"><i class="ti ti-eye"></i></a>
							</div>
						</div>
					@empty
						<div class="text-center py-5">
							<i class="ti ti-inbox fs-1 text-muted d-block mb-2"></i>
							<p class="text-muted mb-1">No leads assigned to you yet.</p>
							<a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-primary mt-1">Browse all leads</a>
						</div>
					@endforelse
				</div>
			</div>
		</div>

		<div class="col-lg-4">
			@if ($myPipeline->isNotEmpty())
				<div class="card mb-3">
					<div class="card-header"><h6 class="mb-0"><i class="ti ti-chart-donut me-1 text-primary"></i>My pipeline</h6></div>
					<div class="card-body">
						@foreach ($myPipeline as $stage)
							@php $pct = $myLeadCount > 0 ? round($stage->total / $myLeadCount * 100) : 0; @endphp
							<div class="mb-3">
								<div class="d-flex justify-content-between fs-13 mb-1">
									<span>{{ $stage->name }}</span>
									<span class="fw-semibold">{{ $stage->total }} <span class="text-muted fs-12">({{ $pct }}%)</span></span>
								</div>
								<div class="dash-bar"><span style="width: {{ $pct }}%; background: {{ $stage->color ?: '#6c757d' }};"></span></div>
							</div>
						@endforeach
					</div>
				</div>
			@endif

			<div class="card mb-0">
				<div class="card-header"><h6 class="mb-0"><i class="ti ti-bolt me-1 text-primary"></i>Quick links</h6></div>
				<div class="card-body">
					<div class="row g-2">
						@php
							$links = [
								['My To-Do', 'ti-checklist', route('todos.index')],
								['Notes', 'ti-notes', route('notes.index')],
								['Email', 'ti-mail', route('emails.inbox')],
								['Attendance', 'ti-clock', route('attendance.index')],
								['Leave', 'ti-calendar', route('leave-requests.index')],
								['All Leads', 'ti-users', route('leads.index')],
							];
						@endphp
						@foreach ($links as [$label, $icon, $url])
							<div class="col-4">
								<a href="{{ $url }}" class="dash-tile h-100">
									<i class="ti {{ $icon }}"></i>
									<span>{{ $label }}</span>
								</a>
							</div>
						@endforeach
					</div>
				</div>
			</div>
		</div>
	</div>

@endsection
