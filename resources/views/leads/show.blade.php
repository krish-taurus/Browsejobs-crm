@extends('layouts.app')

@section('title', 'Lead — '.$lead->displayName())

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-0">{{ $lead->name ?: $lead->mobile }}</h4>
			<nav class="fs-13 text-muted">
				<a href="{{ url('/') }}" class="text-muted">Home</a> &gt;
				<a href="{{ route('leads.index') }}" class="text-muted">Leads</a> &gt; <span>Details</span>
			</nav>
		</div>
		<a href="{{ route('leads.index') }}" class="btn btn-outline-light"><i class="ti ti-arrow-left me-1"></i> Back</a>
	</div>

	@foreach (['success' => 'success', 'error' => 'danger'] as $key => $type)
		@if (session($key))
			<div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
				{{ session($key) }}
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		@endif
	@endforeach

	<div class="row">
		<!-- Left: details + actions -->
		<div class="col-lg-4">
			<div class="card mb-3">
				<div class="card-body">
					<h6 class="mb-3">Lead details</h6>
					<div class="fs-14">
						<div class="mb-2"><span class="text-muted">Name:</span> {{ $lead->name ?: '—' }}</div>
						<div class="mb-2"><span class="text-muted">Mobile:</span> {{ $lead->mobile }}</div>
						<div class="mb-2"><span class="text-muted">Email:</span> {{ $lead->email ?: '—' }}</div>
						<div class="mb-2"><span class="text-muted">Source:</span> <span class="text-capitalize">{{ $lead->source ?: 'manual' }}</span></div>
						@if ($lead->campaign_name)<div class="mb-2"><span class="text-muted">Campaign:</span> {{ $lead->campaign_name }}</div>@endif
						<div class="mb-2"><span class="text-muted">Status:</span>
							@if ($lead->status)<span class="badge" style="background: {{ $lead->status->color ?: '#6c757d' }};color:#fff;">{{ $lead->status->name }}</span>@endif
						</div>
						<div class="mb-2"><span class="text-muted">Assigned to:</span> {{ $lead->assignee->full_name ?? '—' }}</div>
						<div class="mb-0"><span class="text-muted">Created:</span> {{ $lead->created_at->format('d M Y, h:i A') }}</div>
					</div>
				</div>
			</div>

			<!-- Change status -->
			<div class="card mb-3">
				<div class="card-body">
					<h6 class="mb-3">Update status</h6>
					<form method="POST" action="{{ route('leads.status', $lead) }}">
						@csrf @method('PATCH')
						<select name="status_id" class="form-select mb-2" required>
							@foreach ($statuses as $s)
								<option value="{{ $s->id }}" @selected($lead->current_status_id == $s->id)>{{ $s->name }}</option>
							@endforeach
						</select>
						<textarea name="remarks" rows="2" class="form-control mb-2" placeholder="Remarks (optional)"></textarea>
						<button type="submit" class="btn btn-primary btn-sm w-100">Update status</button>
					</form>
				</div>
			</div>

			<!-- Interested course (drives the weekend masterclass batch) -->
			<div class="card mb-3">
				<div class="card-body">
					<h6 class="mb-3"><i class="ti ti-school me-1"></i> Interested course</h6>
					<form method="POST" action="{{ route('leads.course', $lead) }}">
						@csrf @method('PATCH')
						<select name="interested_course_slug" class="form-select mb-2">
							<option value="">— No course selected —</option>
							@foreach ($lmsCourses as $course)
								<option value="{{ $course->slug }}" @selected($lead->interested_course_slug === $course->slug)>{{ $course->name }}</option>
							@endforeach
						</select>
						<p class="fs-12 text-muted mb-2">When this lead becomes Interested, the masterclass watch link for this course goes out on WhatsApp + email automatically.</p>
						<button type="submit" class="btn btn-outline-primary btn-sm w-100">Save course</button>
					</form>
				</div>
			</div>

			<!-- Manual conversion: allocate the lead into an LMS batch -->
			<div class="card mb-3">
				<div class="card-body">
					<h6 class="mb-2"><i class="ti ti-users-plus me-1"></i> Allocate to Batch</h6>
					@if ($lead->allocated_batch_number)
						<div class="alert alert-success fs-13 py-2 mb-2">
							<i class="ti ti-circle-check me-1"></i>Already allocated to <strong>{{ $lead->allocated_batch_number }}</strong> — student account created &amp; sign-in link sent. Allocating again adds them to another batch too.
						</div>
					@endif
					@if ($lead->masterclass_link_sent_at)
						<p class="fs-12 text-muted mb-1">
							<i class="ti ti-send me-1"></i>Masterclass link sent {{ $lead->masterclass_link_sent_at->format('d M, h:i A') }}
							@if ($lead->masterclass_followup_at)
								· follow-up {{ $lead->masterclass_followup_at->format('d M, h:i A') }}
							@endif
						</p>
					@endif
					@if ($lmsBatches->isEmpty())
						<div class="alert alert-warning fs-12 mb-0">No active LMS batches (or LMS unreachable). Create one on the <a href="{{ route('live-batches.index') }}">Live Batches</a> page first.</div>
					@else
						<form method="POST" action="{{ route('leads.allocate-batch', $lead) }}"
							onsubmit="return confirm('Move {{ $lead->displayName() }} into the selected batch? A student account will be created and credentials sent.');">
							@csrf
							<select name="lms_batch_id" class="form-select mb-2" required>
								<option value="">Select batch…</option>
								@foreach ($lmsBatches as $lmsBatch)
									<option value="{{ $lmsBatch->id }}">
										[{{ ucfirst($lmsBatch->type) }}] {{ $lmsBatch->number }} — {{ $lmsBatch->course?->name }}{{ $lmsBatch->starts_on ? ' · '.$lmsBatch->starts_on->format('d M') : '' }}
									</option>
								@endforeach
							</select>
							<p class="fs-12 text-muted mb-2">Creates their student account in the LMS, seats them as Enrolled, sends batch number + sign-in link on WhatsApp &amp; email (they log in with this number via OTP — no password), and marks this lead <strong>Joined</strong>.</p>
							<button type="submit" class="btn btn-success btn-sm w-100"><i class="ti ti-arrow-right-circle me-1"></i> Move lead into batch</button>
						</form>
					@endif
				</div>
			</div>

			<!-- Assign (HR Manager) -->
			@if ($canAssign)
				<div class="card mb-3">
					<div class="card-body">
						<h6 class="mb-3">Assign to HR</h6>
						<form method="POST" action="{{ route('leads.assign', $lead) }}">
							@csrf
							<select name="assigned_to_user_id" class="form-select mb-2" required>
								<option value="">Select HR user...</option>
								@foreach ($hrUsers as $u)
									<option value="{{ $u->id }}" @selected($lead->assigned_to_user_id == $u->id)>{{ $u->full_name }} ({{ $u->role->role_code ?? '' }})</option>
								@endforeach
							</select>
							<button type="submit" class="btn btn-outline-primary btn-sm w-100">Assign lead</button>
						</form>
					</div>
				</div>
			@endif

			<!-- AI Call -->
			<div class="card mb-3">
				<div class="card-body">
					<h6 class="mb-2"><i class="ti ti-robot me-1"></i> AI Voice Call</h6>
					@if ($callConfigured)
						<p class="fs-13 text-muted">Trigger an automated Caller.Digital voice call to this lead. Results appear below once the call completes.</p>
						<form method="POST" action="{{ route('leads.ai-call', $lead) }}" onsubmit="return confirm('Start an AI call to {{ $lead->mobile }}?');">
							@csrf
							<button type="submit" class="btn btn-primary btn-sm w-100"><i class="ti ti-phone-outgoing me-1"></i> Start AI Call</button>
						</form>
					@else
						<div class="alert alert-warning fs-12 mb-0">Caller.Digital is not configured. Set <code>CALLER_DIGITAL_*</code> in <code>.env</code> to enable AI calls.</div>
					@endif
				</div>
			</div>

			<!-- Manual call log -->
			<div class="card">
				<div class="card-body">
					<h6 class="mb-2"><i class="ti ti-phone me-1"></i> Log a manual call</h6>
					<form method="POST" action="{{ route('leads.manual-call', $lead) }}" enctype="multipart/form-data">
						@csrf
						<div class="row g-2">
							<div class="col-6">
								<label class="form-label fs-12">Status</label>
								<select name="status" class="form-select form-select-sm" required>
									<option value="completed">Completed</option>
									<option value="no_answer">No answer</option>
									<option value="busy">Busy</option>
									<option value="failed">Failed</option>
								</select>
							</div>
							<div class="col-6">
								<label class="form-label fs-12">Duration (sec)</label>
								<input type="number" name="duration_seconds" min="0" class="form-control form-control-sm">
							</div>
							<div class="col-12">
								<label class="form-label fs-12">Disposition</label>
								<input type="text" name="disposition" class="form-control form-control-sm" placeholder="e.g. Interested, Call back later">
							</div>
							<div class="col-12">
								<label class="form-label fs-12">Transcript / notes</label>
								<textarea name="transcript" rows="3" class="form-control form-control-sm"></textarea>
							</div>
							<div class="col-12">
								<label class="form-label fs-12">Audio recording (optional)</label>
								<input type="file" name="audio" accept="audio/*" class="form-control form-control-sm">
							</div>
						</div>
						<button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-2"><i class="ti ti-plus me-1"></i> Save call log</button>
					</form>
				</div>
			</div>
		</div>

		<!-- Right: calls + history -->
		<div class="col-lg-8">
			<!-- AI Lead Analysis -->
			<div class="card mb-3">
				<div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
					<h6 class="mb-0"><i class="ti ti-sparkles me-1 text-primary"></i>AI Lead Analysis</h6>
					@if (count($aiProviders))
						<div class="d-flex align-items-center gap-2">
							<select id="ai-provider" class="form-select form-select-sm" style="width:auto;">
								@foreach ($aiProviders as $key => $label)
									<option value="{{ $key }}">{{ $label }}</option>
								@endforeach
							</select>
							<button type="button" id="ai-analyze-btn" class="btn btn-primary btn-sm">
								<i class="ti ti-analyze me-1"></i>Analyze this lead
							</button>
						</div>
					@endif
				</div>
				<div class="card-body">
					@if (! count($aiProviders))
						<div class="text-muted fs-13">
							<i class="ti ti-info-circle me-1"></i>No AI provider configured yet. Add
							<code>ANTHROPIC_API_KEY</code> (Claude), <code>OPENAI_API_KEY</code> (ChatGPT), or
							<code>KIMI_API_KEY</code> (Kimi) to <code>.env</code> to enable analysis.
						</div>
					@else
						<div id="ai-analysis-status" class="d-none text-muted fs-13 mb-2">
							<span class="spinner-border spinner-border-sm me-2"></span>Analyzing calls, transcripts and history — this can take up to a minute...
						</div>
						<div id="ai-analysis-error" class="alert alert-danger fs-13 py-2 d-none mb-2"></div>
						<div id="ai-analysis-result" class="fs-14 {{ $aiAnalyses->isEmpty() ? 'd-none' : '' }}">
							@if ($aiAnalyses->isNotEmpty())
								@php $latest = $aiAnalyses->first(); @endphp
								<div class="fs-12 text-muted mb-2" id="ai-analysis-meta">
									{{ config("services.ai_analysis.{$latest->provider}.label", $latest->provider) }}
									· {{ $latest->model }} · {{ $latest->created_at->format('d M Y, h:i A') }}
									@if ($latest->requestedBy) · requested by {{ $latest->requestedBy->full_name }} @endif
								</div>
								<div id="ai-analysis-text" data-raw="{{ $latest->analysis }}"></div>
							@else
								<div class="fs-12 text-muted mb-2" id="ai-analysis-meta"></div>
								<div id="ai-analysis-text" data-raw=""></div>
							@endif
						</div>
						@if ($aiAnalyses->isEmpty())
							<div id="ai-analysis-empty" class="text-muted fs-13">
								No analysis yet. Pick a provider and click <strong>Analyze this lead</strong> — the AI reads every call
								transcript and the status timeline, then explains why the lead has or hasn't converted and what to do next.
							</div>
						@endif
					@endif
				</div>
			</div>

			<div class="card mb-3">
				<div class="card-header"><h6 class="mb-0">Call history</h6></div>
				<div class="card-body">
					@php
						$callColors = ['completed' => 'success', 'queued' => 'secondary', 'ringing' => 'info', 'in_progress' => 'info', 'failed' => 'danger', 'no_answer' => 'warning', 'busy' => 'warning', 'cancelled' => 'secondary'];
					@endphp
					@forelse ($lead->calls as $call)
						<div class="border rounded p-3 mb-2">
							<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
								<div>
									<span class="badge badge-soft-{{ $call->type === 'ai' ? 'primary' : 'secondary' }}">
										<i class="ti {{ $call->type === 'ai' ? 'ti-robot' : 'ti-user' }} me-1"></i>{{ $call->type === 'ai' ? 'AI Call' : 'Manual' }}
									</span>
									<span class="badge badge-soft-{{ $callColors[$call->status] ?? 'secondary' }} ms-1">{{ ucfirst(str_replace('_', ' ', $call->status)) }}</span>
									@if ($call->disposition)<span class="badge badge-soft-info ms-1">{{ $call->disposition }}</span>@endif
									@if ($call->sentiment)<span class="badge badge-soft-secondary ms-1">{{ ucfirst($call->sentiment) }}</span>@endif
								</div>
								<span class="fs-12 text-muted">
									{{ optional($call->started_at)->format('d M Y, h:i A') }}
									@if ($call->duration_seconds) · {{ $call->duration_seconds }}s @endif
									@if ($call->initiatedBy) · by {{ $call->initiatedBy->full_name }} @endif
								</span>
							</div>

							@if ($call->recording_url)
								<div class="my-2"><audio controls src="{{ $call->recording_url }}" style="height:32px;max-width:100%;"></audio></div>
							@elseif ($call->audio_path)
								<div class="my-2"><a href="{{ route('leads.call-audio', $call) }}" class="btn btn-sm btn-outline-light"><i class="ti ti-download me-1"></i> Recording</a></div>
							@endif

							@if ($call->transcript)
								<details class="fs-13">
									<summary class="text-primary" style="cursor:pointer;">Transcript</summary>
									<pre class="mt-2 mb-0" style="white-space:pre-wrap;font-family:inherit;">{{ $call->transcript }}</pre>
								</details>
							@endif
						</div>
					@empty
						<div class="text-center py-4 text-muted">No calls yet. Trigger an AI call or log a manual one.</div>
					@endforelse
				</div>
			</div>

			<div class="card">
				<div class="card-header"><h6 class="mb-0">Status history</h6></div>
				<div class="card-body">
					@forelse ($lead->statusHistory as $h)
						<div class="d-flex align-items-start gap-2 mb-2 pb-2 border-bottom">
							<i class="ti ti-point-filled text-primary"></i>
							<div>
								<div class="fw-medium">{{ $h->status->name ?? '—' }}</div>
								@if ($h->remarks)<div class="fs-13 text-muted">{{ $h->remarks }}</div>@endif
								<div class="fs-12 text-muted">{{ $h->changedBy->full_name ?? 'System' }} · {{ $h->created_at->format('d M Y, h:i A') }}</div>
							</div>
						</div>
					@empty
						<div class="text-center py-3 text-muted">No status changes recorded.</div>
					@endforelse
				</div>
			</div>
		</div>
	</div>

