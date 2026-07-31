@extends('layouts.app')

@section('title', 'Batch Funnel')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-1">Batch Funnel</h4>
			<span class="text-muted fs-13">Interest → Saturday Masterclass → 7-day Bootcamp → Paid Batch · runs automatically every day</span>
		</div>
		<form method="POST" action="{{ route('batch-funnel.run') }}"
			onsubmit="return confirm('Run the funnel now? This creates batches, enrols students and converts ended bootcamps.');">
			@csrf
			<button type="submit" class="btn btn-primary"><i class="ti ti-player-play me-1"></i> Run Funnel Now</button>
		</form>
	</div>

	@if (session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger">{{ session('error') }}</div>
	@endif

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Masterclass Interest</p>
					<h4 class="mb-0">{{ number_format($stats['interest']) }} <span class="fs-14 text-muted">leads</span></h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-flame text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Registered & Ready</p>
					<h4 class="mb-0">{{ number_format($stats['ready']) }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-user-check text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Active Bootcamps</p>
					<h4 class="mb-0">{{ number_format($stats['activeBootcamps']) }}</h4>
					<span class="avatar avatar-rounded bg-warning-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-run text-warning"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Awaiting Payment</p>
					<h4 class="mb-0">{{ number_format($stats['awaitingPayment']) }}</h4>
					<span class="avatar avatar-rounded bg-danger-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-report-money text-danger"></i>
					</span>
				</div>
			</div>
		</div>
	</div>
	<!-- End Stat Cards -->

	@if ($noCourseLeads->isNotEmpty())
		<!-- Blocker: leads that cannot be seated until a course is chosen -->
		<div class="card mb-3 border-warning">
			<div class="card-header">
				<h5 class="mb-0"><i class="ti ti-alert-triangle me-2 text-warning"></i>{{ $noCourseLeads->count() }} lead(s) waiting for a course</h5>
			</div>
			<div class="card-body">
				<p class="fs-13 text-muted mb-3">A masterclass is always per-course — pick each lead's course and they're seated on the next funnel run (account created automatically, OTP login).</p>
				<div class="table-responsive custom-table">
					<table class="table table-bordered table-nowrap">
						<thead class="table-white">
							<tr><th>Lead</th><th>Phone</th><th>Assign Course</th></tr>
						</thead>
						<tbody>
							@foreach ($noCourseLeads as $lmsLead)
								<tr>
									<td class="fw-medium">{{ $lmsLead->name ?: '—' }}</td>
									<td>{{ $lmsLead->phone }}</td>
									<td>
										<form method="POST" action="{{ route('batch-funnel.assign-course', $lmsLead->id) }}" class="d-flex gap-2">
											@csrf @method('PATCH')
											<select name="course_slug" class="form-select form-select-sm" required style="max-width: 260px;">
												<option value="">Select course...</option>
												@foreach ($orderedCourses as $course)
													<option value="{{ $course->slug }}">{{ $course->name }}</option>
												@endforeach
											</select>
											<button type="submit" class="btn btn-sm btn-primary">Assign</button>
										</form>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	@endif

	<!-- Course interest → next masterclass -->
	<div class="card mb-3">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h5 class="mb-0"><i class="ti ti-flame me-2"></i>Course Interest</h5>
			<span class="badge badge-soft-info">Masterclasses run every Saturday &amp; Sunday — each course has its fixed day</span>
		</div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Course</th>
							<th>Next Masterclass</th>
							<th>Masterclass Leads</th>
							<th>Already Registered</th>
							<th>Awaiting Seating</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($interest as $row)
							<tr>
								<td class="fw-medium">{{ $row['course'] }}</td>
								<td>
									@if ($row['day'])
										<span class="badge {{ $row['day'] === 'Saturday' ? 'badge-soft-primary' : 'badge-soft-info' }}">{{ $row['day'] }}</span>
										<span class="text-muted fs-12">{{ $row['next_date']?->format('d M') }}</span>
									@else
										<span class="badge badge-soft-danger">No course set</span>
										<span class="text-muted fs-12">— pick the course on the lead page</span>
									@endif
								</td>
								<td>{{ $row['leads'] }}</td>
								<td><span class="badge badge-soft-success">{{ $row['registered'] }}</span></td>
								<td>
									<span class="badge badge-soft-warning">{{ $row['leads'] - $row['registered'] }}</span>
									<span class="text-muted fs-12">— seated on the next funnel run (account auto-created, OTP login)</span>
								</td>
							</tr>
						@empty
							<tr><td colspan="5" class="text-center py-4">No masterclass bookings yet.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Funnel chains -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-arrows-split-2 me-2"></i>Funnel Chains</h5></div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Masterclass (Saturday)</th>
							<th></th>
							<th>Bootcamp (7 days)</th>
							<th></th>
							<th>Paid Batch</th>
							<th>Fees Collected</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($chains as $chain)
							@php
								$mc = $chain['masterclass'];
								$bc = $chain['bootcamp'];
								$paid = $chain['paid'];
								$statusBadge = fn ($s) => ['active' => 'badge-soft-success', 'completed' => 'badge-soft-info'][$s] ?? 'badge-soft-secondary';
								$paidCounts = $paid ? ($memberStatusCounts->get($paid->id) ?? collect()) : collect();
								$enrolledCount = $paidCounts->firstWhere('status', 'enrolled')->total ?? 0;
								$reservedCount = ($paidCounts->firstWhere('status', 'reserved')->total ?? 0) + ($paidCounts->firstWhere('status', 'payment_pending')->total ?? 0);
								$fees = $paid ? $feeTotals->get($paid->id) : null;
							@endphp
							<tr>
								<td>
									<a href="{{ route('live-batches.show', $mc->id) }}" class="fw-medium">{{ $mc->number }}</a>
									<div class="text-muted fs-12">{{ $mc->course?->name }} · {{ $mc->starts_on?->format('d M') }} · {{ $mc->members_count }} students</div>
									<span class="badge {{ $statusBadge($mc->status) }}">{{ ucfirst($mc->status) }}</span>
								</td>
								<td class="text-center text-muted"><i class="ti ti-arrow-right"></i></td>
								<td>
									@if ($bc)
										<a href="{{ route('live-batches.show', $bc->id) }}" class="fw-medium">{{ $bc->number }}</a>
										<div class="text-muted fs-12">{{ $bc->starts_on?->format('d M') }} – {{ $bc->ends_on?->format('d M') }} · {{ $bc->members_count }} students</div>
										<span class="badge {{ $statusBadge($bc->status) }}">{{ ucfirst($bc->status) }}</span>
									@elseif ($mc->status === 'active')
										<span class="text-muted fs-13">created after the masterclass runs</span>
									@else
										<span class="text-muted fs-13">—</span>
									@endif
								</td>
								<td class="text-center text-muted"><i class="ti ti-arrow-right"></i></td>
								<td>
									@if ($paid)
										<a href="{{ route('live-batches.show', $paid->id) }}" class="fw-medium">{{ $paid->number }}</a>
										<div class="fs-12">
											<span class="badge badge-soft-success">{{ $enrolledCount }} enrolled</span>
											<span class="badge badge-soft-warning">{{ $reservedCount }} awaiting payment</span>
										</div>
									@elseif ($bc)
										<span class="text-muted fs-13">created with the bootcamp</span>
									@else
										<span class="text-muted fs-13">—</span>
									@endif
								</td>
								<td>
									@if ($fees)
										<span class="fw-medium text-success">₹{{ number_format($fees['paid'] / 100) }}</span>
										<span class="text-muted fs-12">/ ₹{{ number_format($fees['payable'] / 100) }}</span>
									@else
										—
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center py-4">No masterclass batches yet — chains appear after the first Saturday run.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	@if ($unchainedBootcamps->isNotEmpty())
		<!-- Batches created before chain-linking / manually -->
		<div class="card">
			<div class="card-header"><h5 class="mb-0"><i class="ti ti-link-off me-2"></i>Standalone Bootcamps (no masterclass link)</h5></div>
			<div class="card-body">
				<div class="table-responsive custom-table">
					<table class="table table-bordered table-nowrap">
						<thead class="table-white">
							<tr><th>Batch</th><th>Course</th><th>Dates</th><th>Students</th><th>Status</th><th>Actions</th></tr>
						</thead>
						<tbody>
							@foreach ($unchainedBootcamps as $batch)
								<tr>
									<td class="fw-medium">{{ $batch->number }}</td>
									<td>{{ $batch->course?->name }}</td>
									<td>{{ $batch->starts_on?->format('d M') ?? '—' }} – {{ $batch->ends_on?->format('d M') ?? '—' }}</td>
									<td>{{ $batch->members_count }}</td>
									<td><span class="badge {{ ['active' => 'badge-soft-success', 'completed' => 'badge-soft-info'][$batch->status] ?? 'badge-soft-secondary' }}">{{ ucfirst($batch->status) }}</span></td>
									<td><a href="{{ route('live-batches.show', $batch->id) }}" class="btn btn-sm btn-outline-light"><i class="ti ti-eye"></i> View</a></td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	@endif

@endsection
