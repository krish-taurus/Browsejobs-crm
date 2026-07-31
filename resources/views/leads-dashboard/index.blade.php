@extends('layouts.app')

@section('title', 'Leads Dashboard')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Leads Dashboard</h4>
		<span class="text-muted fs-13"><i class="ti ti-route me-1"></i>How every job lead flows through your team — live</span>
	</div>

	<style>
		.flow-canvas {
			background: #f8fafd;
			background-image: radial-gradient(rgba(15, 23, 42, .07) 1px, transparent 1px);
			background-size: 18px 18px;
			border-radius: 16px;
			padding: 42px 28px;
			overflow-x: auto;
		}
		.flow-lane { display: flex; align-items: center; gap: 0; min-width: 980px; }
		.flow-node {
			background: #ffffff;
			border: 1px solid #e2e8f0;
			border-radius: 14px;
			padding: 16px 18px;
			min-width: 200px;
			color: #0f172a;
			box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
			position: relative;
		}
		.flow-node .fn-icon {
			width: 38px; height: 38px; border-radius: 10px;
			display: flex; align-items: center; justify-content: center;
			font-size: 19px; margin-bottom: 10px;
		}
		.flow-node .fn-title { font-weight: 600; font-size: 14px; }
		.flow-node .fn-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
		.flow-node .fn-count { font-size: 26px; font-weight: 700; margin-top: 8px; }
		.flow-node .fn-meta { color: #64748b; font-size: 11px; margin-top: 4px; }
		.flow-wire { flex: 0 0 64px; height: 2px; position: relative; }
		.flow-wire::before {
			content: ""; position: absolute; inset: 0;
			border-top: 2px dashed #b6c2d6;
		}
		.flow-wire::after {
			content: "▶"; position: absolute; right: -4px; top: -9px;
			color: #94a3b8; font-size: 10px;
		}
		.flow-wire .fw-label {
			position: absolute; top: -22px; left: 50%; transform: translateX(-50%);
			color: #94a3b8; font-size: 10px; white-space: nowrap; letter-spacing: .04em;
		}
		.flow-branch { display: flex; flex-direction: column; gap: 12px; }
		.flow-person {
			background: #f8fafc;
			border: 1px solid #e2e8f0;
			border-radius: 12px;
			padding: 10px 14px;
			color: #0f172a;
			min-width: 210px;
			display: flex; align-items: center; gap: 10px;
		}
		.flow-person .fp-avatar {
			width: 32px; height: 32px; border-radius: 50%;
			background: #e8eefb; color: #2b5fd1;
			display: flex; align-items: center; justify-content: center;
			font-weight: 700; font-size: 13px; flex: 0 0 32px;
		}
		.flow-person .fp-name { font-size: 13px; font-weight: 600; line-height: 1.1; }
		.flow-person .fp-sub { font-size: 11px; color: #64748b; }
		.flow-person .fp-badge { margin-left: auto; text-align: right; }
		.flow-person .fp-badge b { font-size: 16px; color: #2563eb; display: block; line-height: 1; }
		.flow-person .fp-badge span { font-size: 10px; color: #64748b; }
		.flow-outcome { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
		.flow-pill {
			border-radius: 999px; padding: 6px 14px; font-size: 12px; font-weight: 600;
			background: #ffffff; border: 1px solid #dde4ee; color: #334155;
		}
		.flow-pill b { margin-left: 6px; }
	</style>

	<style>
		.flow-filter { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 14px 18px 0; }
		.flow-filter .ff-pill {
			border-radius: 999px; padding: 6px 16px; font-size: 12px; font-weight: 600;
			background: #ffffff; border: 1px solid #dde4ee; color: #64748b;
			text-decoration: none; transition: all .15s;
		}
		.flow-filter .ff-pill:hover { color: #0f172a; border-color: #b6c2d6; }
		.flow-filter .ff-pill.active { background: var(--bs-primary, #e41f07); border-color: var(--bs-primary, #e41f07); color: #ffffff; }
		.flow-filter select {
			background: #ffffff; border: 1px solid #dde4ee; color: #334155;
			border-radius: 999px; padding: 6px 14px; font-size: 12px; font-weight: 600; outline: none;
		}
	</style>

	<div class="flow-canvas mb-3" style="padding-top: 14px;">
		<div class="flow-filter mb-4">
			@foreach (['all' => 'All time', '30d' => 'Last 30 days', '7d' => 'Last 7 days', 'today' => 'Today'] as $value => $label)
				<a class="ff-pill {{ $period === $value ? 'active' : '' }}"
					href="{{ route('leads-dashboard.index', array_filter(['period' => $value, 'source' => $source])) }}">{{ $label }}</a>
			@endforeach
			<form method="GET" action="{{ route('leads-dashboard.index') }}" class="d-inline-flex align-items-center gap-2 ms-2">
				<input type="hidden" name="period" value="{{ $period }}">
				<select name="source" onchange="this.form.submit()">
					<option value="">All sources</option>
					@foreach ($sourceOptions as $option)
						<option value="{{ $option }}" {{ $source === $option ? 'selected' : '' }}>{{ $option }}</option>
					@endforeach
				</select>
			</form>
			@if ($period !== 'all' || $source !== '')
				<a class="ff-pill" href="{{ route('leads-dashboard.index') }}" style="color:#dc2626;">✕ Clear</a>
			@endif
		</div>
		<div class="flow-lane">

			<!-- 1. Lead arrives -->
			<div class="flow-node">
				<div class="fn-icon" style="background:#e8f1fd;">⚡</div>
				<div class="fn-title">Job lead arrives</div>
				<div class="fn-sub">Website · Ads · Job portal</div>
				<div class="fn-count">{{ $today }}</div>
				<div class="fn-meta">today · {{ $week }} this week · {{ $total }} all time</div>
				<div class="fn-meta">
					@foreach ($sources as $source => $n)
						{{ $source ?: 'direct' }} ({{ $n }}){{ $loop->last ? '' : ' · ' }}
					@endforeach
				</div>
			</div>

			<div class="flow-wire"><span class="fw-label">AUTO-ASSIGN</span></div>

			<!-- 2. HR Manager routes -->
			<div class="flow-node">
				<div class="fn-icon" style="background:#fdf3e0;">🧭</div>
				<div class="fn-title">HR Manager routes it</div>
				<div class="fn-sub">Reviews &amp; assigns the right HR</div>
				<div class="fn-count">{{ $assigned }}</div>
				<div class="fn-meta">assigned · <span style="color:{{ $unassigned > 0 ? '#dc2626' : '#16a34a' }};">{{ $unassigned }} waiting</span></div>
				<div class="flow-branch mt-2">
					@foreach ($managers as $manager)
						<div class="flow-person" style="min-width:auto;">
							<div class="fp-avatar">{{ strtoupper(mb_substr($manager->first_name ?? $manager->full_name ?? 'M', 0, 1)) }}</div>
							<div>
								<div class="fp-name">{{ $manager->full_name ?? trim(($manager->first_name ?? '').' '.($manager->last_name ?? '')) }}</div>
								<div class="fp-sub">{{ $manager->role?->role_name }}</div>
							</div>
							<div class="fp-badge"><b>{{ $manager->routed_count }}</b><span>routed</span></div>
						</div>
					@endforeach
				</div>
			</div>

			<div class="flow-wire"><span class="fw-label">ASSIGNED · NOTIFIED 📲</span></div>

			<!-- 3. HR team follows up -->
			<div class="flow-node">
				<div class="fn-icon" style="background:#e6f6fb;">📞</div>
				<div class="fn-title">HR follows up</div>
				<div class="fn-sub">Calls · WhatsApp · AI-call assist</div>
				<div class="flow-branch mt-2">
					@forelse ($hrs as $hr)
						<div class="flow-person">
							<div class="fp-avatar">{{ strtoupper(mb_substr($hr->first_name ?? $hr->full_name ?? 'H', 0, 1)) }}</div>
							<div>
								<div class="fp-name">{{ $hr->full_name ?? trim(($hr->first_name ?? '').' '.($hr->last_name ?? '')) }}</div>
								<div class="fp-sub">{{ $hr->active_count }} active · {{ $hr->joined_count }} joined</div>
							</div>
							<div class="fp-badge"><b>{{ $hr->total_count }}</b><span>leads</span></div>
						</div>
					@empty
						<div class="fp-sub" class="fp-sub">No HR users yet — add them under HRM.</div>
					@endforelse
				</div>
			</div>

			<div class="flow-wire"><span class="fw-label">OUTCOME</span></div>

			<!-- 4. Outcomes -->
			<div class="flow-node">
				<div class="fn-icon" style="background:#e7f8ee;">🎯</div>
				<div class="fn-title">Where leads stand</div>
				<div class="fn-sub">Live status across all leads</div>
				<div class="flow-outcome">
					@foreach (['New' => '#2563eb', 'Interested' => '#16a34a', 'Follow-up' => '#d97706', 'AI Call Running' => '#7c3aed', 'Joined' => '#15803d', 'Not Interested' => '#dc2626', 'Lost' => '#64748b', 'Invalid Number' => '#64748b'] as $status => $color)
						@if (($outcomes[$status] ?? 0) > 0)
							<span class="flow-pill" style="color:{{ $color }};">{{ $status }}<b>{{ $outcomes[$status] }}</b></span>
						@endif
					@endforeach
					@if ($outcomes->isEmpty())
						<span class="flow-pill">No leads yet</span>
					@endif
				</div>
			</div>

		</div>
	</div>

	<p class="text-muted fs-12">
		⚡ New leads land from your website, ads and the job portal → the HR Manager sees them instantly and routes each to an HR
		→ the HR gets a WhatsApp + email + push alert and follows up (with AI-call assist) → every lead ends in a status above.
		Unassigned leads glow red on the manager node — that's the number to keep at zero.
	</p>

@endsection