@endsection

@push('scripts')
	<script>
		// AI Lead Analysis — renders the markdown-ish AI output and calls the
		// analysis endpoint without a page reload.
		(function () {
			const btn = document.getElementById('ai-analyze-btn');
			if (!btn) return;

			const statusEl = document.getElementById('ai-analysis-status');
			const errorEl = document.getElementById('ai-analysis-error');
			const resultEl = document.getElementById('ai-analysis-result');
			const textEl = document.getElementById('ai-analysis-text');
			const metaEl = document.getElementById('ai-analysis-meta');
			const emptyEl = document.getElementById('ai-analysis-empty');

			function esc(s) {
				const d = document.createElement('div');
				d.textContent = s;
				return d.innerHTML;
			}

			// Minimal markdown: ## headings, **bold**, - bullets. Everything else stays text.
			function renderMarkdown(raw) {
				const html = esc(raw)
					.replace(/^## (.*)$/gm, '<h6 class="mt-3 mb-2 text-primary">$1</h6>')
					.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
					.replace(/^\s*[-•] (.*)$/gm, '<div class="d-flex gap-2 mb-1"><span>•</span><span>$1</span></div>')
					.replace(/^\s*(\d+)\. (.*)$/gm, '<div class="d-flex gap-2 mb-1"><span>$1.</span><span>$2</span></div>')
					.replace(/\n{2,}/g, '<br>');
				return html;
			}

			// Render any analysis that was loaded with the page.
			if (textEl && textEl.dataset.raw) {
				textEl.innerHTML = renderMarkdown(textEl.dataset.raw);
			}

			btn.addEventListener('click', function () {
				const provider = document.getElementById('ai-provider').value;
				btn.disabled = true;
				statusEl.classList.remove('d-none');
				errorEl.classList.add('d-none');
				if (emptyEl) emptyEl.classList.add('d-none');

				fetch("{{ route('leads.ai-analysis', $lead) }}", {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
					},
					body: JSON.stringify({ provider: provider }),
				})
					.then(async res => {
						const data = await res.json();
						if (res.ok && data.success) {
							textEl.innerHTML = renderMarkdown(data.analysis);
							metaEl.textContent = data.provider + ' · ' + (data.model || '') + ' · ' + data.created_at;
							resultEl.classList.remove('d-none');
						} else {
							errorEl.textContent = data.message || 'Analysis failed.';
							errorEl.classList.remove('d-none');
							if (emptyEl) emptyEl.classList.remove('d-none');
						}
					})
					.catch(() => {
						errorEl.textContent = 'Network error — the analysis may take a while; try again.';
						errorEl.classList.remove('d-none');
					})
					.finally(() => {
						btn.disabled = false;
						statusEl.classList.add('d-none');
					});
			});
		})();
	</script>
@endpush
