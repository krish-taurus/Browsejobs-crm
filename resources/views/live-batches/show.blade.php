@extends('layouts.app')

@section('title', 'Batch ' . $batch->number)

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-1">{{ $batch->number }}</h4>
			<span class="text-muted">{{ $batch->course?->name }}</span>
		</div>
		<a href="{{ $batch->type === 'masterclass' ? route('masterclasses.index') : route('live-batches.index') }}" class="btn btn-outline-light">
			<i class="ti ti-arrow-left me-1"></i> Back to Batches
		</a>
	</div>

	@if (session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger">{{ session('error') }}</div>
	@endif

	<!-- Batch summary -->
	<div class="card mb-3">
		<div class="card-body">
			<div class="row g-3">
				<div class="col-md-2 col-6">
					<p class="text-muted mb-1 fs-13">Type</p>
					<span class="badge {{ ['paid' => 'badge-soft-primary', 'bootcamp' => 'badge-soft-info'][$batch->type] ?? 'badge-soft-warning' }}">
						{{ ucfirst($batch->type) }}
					</span>
				</div>
				<div class="col-md-2 col-6">
					<p class="text-muted mb-1 fs-13">Status</p>
					<span class="badge {{ ['active' => 'badge-soft-success', 'completed' => 'badge-soft-info'][$batch->status] ?? 'badge-soft-secondary' }}">
						{{ ucfirst($batch->status) }}
					</span>
				</div>
				<div class="col-md-2 col-6">
					<p class="text-muted mb-1 fs-13">Trainer</p>
					<span class="fw-medium">{{ $batch->trainer?->name ?? '—' }}</span>
				</div>
				<div class="col-md-2 col-6">
					<p class="text-muted mb-1 fs-13">Students</p>
					<span class="fw-medium">{{ $batch->members->count() }}@if ($batch->capacity) / {{ $batch->capacity }}@endif</span>
				</div>
				<div class="col-md-2 col-6">
					<p class="text-muted mb-1 fs-13">Starts</p>
					<span class="fw-medium">{{ $batch->starts_on?->format('d M Y') ?? '—' }}</span>
				</div>
				<div class="col-md-2 col-6">
					<p class="text-muted mb-1 fs-13">Ends</p>
					<span class="fw-medium">{{ $batch->ends_on?->format('d M Y') ?? '—' }}</span>
				</div>
			</div>
		</div>
	</div>

	<!-- Manage: edit batch + announce (LMS executes, students notified) -->
	<div class="row">
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-settings me-2"></i>Edit Batch</h5></div>
				<div class="card-body">
					<form method="POST" action="{{ route('live-batches.update', $batch->id) }}" class="row g-3 align-items-end">
						@csrf @method('PATCH')
						<div class="col-md-3">
							<label class="form-label">Seats</label>
							<input type="number" name="capacity" class="form-control" min="0" value="{{ $batch->capacity }}" placeholder="No cap">
						</div>
						<div class="col-md-3">
							<label class="form-label">Status</label>
							<select name="status" class="form-select">
								@foreach (['active', 'completed', 'cancelled'] as $status)
									<option value="{{ $status }}" @selected($batch->status === $status)>{{ ucfirst($status) }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Type</label>
							<select name="type" class="form-select">
								@foreach (['masterclass', 'bootcamp', 'paid', 'self_paced'] as $batchType)
									<option value="{{ $batchType }}" @selected($batch->type === $batchType)>{{ ucfirst(str_replace('_', '-', $batchType)) }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-3">
							<label class="form-label">Starts</label>
							<input type="date" name="starts_on" class="form-control" value="{{ $batch->starts_on?->toDateString() }}">
						</div>
						<div class="col-md-3">
							<label class="form-label">Ends</label>
							<input type="date" name="ends_on" class="form-control" value="{{ $batch->ends_on?->toDateString() }}">
						</div>
						<div class="col-12">
							<button type="submit" class="btn btn-primary">Save Batch</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-speakerphone me-2"></i>Message this Batch</h5></div>
				<div class="card-body">
					<form method="POST" action="{{ route('live-batches.announce', $batch->id) }}" class="row g-3">
						@csrf
						<div class="col-md-5">
							<label class="form-label">Subject</label>
							<input type="text" name="subject" class="form-control" placeholder="Batch update" maxlength="150">
						</div>
						<div class="col-md-7">
							<label class="form-label">Message <span class="text-danger">*</span></label>
							<input type="text" name="message" class="form-control" required maxlength="1000" placeholder="e.g. Tomorrow's class starts 30 minutes early">
						</div>
						<div class="col-12">
							<button type="submit" class="btn btn-primary"
								onclick="return confirm('Send this message to every student of {{ $batch->number }} on WhatsApp + email?');">
								Send to all students
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Add a student (LMS finds or creates the account) -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-user-plus me-2"></i>Add a Student</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('live-batches.students.store', $batch->id) }}" class="row g-3 align-items-end">
				@csrf
				<div class="col-md-3">
					<label class="form-label">Name <span class="text-danger">*</span></label>
					<input type="text" name="name" class="form-control" required maxlength="255">
				</div>
				<div class="col-md-2">
					<label class="form-label">Phone</label>
					<input type="text" name="phone" class="form-control" maxlength="20" placeholder="+91…">
				</div>
				<div class="col-md-3">
					<label class="form-label">Email</label>
					<input type="email" name="email" class="form-control" maxlength="255">
				</div>
				<div class="col-md-2">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="enrolled">Enrolled</option>
						<option value="reserved">Reserved</option>
						<option value="payment_pending">Payment pending</option>
					</select>
				</div>
				<div class="col-md-2">
					<button type="submit" class="btn btn-primary w-100">Add Student</button>
				</div>
				<div class="col-12">
					<div class="form-check">
						<input type="checkbox" name="credentials" value="1" class="form-check-input" id="send-credentials" checked>
						<label class="form-check-label fs-13" for="send-credentials">Send batch number + OTP sign-in link on WhatsApp &amp; email (no password — students log in with a one-time code)</label>
					</div>
				</div>
			</form>
		</div>
	</div>

	<!-- Roster with per-student performance -->
	<div class="card mb-3">
		<div class="card-header">
			<h5 class="mb-0"><i class="ti ti-users me-2"></i>Student Roster</h5>
		</div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Student</th>
							<th>Status</th>
							<th>Attendance</th>
							<th>Quiz Avg</th>
							<th>Assignments</th>
							<th>Mocks</th>
							<th>Engagement</th>
							<th>Dropout Risk</th>
							<th>Fees Paid</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($batch->members as $member)
							@php
								$uid = $member->user_id;
								$att = $attendanceStats->get($uid);
								$quiz = $quizStats->get($uid);
								$mock = $mockStats->get($uid);
								$score = $latestScores->get($uid);
								$plan = $feePlans->get($uid);
								$totalSessions = $batch->liveSessions->count();
								$paidPaise = $plan?->instalments->where('status', 'paid')->sum('amount_paise') ?? 0;
								$payablePaise = $plan ? max(0, $plan->total_paise - $plan->discount_paise) : 0;
							@endphp
							<tr>
								<td>
									<div class="d-flex align-items-center gap-2">
										<span class="avatar avatar-rounded bg-light text-dark">
											{{ strtoupper(substr($member->user?->name ?? '?', 0, 1)) }}
										</span>
										<div>
											<div class="fw-medium">{{ $member->user?->name ?? 'Unknown' }}</div>
											<div class="text-muted fs-12">{{ $member->user?->email ?? '' }}</div>
										</div>
									</div>
								</td>
								<td>
									<span class="badge {{ ['enrolled' => 'badge-soft-success', 'completed' => 'badge-soft-info', 'transferred' => 'badge-soft-warning'][$member->status] ?? 'badge-soft-secondary' }}">
										{{ ucfirst($member->status) }}
									</span>
								</td>
								<td>
									@if ($totalSessions > 0)
										{{ $att->attended_sessions ?? 0 }}/{{ $totalSessions }}
										<span class="text-muted fs-12">({{ (int) ($att->avg_pct ?? 0) }}%)</span>
									@else
										—
									@endif
								</td>
								<td>
									@if ($quiz && $quiz->attempts > 0)
										{{ (int) $quiz->avg_score }}% <span class="text-muted fs-12">({{ $quiz->attempts }})</span>
									@else
										—
									@endif
								</td>
								<td>{{ $submissionCounts->get($uid, 0) }}</td>
								<td>
									@if ($mock && $mock->total > 0)
										{{ $mock->total }} <span class="text-muted fs-12">(avg {{ (int) $mock->avg_score }})</span>
									@else
										—
									@endif
								</td>
								<td>
									@if ($score)
										<span class="badge {{ $score->engagement >= 70 ? 'badge-soft-success' : ($score->engagement >= 40 ? 'badge-soft-warning' : 'badge-soft-danger') }}">
											{{ $score->engagement }}
										</span>
									@else
										—
									@endif
								</td>
								<td>
									@if ($score)
										<span class="badge {{ $score->risk_dropout >= 70 ? 'badge-soft-danger' : ($score->risk_dropout >= 40 ? 'badge-soft-warning' : 'badge-soft-success') }}">
											{{ $score->risk_dropout }}
										</span>
									@else
										—
									@endif
								</td>
								<td>
									@if ($plan)
										₹{{ number_format($paidPaise / 100) }} <span class="text-muted fs-12">/ ₹{{ number_format($payablePaise / 100) }}</span>
									@else
										—
									@endif
								</td>
								<td>
									<div class="d-flex gap-1 flex-wrap">
										<a href="{{ route('live-batches.student', [$batch->id, $uid]) }}" class="btn btn-sm btn-outline-light">
											<i class="ti ti-eye"></i> View
										</a>
										<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#member-{{ $uid }}">
											<i class="ti ti-adjustments"></i> Manage
										</button>
									</div>
								</td>
							</tr>
							<tr class="collapse" id="member-{{ $uid }}">
								<td colspan="10" class="bg-light">
									<div class="d-flex gap-3 flex-wrap align-items-end">
										<form method="POST" action="{{ route('live-batches.students.status', [$batch->id, $uid]) }}" class="d-flex gap-2 align-items-end">
											@csrf @method('PATCH')
											<div>
												<label class="form-label fs-12">Membership status</label>
												<select name="status" class="form-select form-select-sm">
													@foreach (['reserved', 'payment_pending', 'enrolled', 'dropped', 'transferred', 'completed', 'paused'] as $memberStatus)
														<option value="{{ $memberStatus }}" @selected($member->status === $memberStatus)>{{ ucfirst(str_replace('_', ' ', $memberStatus)) }}</option>
													@endforeach
												</select>
											</div>
											<button type="submit" class="btn btn-sm btn-primary">Save</button>
										</form>
										<form method="POST" action="{{ route('live-batches.students.profile', [$batch->id, $uid]) }}"
											onsubmit="return confirm('Re-send the sign-in instructions (batch number + OTP login link) to {{ $member->user?->name }}?');">
											@csrf @method('PATCH')
											<input type="hidden" name="send_login" value="1">
											<button type="submit" class="btn btn-sm btn-outline-warning">
												<i class="ti ti-send"></i> Resend login instructions
											</button>
										</form>
										<form method="POST" action="{{ route('live-batches.students.destroy', [$batch->id, $uid]) }}"
											onsubmit="return confirm('Drop {{ $member->user?->name }} from {{ $batch->number }}? The membership is kept as dropped for history.');">
											@csrf @method('DELETE')
											<input type="hidden" name="reason" value="Removed from CRM roster">
											<button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-user-minus"></i> Drop from batch</button>
										</form>
									</div>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="10" class="text-center py-4">No students enrolled in this batch yet.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Schedule the whole class calendar in one pass: pick weekdays + time,
	     the LMS creates every class (Zoom + reminders, syllabus-titled) and
	     sends the batch one schedule summary. Re-running skips booked slots. -->
	@if ($batch->status === 'active')
		<div class="card mb-3">
			<div class="card-header"><h5 class="mb-0"><i class="ti ti-calendar-stats me-2"></i>Schedule Class Series</h5></div>
			<div class="card-body">
				<form method="POST" action="{{ route('live-batches.classes.series', $batch->id) }}"
					onsubmit="return confirm('Create the whole class calendar for {{ $batch->number }}? Students get one summary message, then the normal reminders before each class.');">
					@csrf
					<div class="row g-3 align-items-end">
						<div class="col-md-4">
							<label class="form-label">Class days <span class="text-danger">*</span></label>
							<div class="d-flex gap-2 flex-wrap">
								@foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $dayNo => $dayName)
									<div class="form-check form-check-inline m-0">
										<input type="checkbox" name="weekdays[]" value="{{ $dayNo }}" class="form-check-input" id="wd-{{ $dayNo }}"
											@checked($dayNo <= 5)>
										<label class="form-check-label fs-13" for="wd-{{ $dayNo }}">{{ $dayName }}</label>
									</div>
								@endforeach
							</div>
						</div>
						<div class="col-md-2">
							<label class="form-label">Time <span class="text-danger">*</span></label>
							<input type="time" name="time" class="form-control" value="20:00" required>
						</div>
						<div class="col-md-2">
							<label class="form-label">Duration (min)</label>
							<input type="number" name="duration" class="form-control" value="90" min="15" max="480">
						</div>
						<div class="col-md-2">
							<label class="form-label">First class from</label>
							<input type="date" name="start" class="form-control" min="{{ now()->toDateString() }}"
								value="{{ optional($batch->starts_on)->isFuture() ? $batch->starts_on->toDateString() : now()->addDay()->toDateString() }}">
						</div>
						<div class="col-md-2">
							<label class="form-label">Until (or use count)</label>
							<input type="date" name="until" class="form-control"
								value="{{ $batch->ends_on?->toDateString() }}">
						</div>
						<div class="col-md-2">
							<label class="form-label">Number of classes</label>
							<input type="number" name="count" class="form-control" min="1" max="200" placeholder="e.g. 39">
						</div>
						<div class="col-md-3">
							<div class="form-check mt-2">
								<input type="checkbox" name="topics" value="1" class="form-check-input" id="map-topics" checked>
								<label class="form-check-label fs-13" for="map-topics">Title classes from the course syllabus (one topic per class)</label>
							</div>
						</div>
						<div class="col-md-3">
							<button type="submit" class="btn btn-primary w-100">Create Class Calendar</button>
						</div>
						<div class="col-12">
							<span class="text-muted fs-12">Fill <strong>Until</strong> (e.g. the batch end date) or a fixed <strong>Number of classes</strong>. Already-booked slots are skipped, so you can safely extend the calendar later.</span>
						</div>
					</div>
				</form>
			</div>
		</div>
	@endif

	<!-- Add a class to this batch: Zoom + reminders handled by the LMS, and
	     every student is instantly notified on WhatsApp + email. -->
	@if ($batch->status === 'active')
		<div class="card mb-3">
			<div class="card-header"><h5 class="mb-0"><i class="ti ti-calendar-plus me-2"></i>Add a Class</h5></div>
			<div class="card-body">
				<form method="POST" action="{{ route('live-batches.classes.store', $batch->id) }}" class="row g-3 align-items-end">
					@csrf
					<div class="col-md-3">
						<label class="form-label">Title</label>
						<input type="text" name="title" class="form-control" placeholder="e.g. Day 3 — SQL Joins" maxlength="255">
					</div>
					<div class="col-md-2">
						<label class="form-label">Type</label>
						<select name="kind" class="form-select">
							<option value="class">Class</option>
							<option value="mentoring">Mentoring</option>
						</select>
					</div>
					<div class="col-md-2">
						<label class="form-label">Host (mentor/trainer)</label>
						<select name="host" class="form-select">
							<option value="">Default host</option>
							@foreach ($mentors as $mentorOption)
								<option value="{{ $mentorOption->user_id }}">{{ $mentorOption->user?->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-2">
						<label class="form-label">Date <span class="text-danger">*</span></label>
						<input type="date" name="date" class="form-control" required min="{{ now()->toDateString() }}">
					</div>
					<div class="col-md-1">
						<label class="form-label">Time <span class="text-danger">*</span></label>
						<input type="time" name="time" class="form-control" required>
					</div>
					<div class="col-md-1">
						<label class="form-label">Mins</label>
						<input type="number" name="duration" class="form-control" placeholder="90" min="15" max="480">
					</div>
					<div class="col-md-1">
						<button type="submit" class="btn btn-primary w-100">Add</button>
					</div>
					<div class="col-12">
						<span class="text-muted fs-12">Creates the Zoom meeting and reminder ladder in the LMS, and messages every student in {{ $batch->number }} right away. Pick <strong>Mentoring</strong> + a host for the weekly batch mentoring session.</span>
					</div>
				</form>
			</div>
		</div>
	@endif

	<!-- Sessions of this batch -->
	<div class="card">
		<div class="card-header">
			<h5 class="mb-0"><i class="ti ti-calendar-event me-2"></i>Classes ({{ $batch->liveSessions->count() }})</h5>
		</div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Title</th>
							<th>Kind</th>
							<th>Scheduled</th>
							<th>Status</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($batch->liveSessions as $session)
							<tr>
								<td>{{ $session->title }}</td>
								<td><span class="badge badge-soft-secondary">{{ ucfirst($session->kind) }}</span></td>
								<td>{{ $session->scheduled_start?->format('d M Y, h:i A') ?? '—' }}</td>
								<td>
									<span class="badge {{ ['completed' => 'badge-soft-success', 'ended' => 'badge-soft-success', 'scheduled' => 'badge-soft-info', 'cancelled' => 'badge-soft-danger'][$session->status] ?? 'badge-soft-secondary' }}">
										{{ ucfirst($session->status) }}
									</span>
								</td>
								<td>
									<div class="d-flex gap-1 flex-wrap">
										<a href="{{ route('class-attendance.show', $session->id) }}" class="btn btn-sm btn-outline-light">
											<i class="ti ti-checklist"></i> Attendance
										</a>
										@if ($session->status === 'scheduled')
											<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#class-resched-{{ $session->id }}">
												<i class="ti ti-calendar-time"></i> Reschedule
											</button>
											<form method="POST" action="{{ route('live-batches.classes.cancel', [$batch->id, $session->id]) }}"
												onsubmit="return confirm('Cancel this class? Every student in {{ $batch->number }} will be notified.');">
												@csrf @method('DELETE')
												<input type="hidden" name="reason" value="Cancelled by the academic team">
												<button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-x"></i> Cancel</button>
											</form>
										@else
											<button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#class-rec-{{ $session->id }}">
												<i class="ti ti-video"></i> Recording
											</button>
										@endif
									</div>
								</td>
							</tr>
							@if ($session->status === 'scheduled')
								<tr class="collapse" id="class-resched-{{ $session->id }}">
									<td colspan="5" class="bg-light">
										<form method="POST" action="{{ route('live-batches.classes.reschedule', [$batch->id, $session->id]) }}" class="row g-2 align-items-end">
											@csrf @method('PATCH')
											<div class="col-md-2">
												<label class="form-label fs-12">New date</label>
												<input type="date" name="date" class="form-control form-control-sm" min="{{ now()->toDateString() }}"
													value="{{ $session->scheduled_start?->toDateString() }}" required>
											</div>
											<div class="col-md-2">
												<label class="form-label fs-12">New time</label>
												<input type="time" name="time" class="form-control form-control-sm"
													value="{{ $session->scheduled_start?->format('H:i') }}" required>
											</div>
											<div class="col-md-4">
												<label class="form-label fs-12">Reason (sent to students)</label>
												<input type="text" name="reason" class="form-control form-control-sm" placeholder="Schedule change">
											</div>
											<div class="col-md-2">
												<button type="submit" class="btn btn-sm btn-primary w-100">Save new slot</button>
											</div>
										</form>
									</td>
								</tr>
							@else
								<tr class="collapse" id="class-rec-{{ $session->id }}">
									<td colspan="5" class="bg-light">
										<form method="POST" action="{{ route('live-batches.classes.recording', [$batch->id, $session->id]) }}" class="row g-2 align-items-end">
											@csrf
											<div class="col-md-5">
												<label class="form-label fs-12">Recording URL (Zoom share link or any streamable URL)</label>
												<input type="url" name="url" class="form-control form-control-sm" required placeholder="https://…">
											</div>
											<div class="col-md-3">
												<label class="form-label fs-12">Passcode (if any)</label>
												<input type="text" name="passcode" class="form-control form-control-sm">
											</div>
											<div class="col-md-2">
												<button type="submit" class="btn btn-sm btn-primary w-100">Attach recording</button>
											</div>
											<div class="col-12">
												<span class="text-muted fs-12">Absent students can replay it from their portal. Zoom cloud recordings attach automatically — this is the manual fallback.</span>
											</div>
										</form>
									</td>
								</tr>
							@endif
						@empty
							<tr>
								<td colspan="5" class="text-center py-4">No classes scheduled for this batch.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

@endsection
