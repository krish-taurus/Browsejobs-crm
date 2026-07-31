@extends('layouts.app')

@section('title', 'Support Tickets')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Support Tickets</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100"><div class="card-body position-relative">
				<p class="fw-medium mb-1">Open / In Progress</p>
				<h4 class="mb-0 text-info">{{ $stats['open'] }}</h4>
				<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3"><i class="ti ti-inbox text-info"></i></span>
			</div></div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100"><div class="card-body position-relative">
				<p class="fw-medium mb-1">Waiting on Student</p>
				<h4 class="mb-0 text-warning">{{ $stats['waiting'] }}</h4>
				<span class="avatar avatar-rounded bg-warning-transparent position-absolute top-0 end-0 m-3"><i class="ti ti-hourglass text-warning"></i></span>
			</div></div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100"><div class="card-body position-relative">
				<p class="fw-medium mb-1">SLA Breached</p>
				<h4 class="mb-0 text-danger">{{ $stats['breached'] }}</h4>
				<span class="avatar avatar-rounded bg-danger-transparent position-absolute top-0 end-0 m-3"><i class="ti ti-alert-triangle text-danger"></i></span>
			</div></div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100"><div class="card-body position-relative">
				<p class="fw-medium mb-1">Resolved (7 days)</p>
				<h4 class="mb-0 text-success">{{ $stats['resolved_week'] }}</h4>
				<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3"><i class="ti ti-circle-check text-success"></i></span>
			</div></div>
		</div>
	</div>
	<!-- End Stat Cards -->

	<!-- Filters -->
	<div class="card mb-3"><div class="card-body">
		<form method="GET" action="{{ route('support-tickets.index') }}" class="row g-3 align-items-end">
			<div class="col-md-4">
				<label class="form-label">Search</label>
				<input type="text" name="search" class="form-control" placeholder="Subject, reference or student..." value="{{ request('search') }}">
			</div>
			<div class="col-md-4">
				<label class="form-label">Status</label>
				<select name="status" class="form-select">
					<option value="">All</option>
					@foreach (['open' => 'Open', 'in_progress' => 'In progress', 'waiting_on_student' => 'Waiting on student', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)
						<option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-4 d-flex gap-2">
				<button type="submit" class="btn btn-primary">Apply</button>
				<a href="{{ route('support-tickets.index') }}" class="btn btn-light">Reset</a>
			</div>
		</form>
	</div></div>
	<!-- End Filters -->

	<!-- Queue -->
	<div class="card">
		<div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-headset me-1"></i>Tickets ({{ $tickets->count() }})</h5></div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-hover mb-0 align-middle">
					<thead class="table-white">
						<tr>
							<th>Ref</th>
							<th>Student</th>
							<th>Subject</th>
							<th>Priority</th>
							<th>Status</th>
							<th>Messages</th>
							<th>Opened</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($tickets as $t)
							<tr class="{{ $t->breached_at !== null && ! in_array($t->status, ['resolved', 'closed']) ? 'table-danger' : '' }}">
								<td class="fw-medium">{{ $t->reference }}</td>
								<td>
									<div class="fw-medium">{{ $t->student?->name ?? '—' }}</div>
									<div class="text-muted fs-12">{{ $t->student?->phone ?? '' }}</div>
								</td>
								<td style="max-width: 260px;"><span class="d-inline-block text-truncate" style="max-width: 250px;">{{ $t->subject }}</span></td>
								<td>
									<span class="badge {{ ['urgent' => 'badge-soft-danger', 'high' => 'badge-soft-danger', 'medium' => 'badge-soft-warning', 'low' => 'badge-soft-secondary'][$t->priority] ?? 'badge-soft-secondary' }}">{{ ucfirst($t->priority ?? 'normal') }}</span>
									@if ($t->ai_priority_raised)
										<div class="text-danger fs-12" title="The AI triage flagged this ticket as more urgent than filed">⚠ AI raised</div>
									@endif
								</td>
								<td><span class="badge {{ ['open' => 'badge-soft-info', 'in_progress' => 'badge-soft-primary', 'waiting_on_student' => 'badge-soft-warning', 'resolved' => 'badge-soft-success', 'closed' => 'badge-soft-secondary'][$t->status] ?? 'badge-soft-secondary' }}">{{ ucwords(str_replace('_', ' ', $t->status)) }}</span></td>
								<td class="text-muted">{{ $t->messages->count() }}</td>
								<td class="text-muted fs-12">{{ $t->created_at?->diffForHumans() }}</td>
								<td><a href="{{ route('support-tickets.show', $t->id) }}" class="btn btn-sm btn-primary"><i class="ti ti-message me-1"></i>Open</a></td>
							</tr>
						@empty
							<tr><td colspan="8" class="text-center text-muted py-4">No tickets — inbox zero 🎉</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

@endsection
