@extends('layouts.app')

@section('title', 'Attendance — ' . $session->title)

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-1">{{ $session->title }}</h4>
			<span class="text-muted">
				{{ $session->batch?->number }} · {{ $session->batch?->course?->name }} ·
				{{ $session->scheduled_start?->format('d M Y, h:i A') }}
			</span>
		</div>
		<a href="{{ route('class-attendance.index') }}" class="btn btn-outline-light">
			<i class="ti ti-arrow-left me-1"></i> Back to Classes
		</a>
	</div>

	@if (session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger">{{ session('error') }}</div>
	@endif

	@php
		$present = $attendance->count();
		$absent = max(0, $members->count() - $present);
	@endphp

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Roster Size</p>
					<h4 class="mb-0">{{ $members->count() }}</h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-users text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Present</p>
					<h4 class="mb-0">{{ $present }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-user-check text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Absent</p>
					<h4 class="mb-0">{{ $absent }}</h4>
					<span class="avatar avatar-rounded bg-danger-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-user-x text-danger"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Host</p>
					<h4 class="mb-0 fs-18">{{ $session->host?->name ?? '—' }}</h4>
					<span class="avatar avatar-rounded bg-primary-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-microphone text-primary"></i>
					</span>
				</div>
			</div>
		</div>
	</div>
	<!-- End Stat Cards -->

	<div class="card">
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Student</th>
							<th>Present</th>
							<th>Joined</th>
							<th>Left</th>
							<th>Time in Class</th>
							<th>Attendance %</th>
							<th>Late</th>
							<th>Correct</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($members as $member)
							@php $row = $attendance->get($member->user_id); @endphp
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
									{!! $row
										? '<span class="badge badge-soft-success">Present</span>'
										: '<span class="badge badge-soft-danger">Absent</span>' !!}
								</td>
								<td>{{ $row?->first_joined_at?->format('h:i A') ?? '—' }}</td>
								<td>{{ $row?->last_left_at?->format('h:i A') ?? '—' }}</td>
								<td>{{ $row ? gmdate('H:i:s', $row->total_seconds) : '—' }}</td>
								<td>
									@if ($row)
										<span class="badge {{ $row->attended_pct >= 75 ? 'badge-soft-success' : ($row->attended_pct >= 40 ? 'badge-soft-warning' : 'badge-soft-danger') }}">
											{{ $row->attended_pct }}%
										</span>
									@else
										—
									@endif
								</td>
								<td>{!! $row?->is_late ? '<span class="badge badge-soft-warning">Late</span>' : '—' !!}</td>
								<td>
									<div class="d-flex gap-1">
										{{-- Manual correction when Zoom missed a join (audited in the LMS). --}}
										@if (! $row || $row->attended_pct < 100)
											<form method="POST" action="{{ route('class-attendance.mark', $session->id) }}">
												@csrf
												<input type="hidden" name="user_id" value="{{ $member->user_id }}">
												<input type="hidden" name="present" value="1">
												<button type="submit" class="btn btn-sm btn-outline-success" title="Mark present">
													<i class="ti ti-user-check"></i> Present
												</button>
											</form>
										@endif
										@if ($row && $row->attended_pct > 0)
											<form method="POST" action="{{ route('class-attendance.mark', $session->id) }}"
												onsubmit="return confirm('Mark {{ $member->user?->name }} absent for this class?');">
												@csrf
												<input type="hidden" name="user_id" value="{{ $member->user_id }}">
												<input type="hidden" name="present" value="0">
												<button type="submit" class="btn btn-sm btn-outline-danger" title="Mark absent">
													<i class="ti ti-user-x"></i> Absent
												</button>
											</form>
										@endif
									</div>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="8" class="text-center py-4">No students on this batch's roster.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

@endsection
