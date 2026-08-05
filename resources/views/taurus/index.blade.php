@extends('layouts.app')

@section('title', 'Taurus Neural Ops')

{{--
	Taurus Neural Ops — the Super Admin command centre.

	Every colour, font and rule below is namespaced under .taurus-console so the
	console's dark treatment cannot leak into the CRM's sidebar, header or any
	other page sharing this layout. Charts use the ApexCharts build already
	loaded by layouts.app; nothing here loads an external asset.
--}}

@push('styles')
	<style>
		.taurus-console {
			--tc-black: #010308;
			--tc-panel: #070c16;
			--tc-edge: #152238;
			--tc-blue: #1b6df0;
			--tc-sky: #7fb0ff;
			--tc-green: #0ba860;
			--tc-mint: #5fd6a2;
			--tc-red: #e05555;
			--tc-amber: #f5a623;
			--tc-text: #eaf1fd;
			--tc-muted: #54637d;
			--tc-mono: ui-monospace, "SF Mono", "Cascadia Mono", Menlo, Consolas, monospace;

			position: relative;
			background: var(--tc-black);
			border: 1px solid var(--tc-edge);
			border-radius: 16px;
			color: var(--tc-text);
			padding: 16px clamp(12px, 2.5vw, 28px) 30px;
			margin-bottom: 24px;
			overflow: hidden;
			font-size: 14px;
			line-height: 1.5;
		}

		.taurus-console #tc-stars { position: absolute; inset: 0; z-index: 0; }
		.taurus-console .tc-shell { position: relative; z-index: 1; }
		.taurus-console ::selection { background: rgba(27, 109, 240, .4); }
		.taurus-console button { font-family: inherit; cursor: pointer; }
		.taurus-console :focus-visible { outline: 2px solid var(--tc-sky); outline-offset: 2px; border-radius: 4px; }
		.taurus-console a { color: var(--tc-sky); }

		/* top bar */
		.taurus-console .tc-top { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
		.taurus-console .tc-wordmark { font-weight: 800; letter-spacing: -.02em; font-size: 20px; }
		.taurus-console .tc-wordmark em { font-style: normal; color: var(--tc-blue); }
		.taurus-console .tc-sysline { font-family: var(--tc-mono); font-size: 9.5px; letter-spacing: .26em; color: var(--tc-muted); text-transform: uppercase; }
		.taurus-console .tc-top .tc-right { margin-left: auto; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
		.taurus-console .tc-clock { font-family: var(--tc-mono); font-size: 13px; color: var(--tc-sky); letter-spacing: .06em; }
		.taurus-console .tc-linkstate { display: flex; align-items: center; gap: 7px; font-family: var(--tc-mono); font-size: 9.5px; letter-spacing: .18em; color: var(--tc-muted); }
		.taurus-console .tc-linkstate .tc-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--tc-green); box-shadow: 0 0 8px var(--tc-green); }
		.taurus-console .tc-linkstate.tc-stale .tc-dot { background: var(--tc-amber); box-shadow: 0 0 8px var(--tc-amber); }
		.taurus-console .tc-vtoggle { background: transparent; border: 1px solid var(--tc-edge); color: var(--tc-muted); font-family: var(--tc-mono); font-size: 9.5px; letter-spacing: .18em; border-radius: 7px; padding: 6px 11px; transition: .2s; }
		.taurus-console .tc-vtoggle.tc-on { color: var(--tc-mint); border-color: var(--tc-green); box-shadow: 0 0 12px rgba(11, 168, 96, .25); }

		/* ticker */
		.taurus-console .tc-ticker { overflow: hidden; border: 1px solid var(--tc-edge); border-radius: 8px; background: rgba(7, 12, 22, .7); margin-bottom: 10px; }
		.taurus-console .tc-ticker .tc-track { display: inline-flex; gap: 42px; white-space: nowrap; padding: 7px 0; animation: tc-tick 46s linear infinite; }
		.taurus-console .tc-ticker:hover .tc-track { animation-play-state: paused; }
		.taurus-console .tc-tk { font-family: var(--tc-mono); font-size: 11px; color: var(--tc-muted); }
		.taurus-console .tc-tk .tc-k-lead { color: var(--tc-sky); }
		.taurus-console .tc-tk .tc-k-pay { color: var(--tc-mint); }
		.taurus-console .tc-tk .tc-k-alert { color: var(--tc-amber); }
		@keyframes tc-tick { from { transform: translateX(0); } to { transform: translateX(-50%); } }

		/* the brain */
		.taurus-console .tc-brainstage { position: relative; height: min(38vh, 340px); min-height: 240px; }
		.taurus-console #tc-brain { position: absolute; inset: 0; width: 100%; height: 100%; }

		/* ask bar */
		.taurus-console .tc-under { display: flex; flex-direction: column; align-items: center; gap: 14px; margin: 2px auto 18px; max-width: 820px; }
		.taurus-console .tc-hail { font-family: var(--tc-mono); font-size: 10px; letter-spacing: .34em; color: var(--tc-sky); text-transform: uppercase; text-align: center; }
		.taurus-console .tc-greet { font-weight: 800; font-size: clamp(18px, 2.6vw, 24px); letter-spacing: -.02em; text-align: center; }
		.taurus-console .tc-askrow { display: flex; gap: 10px; width: 100%; }
		.taurus-console .tc-ask { flex: 1; background: rgba(2, 4, 9, .8); border: 1px solid var(--tc-edge); border-radius: 10px; color: var(--tc-text); font-family: var(--tc-mono); font-size: 13px; padding: 13px 15px; transition: .2s; min-width: 0; }
		.taurus-console .tc-ask::placeholder { color: #33415c; }
		.taurus-console .tc-ask:focus { border-color: var(--tc-blue); outline: none; box-shadow: 0 0 0 3px rgba(27, 109, 240, .2); }
		.taurus-console .tc-ask:disabled { opacity: .5; }
		.taurus-console .tc-mic { width: 48px; flex: none; background: rgba(2, 4, 9, .8); border: 1px solid var(--tc-edge); border-radius: 10px; color: var(--tc-sky); font-size: 17px; display: flex; align-items: center; justify-content: center; transition: .2s; }
		.taurus-console .tc-mic:hover { border-color: var(--tc-blue); }
		.taurus-console .tc-mic.tc-live { background: rgba(11, 168, 96, .16); border-color: var(--tc-green); color: var(--tc-mint); animation: tc-pulse 1s ease-in-out infinite; }
		.taurus-console .tc-send { background: var(--tc-blue); border: none; color: #fff; font-family: var(--tc-mono); font-size: 11px; letter-spacing: .18em; padding: 0 22px; border-radius: 10px; }
		.taurus-console .tc-send:hover { filter: brightness(1.15); }
		.taurus-console .tc-send:disabled { opacity: .5; cursor: wait; }
		@keyframes tc-pulse { 0%, 100% { opacity: .92; } 50% { opacity: .5; } }

		/* briefing */
		.taurus-console .tc-briefbox { max-width: 820px; margin: 0 auto 6px; background: var(--tc-panel); border: 1px solid var(--tc-edge); border-radius: 12px; padding: 14px 18px; width: 100%; }
		.taurus-console .tc-briefbox .tc-bh { display: flex; align-items: center; gap: 10px; margin-bottom: 7px; }
		.taurus-console .tc-briefbox .tc-src { font-family: var(--tc-mono); font-size: 9px; letter-spacing: .24em; color: var(--tc-sky); }
		.taurus-console .tc-say { background: transparent; border: 1px solid var(--tc-edge); color: var(--tc-muted); border-radius: 5px; font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .14em; padding: 2px 8px; margin-left: auto; }
		.taurus-console .tc-say:hover { color: var(--tc-mint); border-color: var(--tc-green); }
		.taurus-console .tc-briefbox .tc-bt { font-size: 13.5px; color: #c4d4f1; min-height: 20px; white-space: pre-wrap; }
		.taurus-console .tc-caret { display: inline-block; width: 7px; height: 14px; background: var(--tc-sky); vertical-align: -2px; animation: tc-pulse 1s steps(2) infinite; }
		.taurus-console .tc-hint { font-family: var(--tc-mono); font-size: 9.5px; letter-spacing: .1em; color: var(--tc-muted); text-align: center; }
		.taurus-console .tc-hint b { color: var(--tc-sky); font-weight: 500; }

		/* conversation transcript */
		.taurus-console .tc-log { max-height: 260px; overflow-y: auto; margin-bottom: 10px; }
		.taurus-console .tc-log:empty { display: none; }
		.taurus-console .tc-turn { font-size: 12.5px; margin-bottom: 9px; white-space: pre-wrap; }
		.taurus-console .tc-turn .tc-who2 { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .2em; display: block; margin-bottom: 2px; }
		.taurus-console .tc-turn.tc-me .tc-who2 { color: var(--tc-mint); }
		.taurus-console .tc-turn.tc-it .tc-who2 { color: var(--tc-sky); }
		.taurus-console .tc-turn.tc-me { color: #9fe3c2; }
		.taurus-console .tc-turn.tc-it { color: #c4d4f1; }
		.taurus-console .tc-tools { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .12em; color: var(--tc-muted); margin-top: 3px; }

		/* confirmation cards */
		.taurus-console .tc-confirms:empty { display: none; }
		.taurus-console .tc-confirm {
			border: 1px solid rgba(245, 166, 35, .45); background: rgba(245, 166, 35, .07);
			border-radius: 10px; padding: 11px 13px; margin-top: 10px;
		}
		.taurus-console .tc-confirm .tc-ch { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .2em; color: var(--tc-amber); margin-bottom: 5px; }
		.taurus-console .tc-confirm .tc-cs { font-size: 12.5px; color: #f0e2c8; margin-bottom: 9px; }
		.taurus-console .tc-confirm .tc-cbody { font-size: 12px; color: #c4d4f1; background: rgba(2, 4, 9, .5); border-left: 2px solid var(--tc-amber); border-radius: 0 6px 6px 0; padding: 7px 10px; margin-bottom: 9px; white-space: pre-wrap; }
		.taurus-console .tc-confirm .tc-cactions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
		.taurus-console .tc-confirm .tc-cnote { font-family: var(--tc-mono); font-size: 8.5px; color: var(--tc-muted); }

		/* section labels */
		.taurus-console .tc-slab { display: flex; align-items: center; gap: 12px; margin: 22px 0 10px; }
		.taurus-console .tc-slab .tc-k { font-family: var(--tc-mono); font-size: 9.5px; letter-spacing: .3em; color: var(--tc-sky); text-transform: uppercase; }
		.taurus-console .tc-slab .tc-ln { flex: 1; height: 1px; background: linear-gradient(90deg, var(--tc-edge), transparent); }

		/* metric cards */
		.taurus-console .tc-cards { display: grid; gap: 12px; }
		.taurus-console .tc-cards.tc-c3 { grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); }
		.taurus-console .tc-cards.tc-c4 { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
		.taurus-console .tc-card { background: var(--tc-panel); border: 1px solid var(--tc-edge); border-radius: 12px; padding: 14px 16px; position: relative; overflow: hidden; }
		.taurus-console .tc-card::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 2px; background: var(--tc-blue); opacity: .75; }
		.taurus-console .tc-card.tc-good::before { background: var(--tc-green); }
		.taurus-console .tc-card.tc-warn::before { background: var(--tc-amber); }
		.taurus-console .tc-card.tc-bad::before { background: var(--tc-red); }
		.taurus-console .tc-card .tc-row1 { display: flex; align-items: flex-end; justify-content: space-between; gap: 8px; }
		.taurus-console .tc-card .tc-v { font-family: var(--tc-mono); font-size: 25px; color: var(--tc-sky); line-height: 1; }
		.taurus-console .tc-card.tc-good .tc-v { color: var(--tc-mint); }
		.taurus-console .tc-card.tc-warn .tc-v { color: var(--tc-amber); }
		.taurus-console .tc-card.tc-bad .tc-v { color: var(--tc-red); }
		.taurus-console .tc-card canvas { width: 64px; height: 26px; }
		.taurus-console .tc-card .tc-l { font-family: var(--tc-mono); font-size: 9px; letter-spacing: .18em; color: var(--tc-muted); text-transform: uppercase; margin-top: 8px; }
		.taurus-console .tc-card .tc-sub { font-family: var(--tc-mono); font-size: 10.5px; color: var(--tc-muted); margin-top: 3px; }
		.taurus-console .tc-card .tc-sub b { color: var(--tc-text); font-weight: 500; }
		.taurus-console .tc-owner { display: inline-flex; align-items: center; gap: 6px; margin-top: 9px; font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .12em; color: var(--tc-muted); border: 1px solid var(--tc-edge); border-radius: 20px; padding: 2px 9px 2px 3px; text-transform: uppercase; }
		.taurus-console .tc-owner .tc-av { width: 15px; height: 15px; border-radius: 50%; background: linear-gradient(135deg, var(--tc-blue), #0a2b66); display: inline-flex; align-items: center; justify-content: center; font-size: 7.5px; color: #fff; }

		/* widgets */
		.taurus-console .tc-grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 14px; }
		.taurus-console .tc-span4 { grid-column: span 4; }
		.taurus-console .tc-span6 { grid-column: span 6; }
		.taurus-console .tc-span8 { grid-column: span 8; }
		@media (max-width: 1400px) { .taurus-console .tc-span4 { grid-column: span 6; } }
		@media (max-width: 1100px) { .taurus-console .tc-span4, .taurus-console .tc-span6, .taurus-console .tc-span8 { grid-column: span 12; } }
		.taurus-console .tc-w { background: var(--tc-panel); border: 1px solid var(--tc-edge); border-radius: 12px; padding: 16px 18px; min-width: 0; }
		.taurus-console .tc-w .tc-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
		.taurus-console .tc-w .tc-head h2 { font-weight: 700; font-size: 13.5px; margin: 0; color: var(--tc-text); }
		.taurus-console .tc-w .tc-head .tc-tag { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .2em; color: var(--tc-black); background: var(--tc-sky); border-radius: 3px; padding: 2px 6px; }
		.taurus-console .tc-w .tc-head .tc-ts { margin-left: auto; font-family: var(--tc-mono); font-size: 9.5px; color: var(--tc-muted); }
		.taurus-console .tc-runbtn { background: rgba(27, 109, 240, .14); border: 1px solid var(--tc-blue); color: var(--tc-sky); font-family: var(--tc-mono); font-size: 9px; letter-spacing: .16em; border-radius: 6px; padding: 5px 12px; }
		.taurus-console .tc-runbtn:hover { background: rgba(27, 109, 240, .3); }
		.taurus-console .tc-runbtn:disabled { opacity: .45; cursor: wait; }
		.taurus-console .tc-addbtn { background: transparent; border: 1px solid var(--tc-edge); color: var(--tc-muted); font-family: var(--tc-mono); font-size: 9px; letter-spacing: .16em; border-radius: 6px; padding: 5px 12px; }
		.taurus-console .tc-addbtn:hover { color: var(--tc-sky); border-color: var(--tc-blue); }
		.taurus-console .tc-aiout { border-left: 2px solid var(--tc-blue); background: rgba(27, 109, 240, .06); border-radius: 0 8px 8px 0; padding: 10px 13px; font-size: 12.5px; color: #c4d4f1; margin-top: 12px; white-space: pre-wrap; display: none; }
		.taurus-console .tc-aiout.tc-show { display: block; }
		.taurus-console .tc-aiout .tc-h { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .22em; color: var(--tc-sky); margin-bottom: 5px; }

		/* rows + tables */
		.taurus-console .tc-rows { list-style: none; padding: 0; margin: 0; }
		.taurus-console .tc-rows li { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid rgba(21, 34, 56, .7); font-size: 12.5px; flex-wrap: wrap; }
		.taurus-console .tc-rows li:first-child { border-top: none; }
		.taurus-console .tc-rows .tc-nm { flex: 1; min-width: 160px; }
		.taurus-console .tc-rows .tc-nm b { font-weight: 600; }
		.taurus-console .tc-rows .tc-nm .tc-m { color: #8ca0c2; font-size: 11.5px; }
		.taurus-console .tc-rows .tc-amt { font-family: var(--tc-mono); font-size: 12px; color: var(--tc-sky); min-width: 86px; text-align: right; }
		.taurus-console .tc-flag { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .1em; border-radius: 3px; padding: 2px 7px; flex: none; }
		.taurus-console .tc-flag.tc-waste { background: rgba(224, 85, 85, .14); color: var(--tc-red); }
		.taurus-console .tc-flag.tc-watch { background: rgba(245, 166, 35, .14); color: var(--tc-amber); }
		.taurus-console .tc-flag.tc-ok { background: rgba(11, 168, 96, .13); color: var(--tc-mint); }
		.taurus-console .tc-cancel { background: transparent; border: 1px solid var(--tc-red); color: var(--tc-red); font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .1em; border-radius: 5px; padding: 3px 9px; }
		.taurus-console .tc-cancel:hover { background: rgba(224, 85, 85, .15); }
		.taurus-console .tc-cancel.tc-q { border-color: var(--tc-mint); color: var(--tc-mint); pointer-events: none; }
		.taurus-console .tc-icon-btn { background: transparent; border: 1px solid var(--tc-edge); color: var(--tc-muted); border-radius: 5px; padding: 3px 8px; font-size: 11px; }
		.taurus-console .tc-icon-btn:hover { color: var(--tc-sky); border-color: var(--tc-blue); }
		.taurus-console table.tc-tt { width: 100%; border-collapse: collapse; }
		.taurus-console .tc-tt th { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .14em; color: var(--tc-muted); text-transform: uppercase; text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--tc-edge); }
		.taurus-console .tc-tt td { padding: 8px; border-bottom: 1px solid rgba(21, 34, 56, .7); font-size: 12.5px; vertical-align: middle; }
		.taurus-console .tc-tt td.tc-n { font-family: var(--tc-mono); font-size: 11.5px; color: var(--tc-sky); }
		.taurus-console .tc-tt .tc-who b { font-weight: 600; }
		.taurus-console .tc-tt .tc-who span { display: block; font-size: 10.5px; color: var(--tc-muted); }
		.taurus-console .tc-sig { font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .1em; border-radius: 3px; padding: 2px 8px; }
		.taurus-console .tc-sig.tc-keep { background: rgba(11, 168, 96, .15); color: var(--tc-mint); }
		.taurus-console .tc-sig.tc-track { background: rgba(27, 109, 240, .15); color: var(--tc-sky); }
		.taurus-console .tc-sig.tc-review { background: rgba(245, 166, 35, .16); color: var(--tc-amber); }
		.taurus-console .tc-trend.tc-up { color: var(--tc-mint); }
		.taurus-console .tc-trend.tc-dn { color: var(--tc-red); }
		.taurus-console .tc-trend.tc-fl { color: var(--tc-muted); }
		.taurus-console .tc-note { font-size: 10.5px; color: var(--tc-muted); margin-top: 10px; margin-bottom: 0; }
		.taurus-console .tc-empty { font-family: var(--tc-mono); font-size: 11px; color: var(--tc-muted); padding: 14px 0; }
		.taurus-console .tc-foot { margin-top: 26px; font-family: var(--tc-mono); font-size: 9px; letter-spacing: .22em; color: #26344f; text-align: center; text-transform: uppercase; }

		/* approval queue */
		.taurus-console .tc-queue { border: 1px solid rgba(245, 166, 35, .35); background: rgba(245, 166, 35, .06); border-radius: 12px; padding: 14px 18px; margin-top: 14px; }
		.taurus-console .tc-queue h2 { font-size: 13px; font-weight: 700; margin: 0 0 4px; color: var(--tc-amber); }
		.taurus-console .tc-queue form { display: inline; }
		.taurus-console .tc-approve { background: transparent; border: 1px solid var(--tc-green); color: var(--tc-mint); font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .1em; border-radius: 5px; padding: 3px 9px; }
		.taurus-console .tc-dismiss { background: transparent; border: 1px solid var(--tc-edge); color: var(--tc-muted); font-family: var(--tc-mono); font-size: 8.5px; letter-spacing: .1em; border-radius: 5px; padding: 3px 9px; }

		@media (prefers-reduced-motion: reduce) {
			.taurus-console .tc-ticker .tc-track,
			.taurus-console .tc-caret,
			.taurus-console .tc-mic.tc-live { animation: none; }
		}
	</style>
@endpush

@section('content')

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			{{ session('success') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			{{ session('error') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	@endif
	@if ($errors->any())
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<ul class="mb-0 ps-3">
				@foreach ($errors->all() as $error)
					<li>{{ $error }}</li>
				@endforeach
			</ul>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	@endif

	<section class="taurus-console" aria-label="Taurus Neural Ops">
		<canvas id="tc-stars" aria-hidden="true"></canvas>

		<div class="tc-shell">

			<header class="tc-top">
				<div>
					<div class="tc-wordmark">TAURUS <em>· NEURAL OPS</em></div>
					<div class="tc-sysline">browsejobs · live organizational cortex · super admin only</div>
				</div>
				<div class="tc-right">
					<button type="button" class="tc-vtoggle" id="tc-vtoggle" aria-pressed="false">◉ VOICE · OFF</button>
					<div class="tc-linkstate" id="tc-linkstate">
						<span class="tc-dot"></span><span id="tc-linktext">CORTEX · LIVE</span>
					</div>
					<div class="tc-clock" id="tc-clock">--:--:-- IST</div>
				</div>
			</header>

			<div class="tc-ticker" aria-label="Live events"><div class="tc-track" id="tc-ticker"></div></div>

			<section class="tc-brainstage" aria-label="Taurus neural core">
				<canvas id="tc-brain"></canvas>
			</section>

			<div class="tc-under">
				<div>
					<div class="tc-hail" id="tc-hail">TAURUS · SYNAPSES LIVE</div>
					<div class="tc-greet" id="tc-greet">Welcome back.</div>
				</div>
				<div class="tc-askrow">
					<input class="tc-ask" id="tc-ask" autocomplete="off"
						placeholder="{{ $agentReady ? 'Ask, or tell Taurus to do something — “message Meera about the DevOps batch”' : 'Agent offline — add ANTHROPIC_API_KEY to .env' }}"
						aria-label="Ask Taurus" @disabled(! $agentReady)>
					<button type="button" class="tc-mic" id="tc-mic" title="Hold a conversation by voice" aria-label="Talk to Taurus" @disabled(! $agentReady)>🎙</button>
					<button type="button" class="tc-send" id="tc-send" @disabled(! $agentReady)>RUN</button>
				</div>
				<div class="tc-hint" id="tc-hint">
					Click the mic for hands-free — Taurus listens, answers aloud, then listens again.
					Say <b>“stand down”</b> to stop.
				</div>
			</div>

			<section class="tc-briefbox" aria-label="Conversation with Taurus">
				<div class="tc-bh">
					<span class="tc-src" id="tc-briefsrc">TAURUS · BRIEFING</span>
					<button type="button" class="tc-say" id="tc-say">▶ SPEAK</button>
					<button type="button" class="tc-say" id="tc-reset" title="Start a fresh conversation">⟲ NEW</button>
				</div>
				<div class="tc-log" id="tc-log" aria-live="polite"></div>
				<div class="tc-bt"><span id="tc-brieftext"></span><span class="tc-caret" id="tc-caret"></span></div>
				<div class="tc-confirms" id="tc-confirms"></div>
			</section>

			{{-- ── Placement pipeline ── --}}
			<div class="tc-slab"><span class="tc-k">Placement pipeline · today / MTD</span><div class="tc-ln"></div></div>
			@unless ($snapshot['pipeline']['available'])
				<p class="tc-empty">The LMS database is unreachable, so placement, fee and class-attendance numbers are unavailable. Everything sourced from the CRM below is still live.</p>
			@endunless
			<section class="tc-cards tc-c3" id="tc-pipe"></section>

			{{-- ── Business vitals ── --}}
			<div class="tc-slab"><span class="tc-k">Business vitals · finance / conversion / attendance</span><div class="tc-ln"></div></div>
			<section class="tc-cards tc-c4" id="tc-vitals"></section>

			{{-- ── Trends ── --}}
			<div class="tc-slab"><span class="tc-k">Trends</span><div class="tc-ln"></div></div>
			<main class="tc-grid">
				<section class="tc-w tc-span8">
					<div class="tc-head">
						<h2>Revenue vs expenses · this year by month</h2>
						<span class="tc-tag">FIN</span>
						<span class="tc-ts" id="tc-ts1"></span>
					</div>
					<div id="tc-chart-fin" style="height:220px"></div>
				</section>
				<section class="tc-w tc-span4">
					<div class="tc-head">
						<h2>Lead → joined funnel · MTD</h2>
						<span class="tc-tag">CRM</span>
					</div>
					<div id="tc-chart-fun" style="height:220px"></div>
				</section>
			</main>

			{{-- ── Intelligence panels ── --}}
			<div class="tc-slab"><span class="tc-k">Taurus intelligence · autonomous analysis</span><div class="tc-ln"></div></div>
			<main class="tc-grid">

				<section class="tc-w tc-span6">
					<div class="tc-head">
						<h2>Expense sentinel</h2>
						<span class="tc-tag">FIN·AI</span>
						<button type="button" class="tc-addbtn" data-bs-toggle="modal" data-bs-target="#tc-sub-modal">+ SUBSCRIPTION</button>
						<button type="button" class="tc-runbtn" id="tc-run-exp" @disabled(! $agentReady)>⚡ RUN ANALYSIS</button>
					</div>
					<ul class="tc-rows" id="tc-subs"></ul>
					<div class="tc-aiout" id="tc-ai-exp"><div class="tc-h">TAURUS · EXPENSE READ</div><span></span></div>
					<p class="tc-note">
						Flagged burn: <b id="tc-waste" style="color:var(--tc-red);font-family:var(--tc-mono)">—</b>
						of <b id="tc-total-burn" style="font-family:var(--tc-mono)">—</b> tracked monthly.
						Cancellations queue for your approval — Taurus never cancels anything itself.
					</p>
				</section>

				<section class="tc-w tc-span6">
					<div class="tc-head">
						<h2>Team signal</h2>
						<span class="tc-tag">HR·AI</span>
						<button type="button" class="tc-addbtn" data-bs-toggle="modal" data-bs-target="#tc-target-modal">+ TARGET</button>
						<button type="button" class="tc-runbtn" id="tc-run-team" @disabled(! $agentReady)>⚡ RUN ANALYSIS</button>
					</div>
					<div class="table-responsive">
						<table class="tc-tt">
							<thead>
								<tr><th>Member</th><th>Metric · MTD</th><th>Trend</th><th>Signal</th><th></th></tr>
							</thead>
							<tbody id="tc-team"></tbody>
						</table>
					</div>
					<div class="tc-aiout" id="tc-ai-team"><div class="tc-h">TAURUS · TEAM READ</div><span></span></div>
					<p class="tc-note">
						Month-to-date progress against a monthly target — someone at 40% on the 10th is on pace.
						Signals are prompts for a conversation, not verdicts on a person.
					</p>
				</section>
			</main>

			{{-- ── Approval queue ── --}}
			@if (! empty($snapshot['pending_actions']))
				<div class="tc-queue">
					<h2>Awaiting your approval</h2>
					<p class="tc-note mb-2">Taurus proposed these. Nothing happens until you decide.</p>
					<ul class="tc-rows">
						@foreach ($snapshot['pending_actions'] as $pending)
							<li>
								<span class="tc-nm">
									<b>{{ $pending['summary'] }}</b><br>
									<span class="tc-m">Requested by {{ $pending['requested_by'] }} · {{ $pending['requested_at'] }}</span>
								</span>
								<form method="POST" action="{{ route('taurus.actions.decide', $pending['id']) }}">
									@csrf
									@method('PATCH')
									<input type="hidden" name="decision" value="approved">
									<button type="submit" class="tc-approve">APPROVE</button>
								</form>
								<form method="POST" action="{{ route('taurus.actions.decide', $pending['id']) }}">
									@csrf
									@method('PATCH')
									<input type="hidden" name="decision" value="dismissed">
									<button type="submit" class="tc-dismiss">DISMISS</button>
								</form>
							</li>
						@endforeach
					</ul>
				</div>
			@endif

			<div class="tc-foot">browsejobs · every promise in writing · every call recorded &amp; AI-monitored</div>
		</div>
	</section>

	{{-- ── Add subscription ── --}}
	<div class="modal fade" id="tc-sub-modal" tabindex="-1" aria-labelledby="tc-sub-modal-label" aria-hidden="true">
		<div class="modal-dialog">
			<form class="modal-content" method="POST" action="{{ route('taurus.subscriptions.store') }}">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title" id="tc-sub-modal-label">Track a subscription</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p class="text-muted fs-13">
						The sentinel needs two things it cannot infer from the expenses ledger: what this costs
						every month, and when anyone last actually used it.
					</p>
					<div class="mb-3">
						<label class="form-label" for="tc-sub-name">Name <span class="text-danger">*</span></label>
						<input type="text" class="form-control" id="tc-sub-name" name="name" maxlength="255" required placeholder="Zoom Pro · 4 seats">
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-sub-vendor">Vendor</label>
							<input type="text" class="form-control" id="tc-sub-vendor" name="vendor" maxlength="255" placeholder="Zoom">
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-sub-cost">Monthly cost (₹) <span class="text-danger">*</span></label>
							<input type="number" step="0.01" min="0" class="form-control" id="tc-sub-cost" name="monthly_cost" required>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-sub-seats">Seats</label>
							<input type="number" min="1" class="form-control" id="tc-sub-seats" name="seats">
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-sub-used">Last used on</label>
							<input type="date" class="form-control" id="tc-sub-used" name="last_used_on" max="{{ today()->toDateString() }}">
							<small class="text-muted">Leave blank if nobody has used it — that counts as waste, not as unknown.</small>
						</div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="tc-sub-note">Usage note</label>
						<input type="text" class="form-control" id="tc-sub-note" name="usage_note" maxlength="500" placeholder="7 logins in 90 days · SEO work paused">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Add</button>
				</div>
			</form>
		</div>
	</div>

	{{-- ── Edit subscription (populated by JS from the clicked row) ── --}}
	<div class="modal fade" id="tc-sub-edit-modal" tabindex="-1" aria-labelledby="tc-sub-edit-label" aria-hidden="true">
		<div class="modal-dialog">
			<form class="modal-content" method="POST" id="tc-sub-edit-form">
				@csrf
				@method('PATCH')
				<div class="modal-header">
					<h5 class="modal-title" id="tc-sub-edit-label">Update subscription</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label" for="tc-esub-name">Name <span class="text-danger">*</span></label>
						<input type="text" class="form-control" id="tc-esub-name" name="name" maxlength="255" required>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-esub-vendor">Vendor</label>
							<input type="text" class="form-control" id="tc-esub-vendor" name="vendor" maxlength="255">
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-esub-cost">Monthly cost (₹) <span class="text-danger">*</span></label>
							<input type="number" step="0.01" min="0" class="form-control" id="tc-esub-cost" name="monthly_cost" required>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-esub-seats">Seats</label>
							<input type="number" min="1" class="form-control" id="tc-esub-seats" name="seats">
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-esub-used">Last used on</label>
							<input type="date" class="form-control" id="tc-esub-used" name="last_used_on" max="{{ today()->toDateString() }}">
						</div>
					</div>
					<div class="mb-3">
						<label class="form-label" for="tc-esub-note">Usage note</label>
						<input type="text" class="form-control" id="tc-esub-note" name="usage_note" maxlength="500">
					</div>
					<div class="form-check">
						<input class="form-check-input" type="checkbox" value="1" id="tc-esub-active" name="is_active">
						<label class="form-check-label" for="tc-esub-active">Still being paid for</label>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>

	{{-- ── Set target ── --}}
	<div class="modal fade" id="tc-target-modal" tabindex="-1" aria-labelledby="tc-target-modal-label" aria-hidden="true">
		<div class="modal-dialog">
			<form class="modal-content" method="POST" action="{{ route('taurus.targets.store') }}">
				@csrf
				<div class="modal-header">
					<h5 class="modal-title" id="tc-target-modal-label">Set a monthly target</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<p class="text-muted fs-13">
						Only the target is stored. The actual is measured live from the CRM every time this page
						loads, so it can never drift from the leads, calls and tasks screens.
					</p>
					<div class="mb-3">
						<label class="form-label" for="tc-target-user">Team member <span class="text-danger">*</span></label>
						<select class="form-select" id="tc-target-user" name="user_id" required>
							<option value="">Select…</option>
							@foreach ($staff as $member)
								<option value="{{ $member->id }}">{{ $member->full_name }}</option>
							@endforeach
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label" for="tc-target-metric">Metric <span class="text-danger">*</span></label>
						<select class="form-select" id="tc-target-metric" name="metric" required>
							@foreach ($targetMetrics as $key => $label)
								<option value="{{ $key }}">{{ $label }}</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-target-value">Target <span class="text-danger">*</span></label>
							<input type="number" step="0.01" min="0" class="form-control" id="tc-target-value" name="target_value" required>
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label" for="tc-target-period">Month <span class="text-danger">*</span></label>
							<input type="month" class="form-control" id="tc-target-period" name="period" value="{{ $period }}" required>
						</div>
					</div>
					<p class="text-muted fs-13 mb-0">
						Saving the same person, metric and month again replaces the existing target.
					</p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Save target</button>
				</div>
			</form>
		</div>
	</div>

	{{-- Hidden delete forms, submitted by the row buttons. --}}
	<form method="POST" id="tc-sub-delete-form" class="d-none">@csrf @method('DELETE')</form>
	<form method="POST" id="tc-target-delete-form" class="d-none">@csrf @method('DELETE')</form>

@endsection

@push('scripts')
	<script>
		(function () {
			"use strict";

			const ROUTES = {
				snapshot: @json(route('taurus.snapshot')),
				converse: @json(route('taurus.converse')),
				brief: @json(route('taurus.brief')),
				analyse: @json(route('taurus.analyse')),
				actions: @json(route('taurus.actions.queue')),
				actionBase: @json(url('taurus/actions')),
				subBase: @json(url('taurus/subscriptions')),
				targetBase: @json(url('taurus/targets')),
			};
			const AGENT_READY = @json((bool) $agentReady);
			const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			const REFRESH_SECONDS = 90;
			const REDUCE = matchMedia("(prefers-reduced-motion: reduce)").matches;

			let SNAP = @json($snapshot);

			const $ = (id) => document.getElementById(id);
			const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({
				"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
			})[c]);
			const inr = (n) => "₹" + Number(n || 0).toLocaleString("en-IN");
			// The minus sign belongs outside the symbol: "-₹5L", never "₹-5L".
			const lakhs = (n) => (Number(n) < 0 ? "-₹" : "₹") + Math.abs(Number(n)) + "L";

			async function postJson(url, body) {
				const res = await fetch(url, {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
						"X-CSRF-TOKEN": CSRF,
						"X-Requested-With": "XMLHttpRequest",
						"Accept": "application/json",
					},
					body: JSON.stringify(body || {}),
				});
				if (!res.ok) throw new Error("HTTP " + res.status);
				return res.json();
			}

			/* ═══════════ voice ═══════════ */
			const Voice = {
				on: false,          // speak replies aloud
				handsFree: false,   // keep listening after each reply
				rec: null,
				listening: false,
				pick() {
					const voices = speechSynthesis.getVoices();
					return voices.find((v) => v.lang === "en-IN")
						|| voices.find((v) => /en-GB|en-US/.test(v.lang)) || voices[0];
				},
				// Spoken text differs from written: currency symbols, "MTD" and
				// lakh shorthand all read badly through a synthesiser.
				forSpeech(text) {
					return String(text)
						.replace(/₹\s?/g, " rupees ")
						.replace(/\bMTD\b/g, "month to date")
						.replace(/\bYTD\b/g, "year to date")
						.replace(/(\d)\s?L\b/g, "$1 lakh")
						.replace(/\bCPL\b/g, "cost per lead");
				},
				speak(text, onDone) {
					if (!("speechSynthesis" in window)) { if (onDone) onDone(); return; }
					speechSynthesis.cancel();
					const utter = new SpeechSynthesisUtterance(this.forSpeech(text));
					const voice = this.pick();
					if (voice) utter.voice = voice;
					utter.rate = 1.02;
					utter.onstart = () => (Brain.mode = "speak");
					utter.onend = () => {
						Brain.mode = "idle";
						if (onDone) onDone();
					};
					// If synthesis fails we must still hand control back, or
					// hands-free mode would stall waiting for a reply it never
					// finishes speaking.
					utter.onerror = () => { Brain.mode = "idle"; if (onDone) onDone(); };
					speechSynthesis.speak(utter);
				},
				initRec() {
					const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
					if (!SR) return null;
					const rec = new SR();
					rec.lang = "en-IN";
					rec.interimResults = true;
					rec.continuous = false;

					rec.onresult = (e) => {
						let said = "";
						for (const r of e.results) said += r[0].transcript;
						$("tc-ask").value = said;

						if (!e.results[e.results.length - 1].isFinal) return;

						rec.stop();

						if (/\b(stand down|stop listening|that'?s all|goodbye)\b/i.test(said)) {
							$("tc-ask").value = "";
							Voice.setHandsFree(false);
							Voice.speak("Standing down.");
							return;
						}

						runQuery(true);
					};

					rec.onstart = () => {
						this.listening = true;
						Brain.mode = "listen";
						$("tc-mic").classList.add("tc-live");
						$("tc-hail").textContent = "TAURUS · LISTENING";
					};

					rec.onend = () => {
						this.listening = false;
						if (Brain.mode === "listen") Brain.mode = "idle";
						$("tc-mic").classList.remove("tc-live");
						if (!BUSY) {
							$("tc-hail").textContent = this.handsFree ? "TAURUS · HANDS-FREE" : "TAURUS · SYNAPSES LIVE";
						}
					};

					rec.onerror = (e) => {
						// "no-speech" and "aborted" are routine in a hands-free
						// loop (a pause, or our own stop()); don't shout about them.
						if (e.error === "no-speech" || e.error === "aborted") return;
						this.setHandsFree(false);
						typeOut(e.error === "not-allowed"
							? "Microphone blocked. Voice needs HTTPS and mic permission for this site."
							: "Voice input error: " + e.error);
					};

					return rec;
				},
				listen() {
					if (!this.rec) this.rec = this.initRec();
					if (!this.rec || this.listening || BUSY) return;
					try { this.rec.start(); } catch (e) { /* already starting */ }
				},
				setHandsFree(state) {
					this.handsFree = state;
					$("tc-mic").classList.toggle("tc-live", state);
					if (state) {
						// Replies must be audible, or hands-free is one-way.
						this.on = true;
						syncVoiceToggle();
						this.listen();
					} else {
						if (this.rec && this.listening) this.rec.stop();
						$("tc-hail").textContent = "TAURUS · SYNAPSES LIVE";
					}
				},
			};

			function syncVoiceToggle() {
				const btn = $("tc-vtoggle");
				btn.classList.toggle("tc-on", Voice.on);
				btn.setAttribute("aria-pressed", String(Voice.on));
				btn.textContent = "◉ VOICE · " + (Voice.on ? "ON" : "OFF");
			}

			$("tc-mic").onclick = () => {
				if (!Voice.rec) Voice.rec = Voice.initRec();
				if (!Voice.rec) {
					typeOut("This browser has no speech recognition — try Chrome or Edge. Spoken replies still work.");
					return;
				}
				Voice.setHandsFree(!Voice.handsFree);
			};

			$("tc-vtoggle").onclick = () => {
				Voice.on = !Voice.on;
				syncVoiceToggle();
				if (Voice.on) {
					Voice.speak("Taurus online.");
				} else {
					speechSynthesis.cancel();
					Voice.setHandsFree(false);
				}
			};
			$("tc-say").onclick = () => {
				const text = $("tc-brieftext").textContent;
				if (text) Voice.speak(text);
			};

			/* ═══════════ the sphere ═══════════ */
			const Brain = { mode: "idle" };
			(function sphereBrain() {
				const cv = $("tc-brain"), ctx = cv.getContext("2d");
				const PAL = ["#7FB0FF", "#1B6DF0", "#5FD6A2", "#F5A623", "#B78CFF", "#FF7FA8", "#59E0FF"];
				const N = 200, TILT = 0.42;
				let W, H, R, nodes = [], edges = [], pulses = [], rotY = 0;

				function size() {
					const rect = cv.getBoundingClientRect();
					W = cv.width = Math.max(1, rect.width * devicePixelRatio);
					H = cv.height = Math.max(1, rect.height * devicePixelRatio);
					R = Math.min(W, H) * 0.4;
				}
				function build() {
					size(); nodes = []; edges = []; pulses = [];
					const GA = Math.PI * (3 - Math.sqrt(5));	// fibonacci sphere
					for (let i = 0; i < N; i++) {
						const y = 1 - (i / (N - 1)) * 2, rad = Math.sqrt(1 - y * y), th = GA * i;
						nodes.push({
							x: Math.cos(th) * rad, y: y, z: Math.sin(th) * rad,
							c: PAL[i % 7 < 2 ? 0 : i % PAL.length],
							ph: Math.random() * 6.28, sp: 0.6 + Math.random(),
						});
					}
					for (let i = 0; i < N; i++) {
						for (let j = i + 1; j < N; j++) {
							const a = nodes[i], b = nodes[j];
							const d = (a.x - b.x) ** 2 + (a.y - b.y) ** 2 + (a.z - b.z) ** 2;
							if (d < 0.16) edges.push([i, j]);
						}
					}
				}
				function project(n) {
					const cy = Math.cos(rotY), sy = Math.sin(rotY);
					const x = n.x * cy - n.z * sy, z = n.x * sy + n.z * cy, y = n.y;
					const cx = Math.cos(TILT), sx = Math.sin(TILT);
					const y2 = y * cx - z * sx, z2 = y * sx + z * cx;
					const s = 3.1 / (3.1 + z2);
					return { x: W / 2 + x * R * s, y: H * 0.52 + y2 * R * s, z: z2, s: s };
				}
				function halo() {
					const col = Brain.mode === "listen" ? "11,168,96" : Brain.mode === "speak" ? "245,166,35" : "27,109,240";
					const g = ctx.createRadialGradient(W / 2, H * 0.52, R * 0.2, W / 2, H * 0.52, R * 1.55);
					g.addColorStop(0, "rgba(" + col + ",.13)");
					g.addColorStop(0.6, "rgba(" + col + ",.05)");
					g.addColorStop(1, "rgba(0,0,0,0)");
					ctx.fillStyle = g;
					ctx.fillRect(0, 0, W, H);
				}
				function firePulse() {
					if (pulses.length > 30 || !edges.length) return;
					const e = edges[Math.floor(Math.random() * edges.length)];
					pulses.push({ e: e, t: 0, sp: 0.014 + Math.random() * 0.02, c: PAL[Math.floor(Math.random() * PAL.length)] });
				}
				let frame = 0;
				function draw() {
					ctx.clearRect(0, 0, W, H);
					halo();
					const T = performance.now() / 1000;
					const P = nodes.map(project);

					for (const pair of edges) {
						const a = P[pair[0]], b = P[pair[1]];
						const o = Math.max(0, 0.3 - ((a.z + b.z) / 2) * 0.22);
						if (o <= 0.015) continue;
						ctx.strokeStyle = "rgba(127,176,255," + o + ")";
						ctx.lineWidth = 1;
						ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
					}

					if (!REDUCE) {
						if (frame++ % 5 === 0) firePulse();
						pulses = pulses.filter((p) => p.t <= 1);
						for (const p of pulses) {
							p.t += p.sp;
							const a = P[p.e[0]], b = P[p.e[1]];
							const x = a.x + (b.x - a.x) * p.t, y = a.y + (b.y - a.y) * p.t;
							const g = ctx.createRadialGradient(x, y, 0, x, y, 6 * devicePixelRatio);
							g.addColorStop(0, p.c); g.addColorStop(1, "rgba(0,0,0,0)");
							ctx.globalAlpha = 0.9; ctx.fillStyle = g;
							ctx.beginPath(); ctx.arc(x, y, 6 * devicePixelRatio, 0, 7); ctx.fill();
							ctx.globalAlpha = 1;
						}
					}

					nodes.forEach((n, i) => {
						const p = P[i];
						const tw = REDUCE ? 1 : 0.66 + 0.34 * Math.sin(T * n.sp * 2 + n.ph);
						const r = (1.1 + 1.5 * tw) * p.s * devicePixelRatio;
						const front = Math.max(0.25, 1 - ((p.z + 1) / 2) * 0.9);
						ctx.globalAlpha = front * tw;
						ctx.shadowColor = n.c;
						ctx.shadowBlur = 10 * devicePixelRatio * tw * front;
						ctx.fillStyle = n.c;
						ctx.beginPath(); ctx.arc(p.x, p.y, Math.max(0.6, r), 0, 7); ctx.fill();
						ctx.shadowBlur = 0; ctx.globalAlpha = 1;
					});

					if (!REDUCE) { rotY += 0.0032; requestAnimationFrame(draw); }
				}
				build(); draw();
				addEventListener("resize", () => { build(); if (REDUCE) draw(); });
			})();

			/* starfield */
			(function stars() {
				const cv = $("tc-stars"), ctx = cv.getContext("2d");
				let W, H, st = [];
				function build() {
					const rect = cv.parentElement.getBoundingClientRect();
					W = cv.width = Math.max(1, rect.width * devicePixelRatio);
					H = cv.height = Math.max(1, rect.height * devicePixelRatio);
					cv.style.width = rect.width + "px";
					cv.style.height = rect.height + "px";
					st = Array.from({ length: 130 }, () => ({
						x: Math.random() * W, y: Math.random() * H,
						r: (Math.random() * 1.1 + 0.3) * devicePixelRatio,
						ph: Math.random() * 6.28, sp: 0.3 + Math.random() * 0.8,
						c: Math.random() < 0.85 ? "180,205,240" : "127,176,255",
					}));
				}
				function draw() {
					ctx.clearRect(0, 0, W, H);
					const T = performance.now() / 1000;
					for (const s of st) {
						const a = REDUCE ? 0.5 : 0.25 + 0.45 * (0.5 + 0.5 * Math.sin(T * s.sp + s.ph));
						ctx.fillStyle = "rgba(" + s.c + "," + a + ")";
						ctx.beginPath(); ctx.arc(s.x, s.y, s.r, 0, 7); ctx.fill();
					}
					if (!REDUCE) requestAnimationFrame(draw);
				}
				build(); draw();
				addEventListener("resize", () => { build(); if (REDUCE) draw(); });
			})();

			/* ═══════════ rendering ═══════════ */
			const stamp = () => new Date().toLocaleTimeString("en-IN", { hour12: false, timeZone: "Asia/Kolkata" });

			function spark(cv, data, color) {
				const c = cv.getContext("2d"), W = (cv.width = 128), H = (cv.height = 52);
				const mn = Math.min(...data), mx = Math.max(...data), r = mx - mn || 1;
				c.clearRect(0, 0, W, H);
				c.beginPath();
				data.forEach((v, i) => {
					const x = (i / Math.max(1, data.length - 1)) * (W - 4) + 2;
					const y = H - 4 - ((v - mn) / r) * (H - 10);
					i ? c.lineTo(x, y) : c.moveTo(x, y);
				});
				c.strokeStyle = color; c.lineWidth = 2.4; c.lineJoin = "round"; c.stroke();
			}

			const ownerChip = (name) => {
				const initials = String(name).split(" ").map((w) => w[0]).slice(0, 2).join("");
				return '<span class="tc-owner"><span class="tc-av">' + esc(initials) + "</span>OWNER · " + esc(name) + "</span>";
			};

			function renderPipe() {
				const p = SNAP.pipeline;
				const live = p.available;
				const blocks = [
					["Mock interviews", p.mocks, ""],
					["Interview rounds", p.interviews, ""],
					["Offers received", p.offers, live ? "tc-good" : ""],
				];

				// A dark LMS means these numbers are unknown, not zero — showing
				// "0 offers today" when the source is unreachable is a lie.
				$("tc-pipe").innerHTML = blocks.map(([label, d, cls], i) =>
					'<div class="tc-card ' + cls + '"><div class="tc-row1">' +
					'<div class="tc-v">' + (live ? d.today : "—") +
					(live ? '<span style="font-size:12px;color:var(--tc-muted)"> today</span>' : "") + "</div>" +
					(live ? '<canvas id="tc-pp' + i + '"></canvas>' : "") + "</div>" +
					'<div class="tc-l">' + esc(label) + "</div>" +
					'<div class="tc-sub">' + (live ? "MTD <b>" + d.mtd + "</b> · last 7 days" : "LMS unreachable") + "</div>" +
					ownerChip(d.owner) + "</div>"
				).join("");

				if (live) {
					blocks.forEach(([, d], i) => spark($("tc-pp" + i), d.spark, i === 2 ? "#5FD6A2" : "#7FB0FF"));
				}
			}

			function renderVitals() {
				const f = SNAP.finance, v = SNAP.vitals;
				const students = v.attendance.students, staff = v.attendance.staff;

				const cards = [
					{
						v: lakhs(f.revMTD), l: "Revenue · MTD", cls: "tc-good", o: f.owners.revenue,
						sub: "today <b>" + esc(f.revToday) + "</b> · YTD <b>" + lakhs(f.revYTD) + "</b>",
					},
					{
						v: lakhs(f.expMTD), l: "Expenses · MTD", cls: "tc-warn", o: f.owners.expenses,
						sub: "YTD <b>" + lakhs(f.expYTD) + "</b> · sentinel active",
					},
					{
						v: lakhs(f.netMTD), l: "Net · MTD",
						cls: f.netMTD >= 0 ? "tc-good" : "tc-bad", o: f.owners.revenue,
						sub: f.marginMTD === null ? "no revenue recorded yet" : "margin <b>" + f.marginMTD + "%</b>",
					},
					{
						v: v.convRate + "%", l: "Conversion vs leads", cls: "", o: v.owners.conversion,
						sub: "<b>" + v.joinedMTD + "</b> joined / <b>" + v.leadsMTD + "</b> leads MTD",
					},
					{
						v: students.pct === null ? "—" : students.pct + "%",
						l: "Attendance · students",
						cls: students.pct === null ? "" : students.pct >= 90 ? "tc-good" : "tc-warn",
						o: v.owners.attendance,
						sub: students.available
							? (students.sessions === 0
								? "no classes scheduled today"
								: "<b>" + students.present + "</b> of " + students.roster + " across " + students.sessions + " class" + (students.sessions === 1 ? "" : "es"))
							: "LMS unreachable",
					},
					{
						v: staff.present + "/" + staff.total, l: "Attendance · staff", cls: "", o: v.owners.attendance,
						sub: "<b>" + staff.on_leave + "</b> on approved leave",
					},
					{
						v: esc(v.adSpendMTD), l: "Ad spend · MTD", cls: "", o: v.owners.ads,
						sub: "cost per lead <b>" + esc(v.cpl) + "</b>",
					},
				];

				$("tc-vitals").innerHTML = cards.map((c) =>
					'<div class="tc-card ' + c.cls + '"><div class="tc-v">' + c.v + "</div>" +
					'<div class="tc-l">' + esc(c.l) + '</div><div class="tc-sub">' + c.sub + "</div>" +
					ownerChip(c.o) + "</div>"
				).join("");
			}

			function renderTicker() {
				const row = SNAP.events.map((e) =>
					'<span class="tc-tk"><span class="tc-k-' + esc(e.kind) + '">▸</span> ' + esc(e.text) + "</span>"
				).join("");
				$("tc-ticker").innerHTML = row + row;
			}

			/* charts — ApexCharts ships with the CRM layout, so nothing external loads */
			const charts = {};
			const AXIS = { style: { colors: "#54637D", fontSize: "10px", fontFamily: "monospace" } };

			function renderCharts() {
				if (typeof ApexCharts === "undefined") return;
				const f = SNAP.finance, v = SNAP.vitals;
				$("tc-ts1").textContent = "SYNC " + stamp();

				const finOptions = {
					chart: { type: "bar", height: 220, toolbar: { show: false }, background: "transparent", fontFamily: "monospace" },
					theme: { mode: "dark" },
					series: [
						{ name: "Revenue ₹L", data: f.revByMonth },
						{ name: "Expenses ₹L", data: f.expByMonth },
					],
					colors: ["#0BA860", "#E05555"],
					plotOptions: { bar: { borderRadius: 3, columnWidth: "58%" } },
					dataLabels: { enabled: false },
					grid: { borderColor: "rgba(21,34,56,.9)", strokeDashArray: 3 },
					xaxis: { categories: f.months, labels: AXIS, axisBorder: { show: false }, axisTicks: { show: false } },
					yaxis: { labels: AXIS },
					legend: { labels: { colors: "#54637D" }, fontSize: "10px", markers: { width: 9, height: 9 } },
					tooltip: { theme: "dark", y: { formatter: (val) => "₹" + val + "L" } },
				};

				const funOptions = {
					chart: { type: "bar", height: 220, toolbar: { show: false }, background: "transparent", fontFamily: "monospace" },
					theme: { mode: "dark" },
					series: [{ name: "Leads", data: v.funnel.data }],
					colors: ["#1B6DF0"],
					plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: "55%", distributed: true } },
					dataLabels: { enabled: true, style: { colors: ["#EAF1FD"], fontSize: "11px" } },
					grid: { borderColor: "rgba(21,34,56,.9)", strokeDashArray: 3 },
					xaxis: { categories: v.funnel.labels, labels: AXIS, axisBorder: { show: false }, axisTicks: { show: false } },
					yaxis: { labels: { style: { colors: "#C4D4F1", fontSize: "10px", fontFamily: "monospace" } } },
					legend: { show: false },
					tooltip: { theme: "dark" },
				};

				if (charts.fin) { charts.fin.updateOptions(finOptions); } else {
					charts.fin = new ApexCharts($("tc-chart-fin"), finOptions);
					charts.fin.render();
				}
				if (charts.fun) { charts.fun.updateOptions(funOptions); } else {
					charts.fun = new ApexCharts($("tc-chart-fun"), funOptions);
					charts.fun.render();
				}
			}

			function renderSubs() {
				const s = SNAP.subscriptions;

				if (!s.rows.length) {
					$("tc-subs").innerHTML = '<li class="tc-empty">Nothing tracked yet. Add your recurring vendor spends — that is what turns this panel on.</li>';
				} else {
					$("tc-subs").innerHTML = s.rows.map((x) => {
						const flagText = x.flag === "waste" ? "UNUSED" : x.flag === "watch" ? "WATCH" : "IN USE";
						const cancelBtn = x.cancel_pending
							? '<button type="button" class="tc-cancel tc-q">QUEUED ✓</button>'
							: (x.flag !== "ok" ? '<button type="button" class="tc-cancel" data-id="' + x.id + '">CANCEL?</button>' : "");
						return "<li>" +
							'<span class="tc-nm"><b>' + esc(x.name) + "</b><br>" +
							'<span class="tc-m">' + esc(x.note) + "</span></span>" +
							'<span class="tc-flag tc-' + esc(x.flag) + '">' + flagText + "</span>" +
							'<span class="tc-amt">' + esc(x.cost_display) + "/mo</span>" +
							cancelBtn +
							'<button type="button" class="tc-icon-btn" data-edit-sub=\'' + esc(JSON.stringify(x)) + "'>✎</button>" +
							'<button type="button" class="tc-icon-btn" data-delete-sub="' + x.id + '">✕</button>' +
							"</li>";
					}).join("");
				}

				$("tc-waste").textContent = inr(s.waste_monthly) + "/mo";
				$("tc-total-burn").textContent = inr(s.total_monthly) + "/mo";
				wireSubButtons();
			}

			function wireSubButtons() {
				$("tc-subs").querySelectorAll("[data-id]").forEach((btn) => {
					btn.onclick = async () => {
						btn.textContent = "QUEUED ✓";
						btn.classList.add("tc-q");
						try {
							await postJson(ROUTES.actions, { action: "cancel_subscription", id: Number(btn.dataset.id) });
							// The queue itself only renders on load, so reload to surface the new request.
							location.reload();
						} catch (e) {
							btn.textContent = "FAILED";
							btn.classList.remove("tc-q");
						}
					};
				});

				$("tc-subs").querySelectorAll("[data-edit-sub]").forEach((btn) => {
					btn.onclick = () => {
						const sub = JSON.parse(btn.dataset.editSub);
						const form = $("tc-sub-edit-form");
						form.action = ROUTES.subBase + "/" + sub.id;
						$("tc-esub-name").value = sub.name || "";
						$("tc-esub-vendor").value = sub.vendor || "";
						$("tc-esub-cost").value = sub.cost ?? "";
						$("tc-esub-seats").value = sub.seats ?? "";
						$("tc-esub-used").value = sub.last_used_on || "";
						$("tc-esub-note").value = sub.usage_note || "";
						// Rows only ever contain active subscriptions.
						$("tc-esub-active").checked = true;
						new bootstrap.Modal($("tc-sub-edit-modal")).show();
					};
				});

				$("tc-subs").querySelectorAll("[data-delete-sub]").forEach((btn) => {
					btn.onclick = () => {
						if (!confirm("Stop tracking this subscription?")) return;
						const form = $("tc-sub-delete-form");
						form.action = ROUTES.subBase + "/" + btn.dataset.deleteSub;
						form.submit();
					};
				});
			}

			function renderTeam() {
				const rows = SNAP.team;

				if (!rows.length) {
					$("tc-team").innerHTML = '<tr><td colspan="5" class="tc-empty">No targets set for this month. Add one and the signal turns on — actuals are already being measured.</td></tr>';
					return;
				}

				$("tc-team").innerHTML = rows.map((m) => {
					const sigText = m.sig === "keep" ? "KEEP · STRONG" : m.sig === "track" ? "ON TRACK" : "REVIEW";
					const arrow = m.trend === "up" ? "▲" : m.trend === "dn" ? "▼" : "―";
					return '<tr><td class="tc-who"><b>' + esc(m.name) + "</b><span>" + esc(m.role) + "</span></td>" +
						'<td class="tc-n">' + esc(m.metric) + "<br>" + esc(m.n) + " · " + m.pct + "%</td>" +
						'<td class="tc-trend tc-' + esc(m.trend) + '">' + arrow + "</td>" +
						'<td><span class="tc-sig tc-' + esc(m.sig) + '">' + sigText + "</span></td>" +
						'<td><button type="button" class="tc-icon-btn" data-delete-target="' + m.id + '">✕</button></td></tr>';
				}).join("");

				$("tc-team").querySelectorAll("[data-delete-target]").forEach((btn) => {
					btn.onclick = () => {
						if (!confirm("Remove this target?")) return;
						const form = $("tc-target-delete-form");
						form.action = ROUTES.targetBase + "/" + btn.dataset.deleteTarget;
						form.submit();
					};
				});
			}

			function renderAll() {
				renderTicker();
				renderPipe();
				renderVitals();
				renderCharts();
				renderSubs();
				renderTeam();
			}

			/* ═══════════ agent I/O ═══════════ */
			// Hands-free is a loop: listen → answer → speak → listen again. The
			// hand-back has to happen when speech *finishes*, not when the reply
			// arrives, or Taurus would hear its own voice.
			function resumeListening() {
				if (Voice.handsFree && !BUSY) {
					setTimeout(() => Voice.listen(), 250);
				}
			}

			function typeOut(text, speakIt) {
				const out = $("tc-brieftext"), caret = $("tc-caret");
				out.textContent = "";
				caret.style.display = "inline-block";
				const done = () => {
					caret.style.display = "none";
					if (speakIt) { Voice.speak(text, resumeListening); } else { resumeListening(); }
				};
				if (REDUCE || text.length > 900) { out.textContent = text; done(); return; }
				let i = 0;
				const id = setInterval(() => {
					out.textContent = text.slice(0, ++i);
					if (i >= text.length) { clearInterval(id); done(); }
				}, 11);
			}

			/* ═══════════ conversation ═══════════ */
			// The transcript is held here and posted back each turn, so the
			// server stays stateless. It is the model's context only — every
			// tool re-authorises server-side regardless of what is in it.
			let HISTORY = [];
			let BUSY = false;

			function logTurn(who, text, tools) {
				const div = document.createElement("div");
				div.className = "tc-turn " + (who === "you" ? "tc-me" : "tc-it");
				const label = document.createElement("span");
				label.className = "tc-who2";
				label.textContent = who === "you" ? "YOU" : "TAURUS";
				div.appendChild(label);
				div.appendChild(document.createTextNode(text));
				if (tools && tools.length) {
					const t = document.createElement("div");
					t.className = "tc-tools";
					t.textContent = "▸ read " + tools.join(", ");
					div.appendChild(t);
				}
				$("tc-log").appendChild(div);
				$("tc-log").scrollTop = $("tc-log").scrollHeight;
			}

			function renderConfirms(actions) {
				const wrap = $("tc-confirms");
				wrap.innerHTML = "";
				if (!actions || !actions.length) return;

				for (const a of actions) {
					const card = document.createElement("div");
					card.className = "tc-confirm";

					const head = document.createElement("div");
					head.className = "tc-ch";
					head.textContent = "AWAITING YOUR APPROVAL";
					card.appendChild(head);

					const summary = document.createElement("div");
					summary.className = "tc-cs";
					summary.textContent = a.summary;
					card.appendChild(summary);

					// For a message, show the exact text that would go out —
					// approving something you can only see summarised is not
					// really approving it.
					if (a.action === "send_message" && a.payload && a.payload.body) {
						const body = document.createElement("div");
						body.className = "tc-cbody";
						body.textContent = a.payload.body;
						card.appendChild(body);
					}

					const actionsRow = document.createElement("div");
					actionsRow.className = "tc-cactions";

					const yes = document.createElement("button");
					yes.type = "button";
					yes.className = "tc-approve";
					yes.textContent = "APPROVE";

					const no = document.createElement("button");
					no.type = "button";
					no.className = "tc-dismiss";
					no.textContent = "DISMISS";

					const note = document.createElement("span");
					note.className = "tc-cnote";
					note.textContent = "nothing has happened yet";

					const decide = async (decision) => {
						yes.disabled = no.disabled = true;
						note.textContent = "working…";
						try {
							const res = await postJson(ROUTES.actionBase + "/" + a.id + "/confirm", { decision });
							note.textContent = res.message;
							actionsRow.removeChild(yes);
							actionsRow.removeChild(no);
							if (Voice.on) Voice.speak(res.message);
							refreshSnapshot();
						} catch (e) {
							note.textContent = "Failed — " + e.message;
							yes.disabled = no.disabled = false;
						}
					};

					yes.onclick = () => decide("approved");
					no.onclick = () => decide("dismissed");

					actionsRow.append(yes, no, note);
					card.appendChild(actionsRow);
					wrap.appendChild(card);
				}

				// Voice users can approve without reaching for the mouse.
				PENDING_DECISION = actions.length ? () => wrap.querySelector(".tc-approve")?.click() : null;
			}

			let PENDING_DECISION = null;

			async function runQuery(spoken) {
				const input = $("tc-ask"), btn = $("tc-send");
				const q = input.value.trim();
				if (!q || !AGENT_READY || BUSY) return;

				// "yes" / "confirm" while a card is open approves it rather than
				// being sent to the model as a fresh question.
				if (PENDING_DECISION && /^(yes|yeah|approve[d]?|confirm|do it|go ahead|send it)\b/i.test(q)) {
					input.value = "";
					PENDING_DECISION();
					PENDING_DECISION = null;
					return;
				}

				BUSY = true;
				btn.disabled = true;
				input.value = "";
				Brain.mode = "speak";
				$("tc-hail").textContent = "TAURUS · REASONING";
				$("tc-briefsrc").textContent = "TAURUS · CONVERSATION";
				logTurn("you", q);
				$("tc-brieftext").textContent = "…";
				$("tc-caret").style.display = "inline-block";
				renderConfirms([]);

				try {
					const data = await postJson(ROUTES.converse, { query: q, history: HISTORY });
					HISTORY = data.history || HISTORY;
					logTurn("taurus", data.reply, data.tools_used);
					typeOut(data.reply, Voice.on || spoken);
					renderConfirms(data.pending_actions);
				} catch (e) {
					typeOut("Could not reach Taurus (" + e.message + ").");
				}

				BUSY = false;
				btn.disabled = false;
				Brain.mode = "idle";
				$("tc-hail").textContent = Voice.handsFree ? "TAURUS · HANDS-FREE" : "TAURUS · SYNAPSES LIVE";
			}

			$("tc-send").onclick = () => runQuery(false);
			$("tc-ask").addEventListener("keydown", (e) => { if (e.key === "Enter") runQuery(false); });
			$("tc-reset").onclick = () => {
				HISTORY = [];
				$("tc-log").innerHTML = "";
				renderConfirms([]);
				$("tc-briefsrc").textContent = "TAURUS · BRIEFING";
				typeOut("Fresh start. What do you need?");
			};

			async function runPanel(btn, outId, panel) {
				const out = $(outId), span = out.querySelector("span");
				btn.disabled = true;
				btn.textContent = "⚡ THINKING…";
				out.classList.add("tc-show");
				span.textContent = "…";
				try {
					const data = await postJson(ROUTES.analyse, { panel: panel });
					span.textContent = data.reply;
					if (Voice.on) Voice.speak(data.reply);
				} catch (e) {
					span.textContent = "Analysis failed (" + e.message + ").";
				}
				btn.disabled = false;
				btn.textContent = "⚡ RUN ANALYSIS";
			}

			if (AGENT_READY) {
				$("tc-run-exp").onclick = (e) => runPanel(e.currentTarget, "tc-ai-exp", "expenses");
				$("tc-run-team").onclick = (e) => runPanel(e.currentTarget, "tc-ai-team", "team");
			}

			/* ═══════════ clock, greeting, boot ═══════════ */
			setInterval(() => {
				$("tc-clock").textContent =
					new Date().toLocaleTimeString("en-IN", { hour12: false, timeZone: "Asia/Kolkata" }) + " IST";
			}, 1000);

			const hour = new Date().getHours();
			const greeting = hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";
			$("tc-greet").textContent = greeting + ", " + @json($greetName) + ".";

			renderAll();

			if (AGENT_READY) {
				typeOut("Reading the room…");
				postJson(ROUTES.brief)
					.then((d) => typeOut(d.reply, Voice.on))
					.catch(() => typeOut("Numbers are live above; the written brief could not be generated."));
			} else {
				typeOut("Taurus is offline — ANTHROPIC_API_KEY is not configured. Every number above is still live from the CRM and LMS.");
				$("tc-hint").textContent = "Voice and conversation need an Anthropic key in .env.";
			}

			// A failed refresh marks the link stale rather than blanking the
			// dashboard — stale data beats no data here.
			async function refreshSnapshot() {
				try {
					const res = await fetch(ROUTES.snapshot, { headers: { "X-Requested-With": "XMLHttpRequest", "Accept": "application/json" } });
					if (!res.ok) throw new Error("HTTP " + res.status);
					SNAP = await res.json();
					renderAll();
					$("tc-linkstate").classList.remove("tc-stale");
					$("tc-linktext").textContent = "CORTEX · LIVE";
				} catch (e) {
					$("tc-linkstate").classList.add("tc-stale");
					$("tc-linktext").textContent = "CORTEX · STALE";
				}
			}

			setInterval(refreshSnapshot, REFRESH_SECONDS * 1000);
		})();
	</script>
@endpush
