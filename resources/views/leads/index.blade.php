@extends('layouts.app')

@section('title', 'Leads')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-0">Leads</h4>
			<nav class="fs-13 text-muted">
				<a href="{{ url('/') }}" class="text-muted">Home</a> &gt; <span>CRM / Leads</span>
			</nav>
		</div>
		<div class="d-flex gap-2">
			@if (in_array(auth()->user()->role->role_code ?? '', ['SUPER_ADMIN', 'ADMIN', 'HEAD_OF_OPERATIONS', 'HR_MANAGER'], true))
				<a href="{{ route('meeting-reports.index') }}" class="btn btn-outline-primary">
					<i class="ti ti-report-analytics me-1"></i> Meeting Reports
				</a>
				<a href="{{ route('leads.performance') }}" class="btn btn-outline-primary">
					<i class="ti ti-chart-bar me-1"></i> HR Performance
				</a>
			@endif
			@if ($canCreate)
				<button type="button" class="btn btn-primary" onclick="openLeadModal()">
					<i class="ti ti-plus me-1"></i> Add Lead
				</button>
			@endif
		</div>
	</div>

	@foreach (['success' => 'success', 'error' => 'danger'] as $key => $type)
		@if (session($key))
			<div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
				{{ session($key) }}
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		@endif
	@endforeach

	<div class="card mb-3">
		<div class="card-body">
			<form method="GET" action="{{ route('leads.index') }}" class="row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label">Search</label>
					<input type="text" name="search" class="form-control" placeholder="Name, mobile, email..." value="{{ request('search') }}">
				</div>
				<div class="col-md-3">
					<label class="form-label">Status</label>
					<select name="status_id" class="form-select">
						<option value="">All statuses</option>
						@foreach ($statuses as $s)
							<option value="{{ $s->id }}" @selected((string) request('status_id') === (string) $s->id)>{{ $s->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Source</label>
					<select name="source" class="form-select">
						<option value="">All sources</option>
						@foreach ($sources as $src)
							<option value="{{ $src }}" @selected(request('source') === $src)>{{ ucfirst($src) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Assignment</label>
					<select name="assignment" class="form-select">
						<option value="">All</option>
						<option value="unassigned" @selected(request('assignment') === 'unassigned')>Unassigned</option>
						<option value="assigned" @selected(request('assignment') === 'assigned')>Assigned</option>
						<option value="mine" @selected(request('assignment') === 'mine')>Assigned to me</option>
					</select>
				</div>
				<div class="col-md-1 d-flex gap-2">
					<button type="submit" class="btn btn-primary"><i class="ti ti-filter"></i></button>
					<a href="{{ route('leads.index') }}" class="btn btn-outline-light">Reset</a>
				</div>
			</form>
		</div>
	</div>

	<div class="card">
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Lead</th><th>Mobile</th><th>Source</th><th>Status</th><th>AI Call</th><th>Batch</th><th>Assigned To</th><th>Created</th><th>Action</th>
						</tr>
					</thead>
					<tbody id="leads-tbody">
						@forelse ($leads as $lead)
							<tr>
								<td>
									<a href="{{ route('leads.show', $lead) }}">{{ $lead->name ?: '(no name)' }}</a>
									@if ($lead->email)<div class="fs-12 text-muted">{{ $lead->email }}</div>@endif
								</td>
								<td>{{ $lead->mobile }}</td>
								<td class="text-capitalize">{{ $lead->source ?: 'manual' }}</td>
								<td>
									@if ($lead->status)
										<span class="badge" style="background: {{ $lead->status->color ?: '#6c757d' }}; color:#fff;">{{ $lead->status->name }}</span>
									@else — @endif
								</td>
								<td>
									@php $aiCall = $lead->calls->first(); @endphp
									@if (! $aiCall)
										<span class="badge bg-light text-muted">Not triggered</span>
									@else
										@php
											$aiBadge = match ($aiCall->status) {
												'completed' => 'bg-success',
												'queued', 'ringing', 'in_progress' => 'bg-info',
												'failed' => 'bg-danger',
												default => 'bg-warning',
											};
										@endphp
										<span class="badge {{ $aiBadge }}" title="{{ $aiCall->started_at?->format('d M Y h:i A') }}">
											{{ ucwords(str_replace('_', ' ', $aiCall->status)) }}
										</span>
									@endif
								</td>
								<td>
									@if ($lead->allocated_batch_number)
										<span class="badge badge-soft-success" title="Student account created &amp; credentials sent">
											<i class="ti ti-users me-1"></i>{{ $lead->allocated_batch_number }}
										</span>
									@else
										<span class="badge bg-light text-muted">Not allocated</span>
									@endif
								</td>
								<td>{{ $lead->assignee->full_name ?? '—' }}</td>
								<td>{{ $lead->created_at->format('d M Y') }}</td>
								<td><a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-icon btn-outline-light"><i class="ti ti-eye"></i></a></td>
							</tr>
						@empty
							<tr><td colspan="9" class="text-center py-4">No leads found.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			<div class="mt-3" id="leads-pagination">{{ $leads->links() }}</div>
		</div>
	</div>

	<!-- Add Lead Modal -->
	<div class="modal fade" id="leadModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<form method="POST" action="{{ route('leads.store') }}" id="lead-form">
					@csrf
					<div class="modal-header">
						<h5 class="modal-title">Add Lead</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						@if ($isMediaStrategist)
							<div class="alert alert-info fs-13 py-2"><i class="ti ti-info-circle me-1"></i> As a Media Strategist, only a mobile number is required.</div>
						@endif
						<div id="lead-form-feedback"></div>
						<div class="mb-3">
							<label class="form-label">Mobile <span class="text-danger">*</span></label>
							<input type="text" name="mobile" class="form-control" placeholder="+91..." required>
						</div>
						<div class="mb-3">
							<label class="form-label">Name @unless ($isMediaStrategist)<span class="text-danger">*</span>@endunless</label>
							<input type="text" name="name" class="form-control" @unless ($isMediaStrategist) required @endunless>
						</div>
						<div class="mb-3">
							<label class="form-label">Email</label>
							<input type="email" name="email" class="form-control">
						</div>
						<div class="mb-0">
							<label class="form-label">Campaign (optional)</label>
							<input type="text" name="campaign_name" class="form-control">
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Save Lead</button>
					</div>
				</form>
			</div>
		</div>
	</div>

@endsection

@push('scripts')
	<script>
		function openLeadModal() { new bootstrap.Modal(document.getElementById('leadModal')).show(); }
		@if ($errors->any()) document.addEventListener('DOMContentLoaded', openLeadModal); @endif

		// Fast add: submit via AJAX (no page reload), then clear the form and
		// keep the modal open so the next lead can be typed immediately.
		(function () {
			const form = document.getElementById('lead-form');
			const feedback = document.getElementById('lead-form-feedback');
			if (!form) return;

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				const submitBtn = form.querySelector('button[type="submit"]');
				submitBtn.disabled = true;
				feedback.innerHTML = '';

				fetch(form.action, {
					method: 'POST',
					headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
					body: new FormData(form),
				})
					.then(async res => {
						const data = await res.json();
						if (res.ok && data.success) {
							form.reset();
							feedback.innerHTML = '<div class="alert alert-success fs-13 py-2"><i class="ti ti-check me-1"></i>Lead added — team is being notified in the background. Add another or close.</div>';
							form.querySelector('input[name="mobile"]').focus();
							if (window.refreshLeadsTable) window.refreshLeadsTable();
						} else if (res.status === 422) {
							const messages = Object.values(data.errors || {}).flat().join('<br>');
							feedback.innerHTML = '<div class="alert alert-danger fs-13 py-2">' + (messages || 'Please check the form.') + '</div>';
						} else {
							feedback.innerHTML = '<div class="alert alert-danger fs-13 py-2">Could not save the lead — please try again.</div>';
						}
					})
					.catch(() => {
						feedback.innerHTML = '<div class="alert alert-danger fs-13 py-2">Network problem — the lead was not saved. Try again.</div>';
					})
					.finally(() => { submitBtn.disabled = false; });
			});
		})();

		// Auto-refresh: poll for changes to the visible lead set every 10s and
		// swap the table in place (filters, pagination, and scroll preserved).
		(function () {
			const POLL_URL = "{{ route('leads.poll') }}";
			let fingerprint = null;

			function typingOrModalOpen() {
				if (document.querySelector('.modal.show')) return true;
				const el = document.activeElement;
				return el && ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName);
			}

			async function refreshTable() {
				const html = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.text());
				const doc = new DOMParser().parseFromString(html, 'text/html');
				const newBody = doc.getElementById('leads-tbody');
				const newPagination = doc.getElementById('leads-pagination');
				if (newBody) document.getElementById('leads-tbody').innerHTML = newBody.innerHTML;
				if (newPagination) document.getElementById('leads-pagination').innerHTML = newPagination.innerHTML;
			}
			// Let the add-lead modal trigger an immediate refresh after saving.
			window.refreshLeadsTable = () => refreshTable().catch(() => {});

			async function check() {
				try {
					const res = await fetch(POLL_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
					if (!res.ok) return;
					const data = await res.json();
					const key = data.latest_id + '|' + data.total + '|' + data.last_change;

					if (fingerprint === null) {
						fingerprint = key;
						return;
					}
					if (key !== fingerprint && !typingOrModalOpen()) {
						fingerprint = key;
						await refreshTable();
					}
				} catch (e) { /* offline or server restart — next poll retries */ }
			}

			check();
			setInterval(check, 10000);
		})();
	</script>
@endpush
