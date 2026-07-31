@extends('layouts.app')

@section('title', $user->name . ' — ' . $batch->number)

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Student Report</h4>
		<a href="{{ route('live-batches.show', $batch->id) }}" class="btn btn-outline-light">
			<i class="ti ti-arrow-left me-1"></i> Back to {{ $batch->number }}
		</a>
	</div>

	@if (session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger">{{ session('error') }}</div>
	@endif

	<!-- Profile header -->
	<div class="card mb-3">
		<div class="card-body">
			<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
				<span class="avatar avatar-xl avatar-rounded bg-light text-dark fs-24">
					{{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
				</span>
				<div>
					<h5 class="mb-1">{{ $user->name }}</h5>
					<span class="badge {{ ['enrolled' => 'badge-soft-success', 'completed' => 'badge-soft-info', 'transferred' => 'badge-soft-warning'][$member->status] ?? 'badge-soft-secondary' }}">
						{{ ucfirst($member->status) }}
					</span>
					<span class="badge badge-soft-secondary">{{ ucfirst($member->enrolment_type) }} learner</span>
					@if ($user->is_active === false)
						<span class="badge badge-soft-danger">Deactivated</span>
					@endif
				</div>
				<button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#edit-student">
					<i class="ti ti-pencil"></i> Edit Account
				</button>
			</div>
			<div class="row g-3">
				<div class="col-md-3"><strong>Email:</strong> {{ $user->email ?? '—' }}</div>
				<div class="col-md-3"><strong>Phone:</strong> {{ $user->phone ?? '—' }}</div>
				<div class="col-md-3"><strong>Batch:</strong> {{ $batch->number }} ({{ $batch->course?->name }})</div>
				<div class="col-md-3"><strong>Enrolled:</strong> {{ $member->enrolled_at?->format('d M Y') ?? '—' }}</div>
			</div>

			<div class="collapse mt-3" id="edit-student">
				<div class="border rounded p-3 bg-light">
					<form method="POST" action="{{ route('live-batches.students.profile', [$batch->id, $user->id]) }}" class="row g-3 align-items-end">
						@csrf @method('PATCH')
						<div class="col-md-3">
							<label class="form-label fs-12">Name</label>
							<input type="text" name="name" class="form-control form-control-sm" value="{{ $user->name }}">
						</div>
						<div class="col-md-3">
							<label class="form-label fs-12">Email</label>
							<input type="email" name="email" class="form-control form-control-sm" value="{{ $user->email }}">
						</div>
						<div class="col-md-2">
							<label class="form-label fs-12">Phone</label>
							<input type="text" name="phone" class="form-control form-control-sm" value="{{ $user->phone }}">
						</div>
						<div class="col-md-2">
							<label class="form-label fs-12">Account</label>
							<select name="active" class="form-select form-select-sm">
								<option value="1" @selected($user->is_active !== false)>Active</option>
								<option value="0" @selected($user->is_active === false)>Deactivated</option>
							</select>
						</div>
						<div class="col-md-2">
							<button type="submit" class="btn btn-sm btn-primary w-100">Save</button>
						</div>
					</form>
					<form method="POST" action="{{ route('live-batches.students.profile', [$batch->id, $user->id]) }}" class="mt-2"
						onsubmit="return confirm('Re-send the sign-in instructions (batch number + OTP login link) to {{ $user->name }} on WhatsApp + email?');">
						@csrf @method('PATCH')
						<input type="hidden" name="send_login" value="1">
						<button type="submit" class="btn btn-sm btn-outline-warning">
							<i class="ti ti-send"></i> Resend login instructions
						</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- Performance snapshot -->
	<div class="row mb-3">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Attendance</p>
					<h4 class="mb-0">{{ $attendance->count() }}<span class="fs-14 text-muted">/{{ $totalSessions }} classes</span></h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-checklist text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Course Progress</p>
					<h4 class="mb-0">{{ $topicsTotal > 0 ? round($topicsCompleted / $topicsTotal * 100) : 0 }}%<span class="fs-14 text-muted"> ({{ $topicsCompleted }}/{{ $topicsTotal }} topics)</span></h4>
					<span class="avatar avatar-rounded bg-primary-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-progress text-primary"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Quiz Average</p>
					@php $gradedQuizzes = $quizAttempts->whereNotNull('score_pct'); @endphp
					<h4 class="mb-0">
						{{ $gradedQuizzes->isNotEmpty() ? round($gradedQuizzes->avg('score_pct')) . '%' : '—' }}
						<span class="fs-14 text-muted">({{ $quizAttempts->count() }} attempts)</span>
					</h4>
					<span class="avatar avatar-rounded bg-warning-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-clipboard-check text-warning"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Mock Interviews</p>
					@php $scoredMocks = $mockInterviews->whereNotNull('overall_score'); @endphp
					<h4 class="mb-0">
						{{ $mockInterviews->count() }}
						<span class="fs-14 text-muted">{{ $scoredMocks->isNotEmpty() ? '(avg ' . round($scoredMocks->avg('overall_score')) . ')' : '' }}</span>
					</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-microphone text-success"></i>
					</span>
				</div>
			</div>
		</div>
	</div>

	@if ($latestScore)
		<!-- LMS score snapshot -->
		<div class="card mb-3">
			<div class="card-header"><h5 class="mb-0"><i class="ti ti-gauge me-2"></i>LMS Score Snapshot
				<span class="text-muted fs-12 fw-normal">computed {{ $latestScore->computed_at?->diffForHumans() }}</span></h5></div>
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-3 col-6">
						<p class="text-muted mb-1 fs-13">Engagement</p>
						<div class="progress" style="height: 8px;"><div class="progress-bar {{ $latestScore->engagement >= 70 ? 'bg-success' : ($latestScore->engagement >= 40 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ $latestScore->engagement }}%"></div></div>
						<span class="fw-medium">{{ $latestScore->engagement }}/100</span>
					</div>
					<div class="col-md-3 col-6">
						<p class="text-muted mb-1 fs-13">Dropout Risk</p>
						<div class="progress" style="height: 8px;"><div class="progress-bar {{ $latestScore->risk_dropout >= 70 ? 'bg-danger' : ($latestScore->risk_dropout >= 40 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $latestScore->risk_dropout }}%"></div></div>
						<span class="fw-medium">{{ $latestScore->risk_dropout }}/100</span>
					</div>
					<div class="col-md-3 col-6">
						<p class="text-muted mb-1 fs-13">Placement Risk</p>
						<div class="progress" style="height: 8px;"><div class="progress-bar {{ $latestScore->risk_placement >= 70 ? 'bg-danger' : ($latestScore->risk_placement >= 40 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $latestScore->risk_placement }}%"></div></div>
						<span class="fw-medium">{{ $latestScore->risk_placement }}/100</span>
					</div>
					<div class="col-md-3 col-6">
						<p class="text-muted mb-1 fs-13">Readiness (PRI) · Streak</p>
						<span class="fw-medium">{{ $latestScore->pri }}/100</span>
						<span class="badge badge-soft-info ms-2"><i class="ti ti-flame me-1"></i>{{ $latestScore->streak_days }} day streak</span>
					</div>
				</div>
			</div>
		</div>
	@endif

	<div class="row">
		<!-- Attendance log -->
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-checklist me-2"></i>Class Attendance</h5></div>
				<div class="card-body">
					<div class="table-responsive custom-table">
						<table class="table table-bordered table-nowrap">
							<thead class="table-white">
								<tr><th>Class</th><th>Date</th><th>Attended</th><th>Late</th></tr>
							</thead>
							<tbody>
								@forelse ($attendance as $row)
									<tr>
										<td>{{ $row->liveSession?->title ?? '—' }}</td>
										<td>{{ $row->liveSession?->scheduled_start?->format('d M Y') ?? '—' }}</td>
										<td>
											<span class="badge {{ $row->attended_pct >= 75 ? 'badge-soft-success' : ($row->attended_pct >= 40 ? 'badge-soft-warning' : 'badge-soft-danger') }}">
												{{ $row->attended_pct }}%
											</span>
										</td>
										<td>{!! $row->is_late ? '<span class="badge badge-soft-warning">Late</span>' : '—' !!}</td>
									</tr>
								@empty
									<tr><td colspan="4" class="text-center py-4">No attendance recorded yet.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Quiz attempts -->
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-clipboard-check me-2"></i>Quiz Attempts</h5></div>
				<div class="card-body">
					<div class="table-responsive custom-table">
						<table class="table table-bordered table-nowrap">
							<thead class="table-white">
								<tr><th>Quiz</th><th>Status</th><th>Score</th><th>Submitted</th></tr>
							</thead>
							<tbody>
								@forelse ($quizAttempts as $attempt)
									<tr>
										<td>{{ $attempt->quiz?->title ?? 'Quiz #' . $attempt->quiz_id }}</td>
										<td>
											<span class="badge {{ ['submitted' => 'badge-soft-success', 'graded' => 'badge-soft-success', 'in_progress' => 'badge-soft-info', 'pending' => 'badge-soft-warning'][$attempt->status] ?? 'badge-soft-secondary' }}">
												{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
											</span>
										</td>
										<td>
											@if (!is_null($attempt->score_pct))
												{{ $attempt->score_pct }}%
												<span class="text-muted fs-12">({{ $attempt->correct_count }}/{{ $attempt->total_count }})</span>
											@else
												—
											@endif
										</td>
										<td>{{ $attempt->submitted_at?->format('d M Y') ?? '—' }}</td>
									</tr>
								@empty
									<tr><td colspan="4" class="text-center py-4">No quiz attempts yet.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<!-- Assignments -->
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-file-text me-2"></i>Assignment Submissions</h5></div>
				<div class="card-body">
					<div class="table-responsive custom-table">
						<table class="table table-bordered table-nowrap">
							<thead class="table-white">
								<tr><th>Assignment</th><th>Status</th><th>Grade</th><th>Submitted</th></tr>
							</thead>
							<tbody>
								@forelse ($submissions as $submission)
									<tr>
										<td>{{ $submission->assignment?->title ?? 'Assignment #' . $submission->assignment_id }}</td>
										<td>
											<span class="badge {{ ['graded' => 'badge-soft-success', 'submitted' => 'badge-soft-info', 'returned' => 'badge-soft-warning'][$submission->status] ?? 'badge-soft-secondary' }}">
												{{ ucfirst($submission->status) }}
											</span>
										</td>
										<td>
											@if ($submission->grade === null)
												<span class="text-muted fs-12">Not graded</span>
											@elseif ($submission->grade->status === 'approved')
												<span class="fw-medium">{{ $submission->grade->score }}/{{ $submission->grade->max_points }}</span>
												<span class="badge badge-soft-success ms-1">Released</span>
											@else
												<span class="fw-medium">{{ $submission->grade->score }}/{{ $submission->grade->max_points }}</span>
												<span class="badge badge-soft-warning ms-1" title="AI draft — release it from the LMS grading queue">Awaiting release</span>
											@endif
										</td>
										<td>{{ $submission->submitted_at?->format('d M Y') ?? '—' }}</td>
									</tr>
								@empty
									<tr><td colspan="4" class="text-center py-4">No assignments submitted yet.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Mock interviews -->
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-microphone me-2"></i>Mock Interviews</h5></div>
				<div class="card-body">
					<div class="table-responsive custom-table">
						<table class="table table-bordered table-nowrap">
							<thead class="table-white">
								<tr><th>Role</th><th>Mode</th><th>Status</th><th>Score</th><th>Date</th></tr>
							</thead>
							<tbody>
								@forelse ($mockInterviews as $mock)
									<tr>
										<td>{{ $mock->blueprint?->role_title ?? '—' }}</td>
										<td><span class="badge badge-soft-secondary">{{ ucfirst($mock->mode) }}</span></td>
										<td>
											<span class="badge {{ $mock->status === 'completed' ? 'badge-soft-success' : 'badge-soft-info' }}">
												{{ ucfirst(str_replace('_', ' ', $mock->status)) }}
											</span>
										</td>
										<td>{{ $mock->overall_score !== null ? $mock->overall_score . '/100' : '—' }}</td>
										<td>{{ $mock->started_at?->format('d M Y') ?? '—' }}</td>
									</tr>
								@empty
									<tr><td colspan="5" class="text-center py-4">No mock interviews yet.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Fees -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-report-money me-2"></i>Fees & Instalments</h5></div>
		<div class="card-body">
			@if ($feePlan)
				@php
					$payablePaise = max(0, $feePlan->total_paise - $feePlan->discount_paise);
					$paidPaise = $feePlan->instalments->where('status', 'paid')->sum('amount_paise');
				@endphp
				<div class="row g-3 mb-3">
					<div class="col-md-3 col-6"><p class="text-muted mb-1 fs-13">Plan</p><span class="fw-medium">{{ ucfirst($feePlan->type) }}</span></div>
					<div class="col-md-3 col-6"><p class="text-muted mb-1 fs-13">Payable</p><span class="fw-medium">₹{{ number_format($payablePaise / 100) }}</span>
						@if ($feePlan->discount_paise > 0)<span class="text-muted fs-12">(after ₹{{ number_format($feePlan->discount_paise / 100) }} discount)</span>@endif
					</div>
					<div class="col-md-3 col-6"><p class="text-muted mb-1 fs-13">Paid</p><span class="fw-medium text-success">₹{{ number_format($paidPaise / 100) }}</span></div>
					<div class="col-md-3 col-6"><p class="text-muted mb-1 fs-13">Outstanding</p><span class="fw-medium {{ $payablePaise - $paidPaise > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format(max(0, $payablePaise - $paidPaise) / 100) }}</span></div>
				</div>
				<div class="table-responsive custom-table">
					<table class="table table-bordered table-nowrap">
						<thead class="table-white">
							<tr><th>#</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Paid On</th></tr>
						</thead>
						<tbody>
							@foreach ($feePlan->instalments as $instalment)
								<tr>
									<td>{{ $instalment->seq }}</td>
									<td>₹{{ number_format($instalment->amount_paise / 100) }}</td>
									<td>{{ $instalment->due_on?->format('d M Y') }}</td>
									<td>
										<span class="badge {{ ['paid' => 'badge-soft-success', 'pending' => 'badge-soft-warning', 'overdue' => 'badge-soft-danger'][$instalment->status] ?? 'badge-soft-secondary' }}">
											{{ ucfirst($instalment->status) }}
										</span>
									</td>
									<td>{{ $instalment->paid_at?->format('d M Y') ?? '—' }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@else
				<p class="text-muted mb-0 text-center py-3">No fee plan for this student in this batch.</p>
			@endif
		</div>
	</div>

	<div class="row">
		<!-- Certificates -->
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-certificate me-2"></i>Certificates</h5></div>
				<div class="card-body">
					@forelse ($certificates as $certificate)
						<div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
							<div>
								<div class="fw-medium">{{ $certificate->title }}</div>
								<div class="text-muted fs-12">{{ $certificate->number }} · {{ $certificate->course?->name }}</div>
							</div>
							<span class="badge {{ $certificate->status === 'issued' ? 'badge-soft-success' : 'badge-soft-warning' }}">
								{{ ucfirst($certificate->status) }}
							</span>
						</div>
					@empty
						<p class="text-muted mb-0 text-center py-3">No certificates yet.</p>
					@endforelse
				</div>
			</div>
		</div>

		<!-- Other batches -->
		<div class="col-lg-6">
			<div class="card mb-3">
				<div class="card-header"><h5 class="mb-0"><i class="ti ti-school me-2"></i>Other Batches</h5></div>
				<div class="card-body">
					@forelse ($otherMemberships as $membership)
						<div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
							<div>
								<div class="fw-medium">{{ $membership->batch?->number }}</div>
								<div class="text-muted fs-12">{{ $membership->batch?->course?->name }} · {{ ucfirst($membership->batch?->type ?? '') }}</div>
							</div>
							<a href="{{ route('live-batches.student', [$membership->batch_id, $user->id]) }}" class="btn btn-sm btn-outline-light">
								<i class="ti ti-eye"></i>
							</a>
						</div>
					@empty
						<p class="text-muted mb-0 text-center py-3">Not enrolled in any other batch.</p>
					@endforelse
				</div>
			</div>
		</div>
	</div>

@endsection
