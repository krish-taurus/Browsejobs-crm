@extends('layouts.app')

@section('title', 'Masterclasses')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Masterclasses</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	@if (session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger">{{ session('error') }}</div>
	@endif

	<!-- Recorded masterclass per course: streams daily as a simulated-live
	     showing, so a Monday lead never waits for Saturday's live session. -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-video me-2"></i>Masterclass Recordings (daily "live" showing)</h5></div>
		<div class="card-body">
			<p class="text-muted fs-13 mb-3">
				Each course's recorded masterclass plays <strong>as if live, every day at 8:00 PM</strong> on the public watch page
				(<code>/masterclass/watch/&lt;course&gt;</code>) — with a countdown, no seeking, and a join-mid-stream experience.
				Interested leads get this link on WhatsApp + email automatically. Paste a YouTube (unlisted) or direct MP4 URL.
			</p>
			@foreach ($courses as $course)
				<form method="POST" action="{{ route('masterclasses.video') }}" class="row g-2 align-items-center mb-2">
					@csrf
					<input type="hidden" name="course_slug" value="{{ $course->slug }}">
					<div class="col-md-3">
						<span class="fw-medium">{{ $course->name }}</span>
						@if ($course->masterclass_video_url)
							<span class="badge badge-soft-success ms-1">Set</span>
						@else
							<span class="badge badge-soft-warning ms-1">Missing</span>
						@endif
					</div>
					<div class="col-md-7">
						<input type="url" name="video_url" class="form-control form-control-sm"
							placeholder="https://youtu.be/… or https://…/masterclass.mp4 (leave empty to clear)"
							value="{{ $course->masterclass_video_url }}">
					</div>
					<div class="col-md-2">
						<button type="submit" class="btn btn-sm btn-outline-primary w-100">Save</button>
					</div>
				</form>
			@endforeach
		</div>
	</div>

	<!-- Plan a masterclass in advance -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-calendar-plus me-2"></i>Plan a Masterclass</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('masterclasses.store') }}" class="row g-3 align-items-end">
				@csrf
				<div class="col-md-3">
					<label class="form-label">Course</label>
					<select name="course_slug" class="form-select" required>
						<option value="">Select course...</option>
						@foreach ($courses as $course)
							<option value="{{ $course->slug ?? '' }}">{{ $course->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Date</label>
					<input type="date" name="date" class="form-control" min="{{ now()->toDateString() }}" required>
				</div>
				<div class="col-md-2">
					<label class="form-label">Start Time</label>
					<input type="time" name="time" class="form-control" required>
				</div>
				<div class="col-md-2">
					<label class="form-label">Duration (min)</label>
					<input type="number" name="duration" class="form-control" value="90" min="15" max="480">
				</div>
				<div class="col-md-1">
					<label class="form-label">Seats</label>
					<input type="number" name="capacity" class="form-control" placeholder="∞" min="1">
				</div>
				<div class="col-md-2">
					<button type="submit" class="btn btn-primary w-100"><i class="ti ti-calendar-plus me-1"></i> Plan</button>
				</div>
			</form>
			<p class="fs-12 text-muted mb-0 mt-2">Interested, registered students for the course are auto-enrolled by the funnel — planning ahead just fixes the slot before they arrive.</p>
		</div>
	</div>

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Total Masterclasses</p>
					<h4 class="mb-0">{{ number_format($totalMasterclasses) }}</h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-presentation text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Active</p>
					<h4 class="mb-0">{{ number_format($activeMasterclasses) }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-player-play text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Total Registrations</p>
					<h4 class="mb-0">{{ number_format($totalRegistrations) }}</h4>
					<span class="avatar avatar-rounded bg-primary-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-users text-primary"></i>
					</span>
				</div>
			</div>
		</div>
	</div>
	<!-- End Stat Cards -->

	<!-- Filters -->
	<div class="card mb-3">
		<div class="card-body">
			<form method="GET" action="{{ route('masterclasses.index') }}" class="row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label">Search</label>
					<input type="text" name="search" class="form-control" placeholder="Batch number or course..."
						value="{{ request('search') }}">
				</div>
				<div class="col-md-3">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="">All status</option>
						<option value="active" @selected(request('status') === 'active')>Active</option>
						<option value="completed" @selected(request('status') === 'completed')>Completed</option>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label">Course</label>
					<select name="course_id" class="form-select">
						<option value="">All courses</option>
						@foreach ($courses as $course)
							<option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2 d-flex gap-2">
					<button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1"></i> Filter</button>
					<a href="{{ route('masterclasses.index') }}" class="btn btn-outline-light">Reset</a>
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
							<th>Batch</th>
							<th>Course</th>
							<th>Class Slot</th>
							<th>Registrations</th>
							<th>Status</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($batches as $batch)
							@php $session = $batch->liveSessions->firstWhere('status', 'scheduled') ?? $batch->liveSessions->first(); @endphp
							<tr>
								<td><span class="fw-medium">{{ $batch->number }}</span></td>
								<td>{{ $batch->course?->name ?? '—' }}</td>
								<td>
									@if ($session)
										{{ $session->scheduled_start?->format('D, d M Y · h:i A') }}
										@if ($session->status === 'cancelled')<span class="badge badge-soft-danger ms-1">Cancelled</span>@endif
									@elseif ($batch->starts_on)
										{{ $batch->starts_on->format('D, d M Y') }} <span class="badge badge-soft-warning ms-1">No time set</span>
									@else
										—
									@endif
								</td>
								<td>{{ $batch->students_count }}@if ($batch->capacity) / {{ $batch->capacity }}@endif</td>
								<td>
									<span class="badge {{ ['active' => 'badge-soft-success', 'completed' => 'badge-soft-info', 'cancelled' => 'badge-soft-danger'][$batch->status] ?? 'badge-soft-secondary' }}">
										{{ ucfirst($batch->status) }}
									</span>
								</td>
								<td class="d-flex gap-1 flex-wrap">
									<a href="{{ route('live-batches.show', $batch->id) }}" class="btn btn-sm btn-outline-light">
										<i class="ti ti-users"></i> Attendees
									</a>
									@if ($batch->status === 'active')
										<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#resched-{{ $batch->id }}">
											<i class="ti ti-calendar-time"></i> {{ $session ? 'Reschedule' : 'Set Time' }}
										</button>
										<form method="POST" action="{{ route('masterclasses.cancel', $batch->id) }}"
											onsubmit="return confirm('Cancel {{ $batch->number }}? Every enrolled student and trainer will be notified.');">
											@csrf @method('DELETE')
											<input type="hidden" name="reason" value="Masterclass cancelled by the team">
											<button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-x"></i> Cancel</button>
										</form>
									@endif
								</td>
							</tr>
							@if ($batch->status === 'active')
								<tr class="collapse" id="resched-{{ $batch->id }}">
									<td colspan="6" class="bg-light">
										<form method="POST" action="{{ route('masterclasses.reschedule', $batch->id) }}" class="row g-2 align-items-end">
											@csrf @method('PATCH')
											<div class="col-md-2">
												<label class="form-label fs-12">New date</label>
												<input type="date" name="date" class="form-control form-control-sm" min="{{ now()->toDateString() }}"
													value="{{ $batch->starts_on?->toDateString() }}" required>
											</div>
											<div class="col-md-2">
												<label class="form-label fs-12">New time</label>
												<input type="time" name="time" class="form-control form-control-sm"
													value="{{ $session?->scheduled_start?->format('H:i') }}" required>
											</div>
											<div class="col-md-4">
												<label class="form-label fs-12">Reason (sent to students)</label>
												<input type="text" name="reason" class="form-control form-control-sm" placeholder="Rescheduled by the team">
											</div>
											<div class="col-md-2">
												<button type="submit" class="btn btn-sm btn-primary w-100">Save new slot</button>
											</div>
										</form>
									</td>
								</tr>
							@endif
						@empty
							<tr>
								<td colspan="6" class="text-center py-4">No masterclasses yet — plan one above, or let the weekend funnel create them from course interest.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<div class="mt-3">
				{{ $batches->links() }}
			</div>
		</div>
	</div>

@endsection
