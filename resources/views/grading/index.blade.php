@extends('layouts.app')

@section('title', 'Grading')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Grading</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			{{ session('success') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			{{ session('error') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	@endif

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Awaiting Release</p>
					<h4 class="mb-0 text-warning">{{ $stats['awaiting_release'] }}</h4>
					<span class="avatar avatar-rounded bg-warning-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-eye-check text-warning"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Ungraded</p>
					<h4 class="mb-0 text-danger">{{ $stats['ungraded'] }}</h4>
					<span class="avatar avatar-rounded bg-danger-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-file-unknown text-danger"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Released</p>
					<h4 class="mb-0 text-success">{{ $stats['released'] }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-circle-check text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Avg Released Score</p>
					<h4 class="mb-0">{{ $stats['avg_score'] }}<span class="fs-14 text-muted">/100</span></h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-gauge text-info"></i>
					</span>
				</div>
			</div>
		</div>
	</div>
	<!-- End Stat Cards -->

	<!-- Create assignment -->
	<div class="card mb-3">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-plus me-2"></i>Create an Assignment</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('grading.assignments.store') }}">
				@csrf
				<div class="row g-3">
					<div class="col-md-4">
						<label class="form-label">Lesson</label>
						<select name="lesson_id" class="form-select" required>
							<option value="">Pick a lesson…</option>
							@foreach ($lessonOptions as $lesson)
								<option value="{{ $lesson->id }}">#{{ $lesson->id }} — {{ $lesson->title }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-8">
						<label class="form-label">Title</label>
						<input type="text" name="title" class="form-control" placeholder="e.g. Build a REST endpoint" required>
					</div>
					<div class="col-12">
						<label class="form-label">Instructions (what the student must do)</label>
						<textarea name="instructions" class="form-control" rows="2" required></textarea>
					</div>
					@for ($i = 0; $i < 3; $i++)
						<div class="col-md-3">
							<label class="form-label fs-12">Rubric criterion {{ $i + 1 }}{{ $i === 0 ? '' : ' (optional)' }}</label>
							<input type="text" name="criteria[{{ $i }}][name]" class="form-control" placeholder="e.g. Correctness" {{ $i === 0 ? 'required' : '' }}>
						</div>
						<div class="col-md-1">
							<label class="form-label fs-12">Points</label>
							<input type="number" name="criteria[{{ $i }}][points]" class="form-control" min="1" max="100" {{ $i === 0 ? 'required' : '' }}>
						</div>
					@endfor
					<div class="col-12">
						<button type="submit" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Create assignment</button>
						<span class="text-muted fs-12 ms-2">Students see it on the lesson page immediately. AI grades submissions against these rubric points.</span>
					</div>
				</div>
			</form>
		</div>
	</div>

	<!-- Filters -->
	<div class="card mb-3">
		<div class="card-body">
			<form method="GET" action="{{ route('grading.index') }}" class="row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label">Search Student</label>
					<input type="text" name="search" class="form-control" placeholder="Name or email..." value="{{ request('search') }}">
				</div>
				<div class="col-md-4">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="">All</option>
						<option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Awaiting release</option>
						<option value="ungraded" {{ request('status') === 'ungraded' ? 'selected' : '' }}>Ungraded</option>
						<option value="released" {{ request('status') === 'released' ? 'selected' : '' }}>Released</option>
					</select>
				</div>
				<div class="col-md-4 d-flex gap-2">
					<button type="submit" class="btn btn-primary">Apply</button>
					<a href="{{ route('grading.index') }}" class="btn btn-light">Reset</a>
				</div>
			</form>
		</div>
	</div>
	<!-- End Filters -->

	<!-- Grading queue -->
	<div class="card">
		<div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-checklist me-1"></i>Submissions ({{ $submissions->count() }})</h5></div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-hover mb-0 align-middle">
					<thead class="table-white">
						<tr>
							<th style="width: 48px;">#</th>
							<th>Student</th>
							<th>Assignment</th>
							<th>AI Score</th>
							<th>AI Feedback</th>
							<th>Status</th>
							<th>Submitted</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($submissions as $s)
							<tr>
								<td class="text-muted">{{ $loop->iteration }}</td>
								<td>
									<div class="fw-medium">{{ $s->user?->name ?? 'Unknown' }}</div>
									<div class="text-muted fs-12">{{ $s->user?->email ?? '' }}</div>
								</td>
								<td>
									<div>{{ $s->assignment?->title ?? 'Assignment #' . $s->assignment_id }}</div>
									<div class="text-muted fs-12">{{ $s->assignment?->lesson?->title ?? '' }}</div>
								</td>
								<td>
									@if ($s->grade !== null)
										<span class="fw-medium">{{ $s->grade->score }}/{{ $s->grade->max_points }}</span>
										@if ($s->grade->ai_likelihood !== null && $s->grade->ai_likelihood > 50)
											<div class="text-danger fs-12" title="Rough AI-authorship estimate — advisory only, never act on it alone">⚠ AI-written? {{ $s->grade->ai_likelihood }}%</div>
										@endif
									@else
										<span class="text-muted">—</span>
									@endif
								</td>
								<td style="max-width: 280px;">
									<span class="text-muted fs-12 d-inline-block text-truncate" style="max-width: 270px;" title="{{ $s->grade?->feedback }}">
										{{ $s->grade?->feedback ?? '—' }}
									</span>
								</td>
								<td>
									@if ($s->bucket === 'released')
										<span class="badge badge-soft-success">Released</span>
									@elseif ($s->bucket === 'draft')
										<span class="badge badge-soft-warning">Awaiting release</span>
									@else
										<span class="badge badge-soft-danger">Ungraded</span>
									@endif
								</td>
								<td class="text-muted fs-12">{{ $s->submitted_at?->format('d M Y') ?? '—' }}</td>
								<td>
									@if ($s->bucket === 'draft')
										<form method="POST" action="{{ route('grading.release', $s->grade->id) }}">
											@csrf
											<button type="submit" class="btn btn-sm btn-success" title="Approve the AI draft — the student is notified on WhatsApp/email">
												<i class="ti ti-check me-1"></i>Release
											</button>
										</form>
									@elseif ($s->bucket === 'ungraded')
										<form method="POST" action="{{ route('grading.grade', $s->id) }}">
											@csrf
											<button type="submit" class="btn btn-sm btn-primary" title="AI grades it against the rubric — you review before the student sees anything">
												<i class="ti ti-sparkles me-1"></i>Run AI grade
											</button>
										</form>
									@else
										<span class="text-muted fs-12">{{ $s->grade?->approved_at?->format('d M Y') }}</span>
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="8" class="text-center text-muted py-4">No submissions yet.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

@endsection
