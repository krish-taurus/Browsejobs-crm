@extends('layouts.app')

@section('title', 'How It Works')

@push('styles')
	<style>
		.hiw-hero { border-radius: .9rem; background: linear-gradient(115deg, #0a1220 0%, #1b3a6b 55%, #7c3aed 100%); color: #fff; position: relative; overflow: hidden; }
		.hiw-hero::after { content: ""; position: absolute; right: -70px; top: -90px; width: 300px; height: 300px; border-radius: 50%; background: rgba(255,255,255,.08); }
		.hiw-hero .inner { position: relative; z-index: 1; }

		.hiw-section-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .18em; font-weight: 700; color: var(--bs-secondary-color); }

		/* Two-system panel */
		.hiw-system { border: 2px solid var(--sys); border-radius: .75rem; height: 100%; }
		.hiw-system .head { background: color-mix(in srgb, var(--sys) 12%, transparent); border-bottom: 1px solid color-mix(in srgb, var(--sys) 30%, transparent); border-radius: .6rem .6rem 0 0; }
		.hiw-system li { padding: .3rem 0; font-size: .84rem; }

		/* Journey stepper */
		.hiw-flow { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .5rem; }
		.hiw-step { flex: 1 0 190px; border: 1px solid var(--bs-border-color); border-top: 4px solid var(--step); border-radius: .625rem; padding: .9rem; background: var(--bs-body-bg); position: relative; }
		.hiw-step .num { position: absolute; top: -12px; right: 10px; width: 24px; height: 24px; border-radius: 50%; background: var(--step); color: #fff; font-size: .72rem; font-weight: 700; display: grid; place-items: center; }
		.hiw-step .ico { width: 34px; height: 34px; border-radius: .5rem; display: grid; place-items: center; font-size: 1rem; background: color-mix(in srgb, var(--step) 15%, transparent); color: var(--step); }
		.hiw-step h6 { margin: .55rem 0 .3rem; font-size: .92rem; }
		.hiw-step p { font-size: .78rem; line-height: 1.45; margin-bottom: .4rem; color: var(--bs-secondary-color); }
		.hiw-step .then { font-size: .74rem; border-top: 1px dashed var(--bs-border-color); padding-top: .4rem; }
		.hiw-auto { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .1rem .4rem; border-radius: 999px; background: #7c3aed18; color: #7c3aed; }

		/* Student journey cards */
		.hiw-card { border: 1px solid var(--bs-border-color); border-radius: .625rem; padding: .9rem; height: 100%; }
		.hiw-card .ico { width: 36px; height: 36px; border-radius: .5rem; display: grid; place-items: center; font-size: 1.05rem; }

		/* Bridge rows */
		.hiw-bridge { border: 1px solid var(--bs-border-color); border-radius: .625rem; padding: .8rem 1rem; }
		.hiw-dir { font-family: ui-monospace, monospace; font-size: .72rem; font-weight: 700; padding: .12rem .45rem; border-radius: .3rem; background: var(--bs-secondary-bg); white-space: nowrap; }

		/* Channel pills */
		.chan { display: inline-flex; align-items: center; gap: .22rem; font-size: .7rem; font-weight: 600; padding: .12rem .45rem; border-radius: 999px; white-space: nowrap; }
		.chan-bell     { background: #1b6df018; color: #1b6df0; }
		.chan-push     { background: #7c3aed18; color: #7c3aed; }
		.chan-email    { background: #0891b218; color: #0891b2; }
		.chan-whatsapp { background: #0ba86018; color: #0ba860; }
		.chan-call     { background: #d6454518; color: #d64545; }

		.hiw-clock { border-left: 3px solid var(--bs-primary); padding-left: .9rem; }
		.hiw-clock li { font-size: .82rem; padding: .12rem 0; }

		@media print { .sidebar, .header, .btn { display: none !important; } }
	</style>
@endpush

@section('content')

	@php
		$chanMeta = [
			'bell' => ['ti-bell', 'In-app'],
			'push' => ['ti-device-mobile-message', 'Browser push'],
			'email' => ['ti-mail', 'Email'],
			'whatsapp' => ['ti-brand-whatsapp', 'WhatsApp'],
			'call' => ['ti-phone', 'AI call'],
		];
	@endphp

	<div class="hiw-hero p-4 mb-4">
		<div class="inner">
			<h4 class="text-white mb-1">How BrowseJobs works</h4>
			<p class="mb-0" style="color: rgba(255,255,255,.75); max-width: 760px;">
				Two systems, one journey. This page shows what happens from the moment someone enquires
				to the day they get certified and placed — and exactly who gets told what, when.
			</p>
		</div>
	</div>

	{{-- ------------------------- The two systems ------------------------- --}}
	<p class="hiw-section-label mb-2">1 · The two systems</p>
	<div class="row g-3 mb-2">
		<div class="col-lg-5">
			<div class="hiw-system" style="--sys: #1b6df0;">
				<div class="head p-3">
					<h6 class="mb-0"><i class="ti ti-building-store me-1" style="color:#1b6df0;"></i>CRM <span class="text-muted fw-normal fs-13">— where the staff work</span></h6>
				</div>
				<div class="p-3">
					<ul class="list-unstyled mb-0">
						<li><i class="ti ti-point-filled text-primary"></i> Leads, calls and the sales funnel</li>
						<li><i class="ti ti-point-filled text-primary"></i> HR performance and team monitoring</li>
						<li><i class="ti ti-point-filled text-primary"></i> Attendance, leave, tasks, payroll incentives</li>
						<li><i class="ti ti-point-filled text-primary"></i> Fee collection and revenue reporting</li>
						<li><i class="ti ti-point-filled text-primary"></i> Website content, pages and SEO</li>
					</ul>
				</div>
			</div>
		</div>

		<div class="col-lg-2 d-flex align-items-center justify-content-center">
			<div class="text-center py-2">
				<i class="ti ti-arrows-left-right fs-2 text-muted"></i>
				<p class="fs-12 text-muted mb-0 mt-1">connected<br>automatically</p>
			</div>
		</div>

		<div class="col-lg-5">
			<div class="hiw-system" style="--sys: #7c3aed;">
				<div class="head p-3">
					<h6 class="mb-0"><i class="ti ti-school me-1" style="color:#7c3aed;"></i>LMS <span class="text-muted fw-normal fs-13">— where the students learn</span></h6>
				</div>
				<div class="p-3">
					<ul class="list-unstyled mb-0">
						<li><i class="ti ti-point-filled" style="color:#7c3aed;"></i> Batches, live classes and recordings</li>
						<li><i class="ti ti-point-filled" style="color:#7c3aed;"></i> Assignments, grading and mock interviews</li>
						<li><i class="ti ti-point-filled" style="color:#7c3aed;"></i> Fees, payments and access control</li>
						<li><i class="ti ti-point-filled" style="color:#7c3aed;"></i> Certificates, placement and job matching</li>
						<li><i class="ti ti-point-filled" style="color:#7c3aed;"></i> The public website students first land on</li>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<div class="alert alert-light border fs-13 mb-4">
		<i class="ti ti-info-circle me-1 text-primary"></i>
		<strong>You only need the CRM.</strong> Everything about students is shown here by reading the LMS directly,
		so the numbers are always live — there is no copying, and nothing to keep in sync by hand.
	</div>

	{{-- ------------------------- The lead journey ------------------------- --}}
	<p class="hiw-section-label mb-2">2 · From enquiry to enrolment</p>
	<div class="card mb-4">
		<div class="card-body">
			<div class="hiw-flow">
				@foreach ($leadStages as $i => $stage)
					<div class="hiw-step" style="--step: {{ $stage['colour'] }};">
						<span class="num">{{ $i + 1 }}</span>
						<span class="ico"><i class="ti {{ $stage['icon'] }}"></i></span>
						<h6>
							{{ $stage['status'] }}
							@if ($stage['auto'])
								<span class="hiw-auto ms-1">auto</span>
							@endif
						</h6>
						<p>{{ $stage['what'] }}</p>
						<div class="then text-muted"><i class="ti ti-arrow-narrow-right me-1"></i>{{ $stage['then'] }}</div>
					</div>
				@endforeach
			</div>
			<p class="fs-12 text-muted mb-0 mt-2">
				<span class="hiw-auto">auto</span> means the system does it with no one clicking anything.
			</p>
		</div>
	</div>

	{{-- ------------------------ The student journey ----------------------- --}}
	<p class="hiw-section-label mb-2">3 · After they join — the student journey</p>
	<div class="row g-3 mb-4">
		@foreach ($studentStages as $i => $stage)
			<div class="col-xl-3 col-md-6">
				<div class="hiw-card">
					<div class="d-flex align-items-center gap-2">
						<span class="ico" style="background: {{ $stage['colour'] }}18; color: {{ $stage['colour'] }};">
							<i class="ti {{ $stage['icon'] }}"></i>
						</span>
						<div>
							<div class="fs-12 text-muted">Step {{ $i + 1 }}</div>
							<h6 class="mb-0 fs-14">{{ $stage['title'] }}</h6>
						</div>
					</div>
					<p class="fs-13 text-muted mb-0 mt-2">{{ $stage['body'] }}</p>
				</div>
			</div>
		@endforeach
	</div>

	{{-- ------------------------- Notifications ---------------------------- --}}
	<p class="hiw-section-label mb-2">4 · Who gets told what</p>
	<div class="card mb-4">
		<div class="card-body">
			<div class="d-flex flex-wrap gap-2 mb-3">
				<span class="text-muted fs-13 me-1">Channels:</span>
				@foreach ($chanMeta as $key => [$icon, $label])
					<span class="chan chan-{{ $key }}"><i class="ti {{ $icon }}"></i>{{ $label }}</span>
				@endforeach
			</div>

			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead>
						<tr>
							<th>What happens</th>
							<th>Who hears about it</th>
							<th>How</th>
							<th>When</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($notifications as $n)
							<tr>
								<td class="fw-medium fs-13">{{ $n['event'] }}</td>
								<td class="fs-13 text-muted">{{ $n['who'] }}</td>
								<td>
									@foreach ($n['channels'] as $c)
										<span class="chan chan-{{ $c }} me-1"><i class="ti {{ $chanMeta[$c][0] }}"></i>{{ $chanMeta[$c][1] }}</span>
									@endforeach
								</td>
								<td class="fs-13 text-muted">{{ $n['when'] }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	{{-- --------------------------- The bridges ---------------------------- --}}
	<p class="hiw-section-label mb-2">5 · How the two systems stay in step</p>
	<div class="row g-2 mb-4">
		@foreach ($bridges as $bridge)
			<div class="col-12">
				<div class="hiw-bridge d-flex align-items-start gap-3 flex-wrap">
					<span class="hiw-dir">{{ $bridge['dir'] }}</span>
					<div class="flex-grow-1" style="min-width: 240px;">
						<div class="fw-semibold fs-14">{{ $bridge['title'] }}</div>
						<div class="fs-13 text-muted">{{ $bridge['body'] }}</div>
					</div>
					<span class="badge badge-soft-primary align-self-center">{{ $bridge['every'] }}</span>
				</div>
			</div>
		@endforeach
	</div>

	{{-- ------------------------ What runs by itself ----------------------- --}}
	<p class="hiw-section-label mb-2">6 · What runs by itself</p>
	<div class="row g-3 mb-3">
		@foreach ($schedule as $slot)
			<div class="col-lg-4 col-md-6">
				<div class="hiw-card h-100">
					<h6 class="fs-14 mb-2"><i class="ti ti-clock-hour-4 me-1 text-primary"></i>{{ $slot['time'] }}</h6>
					<ul class="hiw-clock list-unstyled mb-0">
						@foreach ($slot['items'] as $item)
							<li class="text-muted">{{ $item }}</li>
						@endforeach
					</ul>
				</div>
			</div>
		@endforeach
	</div>

	<div class="alert alert-warning fs-13">
		<i class="ti ti-alert-triangle me-1"></i>
		<strong>These only run while the server is on.</strong> If the scheduled task on the server stops,
		leads stop being called, reminders stop going out and digests stop being built — everything else
		keeps working, so it can go unnoticed. Worth checking if messages seem to have gone quiet.
	</div>

	<div class="text-end">
		<button type="button" class="btn btn-light" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print / save as PDF</button>
	</div>

@endsection
