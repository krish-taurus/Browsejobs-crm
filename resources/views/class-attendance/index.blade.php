@extends('layouts.app')

@section('title', 'Class Attendance')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Class Attendance</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Total Classes</p>
					<h4 class="mb-0">{{ number_format($totalSessions) }}</h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-calendar-event text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Completed</p>
					<h4 class="mb-0">{{ number_format($completedSessions) }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-circle-check text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Upcoming</p>
					<h4 class="mb-0">{{ number_format($upcomingSessions) }}</h4>
					<span class="avatar avatar-rounded bg-warning-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-clock text-warning"></i>
					</span>
				</div>
			</div>
		</div>
	</div>
	<!-- End Stat Cards -->

	<!-- Filters -->
	<div class="card mb-3">
		<div class="card-body">
			<form method="GET" action="{{ route('class-attendance.index') }}" class="row g-3 align-items-end">
				<div class="col-md-3">
					<label class="form-label">Search</label>
					<input type="text" name="search" class="form-control" placeholder="Class title..." value="{{ request('search') }}">
				</div>
				<div class="col-md-3">
					<label class="form-label">Batch</label>
					<select name="batch_id" class="form-select">
						<option value="">All batches</option>
						@foreach ($batchOptions as $batchOption)
							<option value="{{ $batchOption->id }}" @selected(request('batch_id') == $batchOption->id)>
								{{ $batchOption->number }} — {{ $batchOption->course?->name }}
							</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="">All status</option>
						<option value="scheduled" @selected(request('status') === 'scheduled')>Scheduled</option>
						<option value="completed" @selected(request('status') === 'completed')>Completed</option>
						<option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Date</label>
					<input type="date" name="date" class="form-control" value="{{ request('date') }}">
				</div>
				<div class="col-md-2 d-flex gap-2">
					<button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1"></i> Filter</button>
					<a href="{{ route('class-attendance.index') }}" class="btn btn-outline-light">Reset</a>
				</div>
			</form>
		</div>
	</div>
	<!-- End Filters -->

	<div class="card">
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Class</th>
							<th>Batch</th>
							<th>Scheduled</th>
							<th>Attendees</th>
							<th>Avg Attendance</th>
							<th>Status</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($sessions as $session)
							<tr>
								<td>
									<span class="fw-medium">{{ $session->title }}</span>
									<span class="badge badge-soft-secondary ms-1">{{ ucfirst($session->kind) }}</span>
								</td>
								<td>{{ $session->batch?->number }} <span class="text-muted fs-12">{{ $session->batch?->course?->name }}</span></td>
								<td>{{ $session->scheduled_start?->format('d M Y, h:i A') ?? '—' }}</td>
								<td>{{ $session->attendees_count }}</td>
								<td>
									@if ($session->attendees_count > 0)
										<span class="badge {{ $session->avg_attended_pct >= 75 ? 'badge-soft-success' : ($session->avg_attended_pct >= 40 ? 'badge-soft-warning' : 'badge-soft-danger') }}">
											{{ round($session->avg_attended_pct) }}%
										</span>
									@else
										—
									@endif
								</td>
								<td>
									<span class="badge {{ ['completed' => 'badge-soft-success', 'ended' => 'badge-soft-success', 'scheduled' => 'badge-soft-info', 'cancelled' => 'badge-soft-danger'][$session->status] ?? 'badge-soft-secondary' }}">
										{{ ucfirst($session->status) }}
									</span>
								</td>
								<td>
									<a href="{{ route('class-attendance.show', $session->id) }}" class="btn btn-sm btn-outline-light">
										<i class="ti ti-eye"></i> View
									</a>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="7" class="text-center py-4">No classes found in the LMS.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<div class="mt-3">
				{{ $sessions->links() }}
			</div>
		</div>
	</div>

@endsection
