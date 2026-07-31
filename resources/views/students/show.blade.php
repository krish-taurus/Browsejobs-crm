@extends('layouts.app')

@section('title', 'Student Profile')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Student Profile</h4>
		<a href="{{ route('students.index') }}" class="btn btn-outline-light">
			<i class="ti ti-arrow-left me-1"></i> Back to List
		</a>
	</div>

	<div class="card">
		<div class="card-body">
			<div class="d-flex align-items-center gap-3 mb-3">
				<span class="avatar avatar-xl avatar-rounded bg-light text-dark fs-24">
					{{ strtoupper(substr($student->full_name ?? '?', 0, 1)) }}
				</span>
				<div>
					<h5 class="mb-1">{{ $student->full_name ?? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) }}</h5>
					<span class="badge badge-soft-{{ $student->status === 'active' ? 'success' : 'secondary' }}">
						{{ ucfirst($student->status ?? 'unknown') }}
					</span>
				</div>
			</div>

			<div class="row g-3">
				<div class="col-md-4"><strong>Email:</strong> {{ $student->email ?? '—' }}</div>
				<div class="col-md-4"><strong>Phone:</strong> {{ $student->phone_number ?? '—' }}</div>
				<div class="col-md-4"><strong>Joined:</strong> {{ $student->created_at?->format('d M Y') ?? '—' }}</div>
				<div class="col-md-4"><strong>External ID:</strong> <code>{{ $student->external_id }}</code></div>
			</div>
		</div>
	</div>

	<!-- LMS enrolments (matched by email/phone against the LMS database) -->
	<div class="card mt-3">
		<div class="card-header">
			<h5 class="mb-0"><i class="ti ti-school me-2"></i>LMS Enrolments</h5>
		</div>
		<div class="card-body">
			@if (($lmsUser ?? null) && $lmsMemberships->isNotEmpty())
				<div class="table-responsive custom-table">
					<table class="table table-bordered table-nowrap">
						<thead class="table-white">
							<tr>
								<th>Batch</th>
								<th>Course</th>
								<th>Type</th>
								<th>Status</th>
								<th>Enrolled</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							@foreach ($lmsMemberships as $membership)
								<tr>
									<td><span class="fw-medium">{{ $membership->batch?->number }}</span></td>
									<td>{{ $membership->batch?->course?->name ?? '—' }}</td>
									<td>
										<span class="badge {{ ['paid' => 'badge-soft-primary', 'bootcamp' => 'badge-soft-info'][$membership->batch?->type] ?? 'badge-soft-warning' }}">
											{{ ucfirst($membership->batch?->type ?? '—') }}
										</span>
									</td>
									<td>
										<span class="badge {{ ['enrolled' => 'badge-soft-success', 'completed' => 'badge-soft-info'][$membership->status] ?? 'badge-soft-secondary' }}">
											{{ ucfirst($membership->status) }}
										</span>
									</td>
									<td>{{ $membership->enrolled_at?->format('d M Y') ?? '—' }}</td>
									<td>
										<a href="{{ route('live-batches.student', [$membership->batch_id, $lmsUser->id]) }}" class="btn btn-sm btn-outline-light">
											<i class="ti ti-eye"></i> Full Report
										</a>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@elseif ($lmsUser ?? null)
				<p class="text-muted mb-0 text-center py-3">Found in the LMS but not enrolled in any batch yet.</p>
			@else
				<p class="text-muted mb-0 text-center py-3">No matching LMS account (matched by email/phone).</p>
			@endif
		</div>
	</div>

@endsection