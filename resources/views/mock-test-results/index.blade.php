@extends('layouts.app')

@section('title', 'Mock Test Results')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Mock Test Results</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Mock Interviews</p>
					<h4 class="mb-0">{{ number_format($totalMocks) }}</h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-microphone text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Completed</p>
					<h4 class="mb-0">{{ number_format($completedMocks) }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-circle-check text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Avg Mock Score</p>
					<h4 class="mb-0">{{ $avgMockScore }}<span class="fs-14 text-muted">/100</span></h4>
					<span class="avatar avatar-rounded bg-warning-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-gauge text-warning"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Avg Quiz Score</p>
					<h4 class="mb-0">{{ $avgQuizScore }}<span class="fs-14 text-muted">%</span></h4>
					<span class="avatar avatar-rounded bg-primary-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-clipboard-check text-primary"></i>
					</span>
				</div>
			</div>
		</div>
	</div>
	<!-- End Stat Cards -->

	<!-- Voice interview pack pricing (writes to the LMS store; students see changes instantly) -->
	@if (session('status'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			{{ session('status') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	@endif
	@if ($errors->any())
		<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
	@endif
	<div class="card mb-3">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h5 class="card-title mb-0"><i class="ti ti-currency-rupee me-1"></i>Voice Interview Pack Pricing</h5>
			<span class="text-muted fs-12">Changes go live on the student portal immediately</span>
		</div>
		<div class="card-body">
			@forelse ($voicePacks as $pack)
				<form method="POST" action="{{ route('mock-test-results.pricing', $pack->id) }}"
					class="row g-2 align-items-end {{ $loop->last ? '' : 'mb-3 pb-3 border-bottom' }}">
					@csrf
					@method('PATCH')
					<div class="col-md-4">
						<span class="fw-medium d-block">{{ $pack->name }}</span>
						<span class="text-muted fs-12">{{ $pack->sku }}</span>
					</div>
					<div class="col-md-2">
						<label class="form-label fs-12 mb-1">Price (₹)</label>
						<input type="number" name="price_rupees" class="form-control" min="0" step="1"
							value="{{ (int) round($pack->price_paise / 100) }}">
					</div>
					<div class="col-md-2">
						<label class="form-label fs-12 mb-1">Sessions</label>
						<input type="number" name="sessions" class="form-control" min="1" max="100"
							value="{{ $pack->grant_amount }}">
					</div>
					<div class="col-md-2">
						<div class="form-check mt-4">
							<input type="hidden" name="active" value="0">
							<input class="form-check-input" type="checkbox" name="active" value="1"
								id="pack-active-{{ $pack->id }}" {{ $pack->active ? 'checked' : '' }}>
							<label class="form-check-label fs-13" for="pack-active-{{ $pack->id }}">On sale</label>
						</div>
					</div>
					<div class="col-md-2">
						<button type="submit" class="btn btn-primary w-100">Save</button>
					</div>
				</form>
			@empty
				<p class="text-muted mb-0">No voice interview packs found in the LMS store.</p>
			@endforelse
		</div>
	</div>

	<!-- Filters -->
	<div class="card mb-3">
		<div class="card-body">
			<form method="GET" action="{{ route('mock-test-results.index') }}" class="row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label">Search Student</label>
					<input type="text" name="search" class="form-control" placeholder="Name or email..." value="{{ request('search') }}">
				</div>
				<div class="col-md-4">
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
				<div class="col-md-4 d-flex gap-2">
					<button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1"></i> Filter</button>
					<a href="{{ route('mock-test-results.index') }}" class="btn btn-outline-light">Reset</a>
				</div>
			</form>
		</div>
	</div>
	<!-- End Filters -->

	<!-- Mock interviews -->
	<div class="card mb-3">
		<div class="card-header">
			<h5 class="mb-0"><i class="ti ti-microphone me-2"></i>AI Mock Interviews</h5>
		</div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Student</th>
							<th>Role</th>
							<th>Mode</th>
							<th>Status</th>
							<th>Score</th>
							<th>Duration</th>
							<th>Date</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($mocks as $mock)
							<tr>
								<td>
									<div class="fw-medium">{{ $mock->user?->name ?? 'Unknown' }}</div>
									<div class="text-muted fs-12">{{ $mock->user?->email ?? '' }}</div>
								</td>
								<td>{{ $mock->blueprint?->role_title ?? '—' }}</td>
								<td>
									@if ($mock->mode === 'voice')
										<span class="badge badge-soft-danger"><i class="ti ti-phone-call me-1"></i>Voice Call</span>
									@elseif ($mock->is_room)
										<span class="badge badge-soft-primary"><i class="ti ti-video me-1"></i>Virtual Room</span>
									@else
										<span class="badge badge-soft-secondary"><i class="ti ti-message me-1"></i>Text</span>
									@endif
								</td>
								<td>
									<span class="badge {{ $mock->status === 'completed' ? 'badge-soft-success' : 'badge-soft-info' }}">
										{{ ucfirst(str_replace('_', ' ', $mock->status)) }}
									</span>
								</td>
								<td>
									@if (!is_null($mock->overall_score))
										<span class="badge {{ $mock->overall_score >= 70 ? 'badge-soft-success' : ($mock->overall_score >= 40 ? 'badge-soft-warning' : 'badge-soft-danger') }}">
											{{ $mock->overall_score }}/100
										</span>
									@else
										—
									@endif
								</td>
								<td>{{ $mock->duration_seconds ? gmdate('i:s', $mock->duration_seconds) . ' min' : '—' }}</td>
								<td>{{ $mock->started_at?->format('d M Y') ?? '—' }}</td>
							</tr>
						@empty
							<tr>
								<td colspan="7" class="text-center py-4">No mock interviews found.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<div class="mt-3">
				{{ $mocks->links() }}
			</div>
		</div>
	</div>

	<!-- Quiz attempts -->
	<div class="card">
		<div class="card-header">
			<h5 class="mb-0"><i class="ti ti-clipboard-check me-2"></i>Quiz / Assignment Test Attempts</h5>
		</div>
		<div class="card-body">
			<div class="table-responsive custom-table">
				<table class="table table-bordered table-nowrap">
					<thead class="table-white">
						<tr>
							<th>Student</th>
							<th>Quiz</th>
							<th>Status</th>
							<th>Score</th>
							<th>Correct</th>
							<th>Submitted</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($quizAttempts as $attempt)
							<tr>
								<td>
									<div class="fw-medium">{{ $attempt->user?->name ?? 'Unknown' }}</div>
									<div class="text-muted fs-12">{{ $attempt->user?->email ?? '' }}</div>
								</td>
								<td>{{ $attempt->quiz?->title ?? 'Quiz #' . $attempt->quiz_id }}</td>
								<td>
									<span class="badge {{ ['submitted' => 'badge-soft-success', 'graded' => 'badge-soft-success', 'in_progress' => 'badge-soft-info', 'pending' => 'badge-soft-warning'][$attempt->status] ?? 'badge-soft-secondary' }}">
										{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
									</span>
								</td>
								<td>
									@if (!is_null($attempt->score_pct))
										<span class="badge {{ $attempt->score_pct >= 70 ? 'badge-soft-success' : ($attempt->score_pct >= 40 ? 'badge-soft-warning' : 'badge-soft-danger') }}">
											{{ $attempt->score_pct }}%
										</span>
									@else
										—
									@endif
								</td>
								<td>{{ !is_null($attempt->correct_count) ? $attempt->correct_count . '/' . $attempt->total_count : '—' }}</td>
								<td>{{ $attempt->submitted_at?->format('d M Y') ?? '—' }}</td>
							</tr>
						@empty
							<tr>
								<td colspan="6" class="text-center py-4">No quiz attempts found.</td>
							</tr>
						@endforelse
					</tbody>
				</table>
			</div>

			<div class="mt-3">
				{{ $quizAttempts->links() }}
			</div>
		</div>
	</div>

@endsection
