@extends('layouts.app')

@section('title', 'Live Batches')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Live Batches</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	@if (session('success'))
		<div class="alert alert-success">{{ session('success') }}</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger">{{ session('error') }}</div>
	@endif

	<!-- Course registration fees (writes to the LMS; new fee plans use it instantly) -->
	<div class="card mb-3">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h5 class="mb-0"><i class="ti ti-currency-rupee me-2"></i>Course Registration Fees</h5>
			<span class="text-muted fs-12">Blank = platform default (₹{{ number_format(30000) }}) · existing fee plans are never changed</span>
		</div>
		<div class="card-body">
			<div class="row g-3">
				@foreach ($courses as $course)
					<div class="col-md-4">
						<form method="POST" action="{{ route('live-batches.course-fee', $course->id) }}" class="d-flex gap-2 align-items-end">
							@csrf
							@method('PATCH')
							<div class="flex-grow-1">
								<label class="form-label fs-12 mb-1">{{ $course->name }} — fee (₹)</label>
								<input type="number" name="fee_rupees" class="form-control" min="0" step="1"
									placeholder="30000 (default)"
									value="{{ $course->fee_paise !== null ? (int) round($course->fee_paise / 100) : '' }}">
							</div>
							<button type="submit" class="btn btn-primary">Save</button>
						</form>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	<!-- Create a batch manually (the weekend funnel creates its own) -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-plus me-2"></i>Create a Batch</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('live-batches.store') }}" class="row g-3 align-items-end">
				@csrf
				<div class="col-md-3">
					<label class="form-label">Course <span class="text-danger">*</span></label>
					<select name="course_slug" class="form-select" required>
						<option value="">Select course…</option>
						@foreach ($courses as $course)
							<option value="{{ $course->slug }}">{{ $course->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Type <span class="text-danger">*</span></label>
					<select name="type" class="form-select" required>
						<option value="paid">Paid</option>
						<option value="bootcamp">Bootcamp</option>
						<option value="masterclass">Masterclass</option>
						<option value="self_paced">Self-paced</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Starts</label>
					<input type="date" name="starts_on" class="form-control">
				</div>
				<div class="col-md-2">
					<label class="form-label">Ends</label>
					<input type="date" name="ends_on" class="form-control">
				</div>
				<div class="col-md-1">
					<label class="form-label">Seats</label>
					<input type="number" name="capacity" class="form-control" min="1" placeholder="—">
				</div>
				<div class="col-md-2">
					<button type="submit" class="btn btn-primary w-100">Create Batch</button>
				</div>
				<div class="col-12">
					<span class="text-muted fs-12">The batch number is generated automatically ({{ 'COURSE-YYYYMM-##' }}). Add classes and students from the batch page after creating it.</span>
				</div>
			</form>
		</div>
	</div>

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Total Batches</p>
					<h4 class="mb-0">{{ number_format($totalBatches) }}</h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-broadcast text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Active Batches</p>
					<h4 class="mb-0">{{ number_format($activeBatches) }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-player-play text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-4 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Enrolled Students</p>
					<h4 class="mb-0">{{ number_format($enrolledStudents) }}</h4>
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
			<form method="GET" action="{{ route('live-batches.index') }}" class="row g-3 align-items-end">
				<div class="col-md-3">
					<label class="form-label">Search</label>
					<input type="text" name="search" class="form-control" placeholder="Batch number or course..."
						value="{{ request('search') }}">
				</div>
				<div class="col-md-2">
					<label class="form-label">Type</label>
					<select name="type" class="form-select">
						<option value="">All types</option>
						<option value="paid" @selected(request('type') === 'paid')>Paid</option>
						<option value="bootcamp" @selected(request('type') === 'bootcamp')>Bootcamp</option>
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="">All status</option>
						<option value="active" @selected(request('status') === 'active')>Active</option>
						<option value="completed" @selected(request('status') === 'completed')>Completed</option>
						<option value="archived" @selected(request('status') === 'archived')>Archived</option>
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
					<a href="{{ route('live-batches.index') }}" class="btn btn-outline-light">Reset</a>
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
							<th>Type</th>
							<th>Trainer</th>
							<th>Students</th>
							<th>Starts</th>
							<th>Ends</th>
							<th>Status</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($batches as $batch)
							<tr>
								<td><span class="fw-medium">{{ $batch->number }}</span></td>
								<td>{{ $batch->course?->name ?? '—' }}</td>
								<td>
									<span class="badge {{ $batch->type === 'paid' ? 'badge-soft-primary' : 'badge-soft-info' }}">
										{{ ucfirst($batch->type) }}
									</span>
								</td>
								<td>{{ $batch->trainer?->name ?? '—' }}</td>
								<td>
									{{ $batch->students_count }}@if ($batch->capacity) / {{ $batch->capacity }}@endif
								</td>
								<td>{{ $batch->starts_on?->format('d M Y') ?? '—' }}</td>
								<td>{{ $batch->ends_on?->format('d M Y') ?? '—' }}</td>
								<td>
									<span class="badge {{ ['active' => 'badge-soft-success', 'completed' => 'badge-soft-info'][$batch->status] ?? 'badge-soft-secondary' }}">
										{{ ucfirst($batch->status) }}
									</span>
								</td>
								<td>
									<a href="{{ route('live-batches.show', $batch->id) }}" class="btn btn-sm btn-outline-light">
										<i class="ti ti-users"></i> Roster
									</a>
								</td>
							</tr>
						@empty
							<tr>
								<td colspan="9" class="text-center py-4">No batches found in the LMS.</td>
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
