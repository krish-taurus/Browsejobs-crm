@extends('layouts.app')

@section('title', 'Mentors')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Mentors</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	@if (session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger">{{ session('error') }}</div>
	@endif

	<!-- Add / update a mentor: WHO mentors is managed here; the mentor sets
	     their own weekly availability in the LMS "My mentoring" hub. -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-user-plus me-2"></i>Add / Update Mentor</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('mentors.store') }}" class="row g-3 align-items-end">
				@csrf
				<div class="col-md-3">
					<label class="form-label">Tech Mentor <span class="text-danger">*</span></label>
					<select name="user" class="form-select" required>
						<option value="">Select team member…</option>
						@foreach ($staff as $member)
							<option value="{{ $member->id }}">{{ $member->full_name }} ({{ $member->email }})</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label">Headline</label>
					<input type="text" name="headline" class="form-control" maxlength="190" placeholder="e.g. Senior Data Engineer">
				</div>
				<div class="col-md-3">
					<label class="form-label">Expertise (comma-separated)</label>
					<input type="text" name="expertise" class="form-control" maxlength="500" placeholder="SQL, Spark, Interview prep">
				</div>
				<div class="col-md-2">
					<label class="form-label">Status</label>
					<select name="active" class="form-select">
						<option value="1">Active</option>
						<option value="0">Inactive</option>
					</select>
				</div>
				<div class="col-md-1">
					<button type="submit" class="btn btn-primary w-100">Save</button>
				</div>
				<div class="col-md-8">
					<label class="form-label">Courses covered <span class="text-muted fs-12">(none selected = generalist, visible to all students)</span></label>
					<div class="d-flex gap-3 flex-wrap">
						@foreach ($courses as $course)
							<div class="form-check">
								<input type="checkbox" name="course_slugs[]" value="{{ $course->slug }}" class="form-check-input" id="mc-{{ $course->id }}">
								<label class="form-check-label fs-13" for="mc-{{ $course->id }}">{{ $course->name }}</label>
							</div>
						@endforeach
					</div>
				</div>
				<div class="col-12">
					<span class="text-muted fs-12">Saving an existing mentor updates their profile. Mentors set their own weekly slots &amp; days off in the LMS — students book those slots with session credits; the mentor connects directly (no Zoom for 1:1s).</span>
				</div>
			</form>
		</div>
	</div>

	<!-- Mentor pool -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-heart-handshake me-2"></i>Mentor Pool ({{ $mentors->count() }})</h5></div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Mentor</th>
							<th>Headline</th>
							<th>Expertise</th>
							<th>Courses</th>
							<th>Weekly Slots</th>
							<th>Rating</th>
							<th>Booked</th>
							<th>Completed</th>
							<th>No-shows</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($mentors as $mentor)
							<tr>
								<td>
									<div class="fw-medium">{{ $mentor->user?->name ?? '—' }}</div>
									<div class="text-muted fs-12">{{ $mentor->user?->email }}</div>
								</td>
								<td>{{ $mentor->headline ?? '—' }}</td>
								<td>
									@foreach ($mentor->expertise_tags ?? [] as $tag)
										<span class="badge badge-soft-secondary">{{ $tag }}</span>
									@endforeach
								</td>
								<td>
									@php $covered = $courses->whereIn('id', $mentor->course_ids ?? []); @endphp
									@if ($covered->isEmpty())
										<span class="badge badge-soft-info">Generalist</span>
									@else
										@foreach ($covered as $course)
											<span class="badge badge-soft-primary">{{ $course->name }}</span>
										@endforeach
									@endif
								</td>
								<td>
									@if ($mentor->availabilities_count > 0)
										{{ $mentor->availabilities_count }} window(s)
									@else
										<span class="badge badge-soft-warning" title="No weekly slots — students cannot book this mentor yet">None set</span>
									@endif
									<button class="btn btn-sm btn-outline-primary ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#slots-{{ $mentor->id }}">
										<i class="ti ti-clock"></i> Set
									</button>
								</td>
								<td>{{ $ratings[$mentor->id] ?? '—' }}@if (isset($ratings[$mentor->id])) ★ @endif</td>
								<td>{{ $mentor->booked_count }}</td>
								<td>{{ $mentor->completed_count }}</td>
								<td>
									@if ($mentor->no_show_count > 0)
										<span class="badge badge-soft-danger">{{ $mentor->no_show_count }}</span>
									@else
										0
									@endif
								</td>
								<td>
									<form method="POST" action="{{ route('mentors.store') }}">
										@csrf
										<input type="hidden" name="user" value="{{ $mentor->user?->email }}">
										<input type="hidden" name="active" value="{{ $mentor->is_active ? 0 : 1 }}">
										<button type="submit" class="badge border-0 {{ $mentor->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}"
											title="Click to {{ $mentor->is_active ? 'deactivate' : 'activate' }}">
											{{ $mentor->is_active ? 'Active' : 'Inactive' }}
										</button>
									</form>
								</td>
							</tr>
							<tr class="collapse" id="slots-{{ $mentor->id }}">
								<td colspan="10" class="bg-light">
									@php
										$dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
										$byDay = $mentor->availabilities->groupBy('weekday');
										$minToTime = fn ($m) => sprintf('%02d:%02d', intdiv((int) $m, 60), ((int) $m) % 60);
									@endphp
									<form method="POST" action="{{ route('mentors.slots', $mentor->id) }}">
										@csrf
										<div class="d-flex gap-3 flex-wrap align-items-end">
											@foreach ($dayNames as $day => $name)
												@php $window = $byDay->get($day)?->first(); @endphp
												<div>
													<label class="form-label fs-12 fw-medium mb-1">{{ $name }}</label>
													<div class="d-flex gap-1 align-items-center">
														<input type="time" name="day_start[{{ $day }}]" class="form-control form-control-sm"
															value="{{ $window ? $minToTime($window->start_minute) : '' }}">
														<span class="fs-12 text-muted">–</span>
														<input type="time" name="day_end[{{ $day }}]" class="form-control form-control-sm"
															value="{{ $window ? $minToTime($window->end_minute) : '' }}">
													</div>
												</div>
											@endforeach
											<button type="submit" class="btn btn-sm btn-primary">Save Slots</button>
										</div>
										<span class="text-muted fs-12 d-block mt-2">Times in IST. Students book 30-minute slots inside these windows. Leave a day blank for no availability that day; clear all to make the mentor unbookable.</span>
									</form>
								</td>
							</tr>
						@empty
							<tr><td colspan="10" class="text-center py-4">No mentors yet — add a staff member above.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Confirmed upcoming bookings: the ops list — who meets whom, when. -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-calendar-check me-2"></i>Upcoming Bookings ({{ $upcomingSessions->count() }})</h5></div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>When</th>
							<th>Mentor</th>
							<th>Student</th>
							<th>Student Phone</th>
							<th>Purpose</th>
							<th>Duration</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($upcomingSessions as $session)
							<tr>
								<td class="fw-medium">{{ $session->starts_at?->format('D, d M · h:i A') }}</td>
								<td>{{ $session->mentor?->user?->name ?? '—' }}</td>
								<td>{{ $session->student?->name ?? '—' }}</td>
								<td>{{ $session->student?->phone ?? '—' }}</td>
								<td>{{ ucfirst($session->purpose ?? 'mentoring') }}</td>
								<td>{{ $session->duration_minutes }} min</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center py-4">No upcoming bookings. Students book from their Mentors page once a mentor has weekly slots set.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			<span class="text-muted fs-12">Both sides get a confirmation (branded WhatsApp card + email) and T-24h / T-1h reminders — the mentor calls the student directly at the slot.</span>
		</div>
	</div>

	<!-- Session history -->
	<div class="card">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-calendar-heart me-2"></i>Recent Sessions</h5></div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>When</th>
							<th>Mentor</th>
							<th>Student</th>
							<th>Purpose</th>
							<th>Status</th>
							<th>Feedback</th>
							<th>Student Rating</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($recentSessions as $session)
							<tr>
								<td>{{ $session->starts_at?->format('d M Y, h:i A') }}</td>
								<td>{{ $session->mentor?->user?->name ?? '—' }}</td>
								<td>
									{{ $session->student?->name ?? '—' }}
									@if ($session->student?->phone)<div class="text-muted fs-12">{{ $session->student->phone }}</div>@endif
								</td>
								<td>{{ $session->purpose ?? '—' }}</td>
								<td>
									<span class="badge {{ ['booked' => 'badge-soft-info', 'completed' => 'badge-soft-success', 'cancelled' => 'badge-soft-secondary', 'no_show' => 'badge-soft-danger'][$session->status] ?? 'badge-soft-secondary' }}">
										{{ ucfirst(str_replace('_', ' ', $session->status)) }}
									</span>
									@if ($session->no_show_side)<span class="text-muted fs-12">({{ $session->no_show_side }})</span>@endif
								</td>
								<td>{{ $session->feedback_score !== null ? $session->feedback_score.'/100' : '—' }}</td>
								<td>{{ $session->student_rating !== null ? $session->student_rating.' ★' : '—' }}</td>
							</tr>
						@empty
							<tr><td colspan="7" class="text-center py-4">No past sessions yet.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

@endsection
