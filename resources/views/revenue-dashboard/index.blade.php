@extends('layouts.app')

@section('title', 'Revenue Summary')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Revenue Summary</h4>
		<span class="text-muted fs-13"><i class="ti ti-currency-rupee me-1"></i>Course fees + store, live from the LMS ledger</span>
	</div>

	<style>
		.rev-canvas {
			background: #f8fafd;
			background-image: radial-gradient(rgba(15, 23, 42, .07) 1px, transparent 1px);
			background-size: 18px 18px;
			border: 1px solid #e5eaf2;
			border-radius: 16px;
			padding: 14px 24px 34px;
			overflow-x: auto;
		}
		.rev-filter { display: flex; gap: 8px; padding: 8px 0 22px; }
		.rev-filter .ff-pill {
			border-radius: 999px; padding: 6px 16px; font-size: 12px; font-weight: 600;
			background: #ffffff; border: 1px solid #dde4ee; color: #64748b; text-decoration: none;
		}
		.rev-filter .ff-pill:hover { color: #0f172a; border-color: #b6c2d6; }
		.rev-filter .ff-pill.active { background: var(--bs-primary, #e41f07); border-color: var(--bs-primary, #e41f07); color: #fff; }
		.rev-lane { display: flex; align-items: center; min-width: 940px; }
		.rev-node {
			background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px;
			padding: 16px 18px; min-width: 190px; color: #0f172a;
			box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
		}
		.rev-node .rn-icon { font-size: 20px; margin-bottom: 8px; display: block; }
		.rev-node .rn-title { font-weight: 600; font-size: 13px; }
		.rev-node .rn-amount { font-size: 22px; font-weight: 700; margin-top: 6px; white-space: nowrap; }
		.rev-node .rn-meta { color: #64748b; font-size: 11px; margin-top: 4px; }
		.rev-wire { flex: 0 0 56px; height: 2px; position: relative; }
		.rev-wire::before { content: ""; position: absolute; inset: 0; border-top: 2px dashed #b6c2d6; }
		.rev-wire::after { content: "▶"; position: absolute; right: -4px; top: -9px; color: #94a3b8; font-size: 10px; }
		.rev-wire .rw-label { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); color: #94a3b8; font-size: 10px; white-space: nowrap; }
		.rev-stack { display: flex; flex-direction: column; gap: 14px; }
		.rev-bars { display: flex; align-items: flex-end; gap: 18px; height: 150px; padding: 26px 6px 0; }
		.rev-bar { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; }
		.rev-bar .rb-fill { width: 100%; max-width: 54px; border-radius: 8px 8px 3px 3px; background: linear-gradient(180deg, #5a8dff, #2b5fd1); min-height: 3px; position: relative; }
		.rev-bar .rb-amount { color: #334155; font-size: 11px; font-weight: 600; white-space: nowrap; }
		.rev-bar .rb-label { color: #64748b; font-size: 11px; }
		[data-theme="dark"] .rev-canvas, [data-bs-theme="dark"] .rev-canvas { background: #0d0d16; border-color: #262636; background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px); }
		[data-theme="dark"] .rev-node, [data-bs-theme="dark"] .rev-node { background: #1b1b28; border-color: #32324a; color: #e8e8f2; }
		[data-theme="dark"] .rev-node .rn-meta, [data-bs-theme="dark"] .rev-node .rn-meta { color: #9a9ab5; }
		[data-theme="dark"] .rev-filter .ff-pill, [data-bs-theme="dark"] .rev-filter .ff-pill { background: #1b1b28; border-color: #32324a; color: #9a9ab5; }
		[data-theme="dark"] .rev-bar .rb-amount, [data-bs-theme="dark"] .rev-bar .rb-amount { color: #cfcfe4; }
	</style>

	@php $inr = fn (int $paise) => '₹' . number_format($paise / 100); @endphp

	<div class="rev-canvas mb-3">
		<div class="rev-filter">
			@foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'all' => 'All time'] as $value => $label)
				<a class="ff-pill {{ $period === $value ? 'active' : '' }}" href="{{ route('revenue-dashboard.index', ['period' => $value]) }}">{{ $label }}</a>
			@endforeach
		</div>

		<div class="rev-lane">
			<div class="rev-stack">
				<div class="rev-node">
					<span class="rn-icon">🎓</span>
					<div class="rn-title">Course fees</div>
					<div class="rn-amount" style="color:#2563eb;">{{ $inr($fees) }}</div>
					<div class="rn-meta">{{ $paymentCount }} EMI payment{{ $paymentCount === 1 ? '' : 's' }} captured</div>
				</div>
				<div class="rev-node">
					<span class="rn-icon">🛒</span>
					<div class="rn-title">Store packs</div>
					<div class="rn-amount" style="color:#7c3aed;">{{ $inr($store) }}</div>
					<div class="rn-meta">{{ $storeCount }} purchase{{ $storeCount === 1 ? '' : 's' }} · voice, CV, mentor, kits</div>
				</div>
			</div>

			<div class="rev-wire"><span class="rw-label">RAZORPAY</span></div>

			<div class="rev-node">
				<span class="rn-icon">🏦</span>
				<div class="rn-title">Collected</div>
				<div class="rn-amount" style="color:#16a34a;">{{ $inr($collected) }}</div>
				<div class="rn-meta">GST receipts + ledger auto-recorded</div>
			</div>

			<div class="rev-wire"><span class="rw-label">MINUS EXPENSES</span></div>

			<div class="rev-node">
				<span class="rn-icon">🧾</span>
				<div class="rn-title">Expenses</div>
				<div class="rn-amount" style="color:#dc2626;">{{ $inr($expensesPaise) }}</div>
				<div class="rn-meta">from Office Finance</div>
			</div>

			<div class="rev-wire"><span class="rw-label">NET</span></div>

			<div class="rev-node" style="border-color:{{ $net >= 0 ? '#86d3a5' : '#f3b1b1' }};">
				<span class="rn-icon">{{ $net >= 0 ? '📈' : '📉' }}</span>
				<div class="rn-title">Net</div>
				<div class="rn-amount" style="color:{{ $net >= 0 ? '#16a34a' : '#dc2626' }};">{{ $inr($net) }}</div>
				<div class="rn-meta">collected − expenses ({{ ['today' => 'today', '7d' => '7 days', '30d' => '30 days', 'all' => 'all time'][$period] }})</div>
			</div>
		</div>

		<div class="rev-lane mt-4" style="min-width:auto;">
			<div class="rev-node" style="flex:1;">
				<div class="rn-title">📊 Collections — last 6 months</div>
				<div class="rev-bars">
					@foreach ($trend as $month)
						<div class="rev-bar">
							<span class="rb-amount">{{ $month['paise'] > 0 ? $inr($month['paise']) : '' }}</span>
							<div class="rb-fill" style="height: {{ max(3, (int) round($month['paise'] / $trendMax * 100)) }}px;"></div>
							<span class="rb-label">{{ $month['label'] }}</span>
						</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>

	<p class="text-muted fs-12">
		💡 <b>{{ $inr($outstanding) }}</b> is still to be collected from active EMI plans
		@if ($overdue > 0)
			— of which <b class="text-danger">{{ $inr($overdue) }} is overdue</b>: open
			<a href="{{ route('fee-collections.index', ['status' => 'overdue']) }}">Fee Collections → Overdue</a> and start calling.
		@else
			— nothing is overdue right now. 🎉
		@endif
	</p>

@endsection
