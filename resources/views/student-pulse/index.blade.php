@extends('layouts.app')

@section('title', 'Student Pulse')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-0">Student Pulse</h4>
			<span class="text-muted fs-13">Everything on the students' Pulse page is curated here — it stays empty until you add something</span>
		</div>
		<a href="http://localhost:3000/pulse" target="_blank" class="btn btn-light">View student page <i class="ti ti-external-link ms-1"></i></a>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif
	@if ($errors->any())
		<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
	@endif

	{{-- ------------------------------ Market Pulse ------------------------------ --}}
	<div class="card mb-3">
		<div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
			<h6 class="mb-0"><i class="ti ti-news me-1 text-primary"></i>Market Pulse</h6>
			<form method="POST" action="{{ route('student-pulse.generate') }}">
				@csrf
				<button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-sparkles me-1"></i>Build today's digest</button>
			</form>
		</div>
		<div class="card-body">
			@if ($digest)
				<div class="p-3 rounded mb-3" style="background: var(--bs-tertiary-bg);">
					<p class="fs-12 text-muted mb-1">Latest digest · {{ $digest->digest_date?->format('d M Y') }}</p>
					<p class="mb-2">{{ $digest->narrative }}</p>
					<p class="fs-12 text-muted mb-0">Sources: {{ $digestItems->pluck('source_name')->unique()->implode(', ') ?: '—' }}</p>
				</div>
			@else
				<div class="alert alert-warning fs-13" role="alert">
					<i class="ti ti-alert-triangle me-1"></i>No digest yet, so students see "Today's digest lands each morning…" and nothing else.
					Add at least one source below, then press <strong>Build today's digest</strong>.
				</div>
			@endif

			<form method="POST" action="{{ route('student-pulse.items.store') }}" class="row g-2 align-items-end mb-3">
				@csrf
				<div class="col-md-4">
					<label class="form-label fs-13">Headline</label>
					<input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Databricks acquires…" required>
				</div>
				<div class="col-md-3">
					<label class="form-label fs-13">Link</label>
					<input type="url" name="url" class="form-control form-control-sm" placeholder="https://…" required>
				</div>
				<div class="col-md-2">
					<label class="form-label fs-13">Source</label>
					<input type="text" name="source_name" class="form-control form-control-sm" placeholder="e.g. TechCrunch" required>
				</div>
				<div class="col-md-2">
					<label class="form-label fs-13">Ties to course</label>
					<select name="course_slug" class="form-select form-select-sm">
						<option value="">— none —</option>
						@foreach ($courses as $course)
							<option value="{{ $course->slug }}">{{ $course->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-1">
					<button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="ti ti-plus"></i></button>
				</div>
			</form>

			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead><tr><th>Headline</th><th>Source</th><th>Course</th><th>Published</th><th></th></tr></thead>
					<tbody>
						@forelse ($items as $item)
							@php $inWindow = $item->published_on?->gte(now()->subDays($windowDays)); @endphp
							<tr>
								<td>
									<a href="{{ $item->url }}" target="_blank" class="fw-medium">{{ $item->title }}</a>
									@unless ($inWindow)
										<span class="badge badge-soft-secondary fs-10" title="Older than {{ $windowDays }} days — not included in a new digest">stale</span>
									@endunless
								</td>
								<td class="fs-13">{{ $item->source_name }}</td>
								<td class="fs-13 text-muted">{{ $item->course_slug ?: '—' }}</td>
								<td class="fs-13">{{ $item->published_on?->format('d M') }}</td>
								<td class="text-end">
									<form method="POST" action="{{ route('student-pulse.items.destroy', $item->id) }}">
										@csrf @method('DELETE')
										<button class="btn btn-sm btn-icon btn-outline-light text-danger" title="Remove"><i class="ti ti-trash"></i></button>
									</form>
								</td>
							</tr>
						@empty
							<tr><td colspan="5" class="text-center text-muted py-3">No sources curated — this is why the students' Market Pulse is empty.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="row g-3">
		{{-- --------------------------- Celebration wall --------------------------- --}}
		<div class="col-lg-6">
			<div class="card h-100 mb-0">
				<div class="card-header"><h6 class="mb-0"><i class="ti ti-trophy me-1 text-warning"></i>Wins on your course</h6></div>
				<div class="card-body">
					<form method="POST" action="{{ route('student-pulse.celebrations.store') }}" class="row g-2 mb-3">
						@csrf
						<div class="col-12">
							<label class="form-label fs-13">Student</label>
							<select name="student_id" class="form-select form-select-sm" required>
								<option value="">Pick a student…</option>
								@foreach ($students as $student)
									<option value="{{ $student->id }}">{{ $student->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label fs-13">Role</label>
							<input type="text" name="role_title" class="form-control form-control-sm" placeholder="Data Engineer" required>
						</div>
						<div class="col-md-6">
							<label class="form-label fs-13">Company</label>
							<input type="text" name="company" class="form-control form-control-sm" placeholder="Nimbus Data">
						</div>
						<div class="col-md-6">
							<label class="form-label fs-13">Show as</label>
							<select name="display_mode" class="form-select form-select-sm">
								<option value="named">Their name</option>
								<option value="anonymous">Anonymous</option>
							</select>
						</div>
						<div class="col-md-6">
							<label class="form-label fs-13">Anonymous label</label>
							<input type="text" name="anonymous_label" class="form-control form-control-sm" placeholder="A Data Engineering student">
						</div>
						<div class="col-12">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required>
								<label class="form-check-label fs-13" for="consent">
									The student has agreed to this being shared publicly
								</label>
							</div>
						</div>
						<div class="col-12">
							<button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-plus me-1"></i>Add win</button>
						</div>
					</form>

					@forelse ($celebrations as $win)
						<div class="d-flex align-items-center gap-2 py-2 border-bottom">
							<div class="flex-grow-1">
								<div class="fw-medium fs-13">{{ $win->displayName() }} → {{ $win->role_title }}{{ $win->company ? ' @ '.$win->company : '' }}</div>
								<div class="fs-12 text-muted">{{ $win->published_at ? 'On the wall since '.$win->published_at->format('d M') : 'Not published yet' }}</div>
							</div>
							@if ($win->published_at)
								<form method="POST" action="{{ route('student-pulse.celebrations.unpublish', $win->id) }}">
									@csrf
									<button class="btn btn-sm btn-outline-light">Unpublish</button>
								</form>
							@else
								<form method="POST" action="{{ route('student-pulse.celebrations.publish', $win->id) }}">
									@csrf
									<button class="btn btn-sm btn-primary">Publish</button>
								</form>
							@endif
						</div>
					@empty
						<p class="text-muted fs-13 text-center py-3 mb-0">No wins yet — this is why the students' wall is empty.</p>
					@endforelse
				</div>
			</div>
		</div>

		{{-- ------------------------------ Content Hub ------------------------------ --}}
		<div class="col-lg-6">
			<div class="card h-100 mb-0">
				<div class="card-header"><h6 class="mb-0"><i class="ti ti-player-play me-1 text-info"></i>Content Hub</h6></div>
				<div class="card-body">
					<form method="POST" action="{{ route('student-pulse.content.store') }}" class="row g-2 mb-3">
						@csrf
						<div class="col-md-4">
							<label class="form-label fs-13">Type</label>
							<select name="kind" class="form-select form-select-sm">
								@foreach ($kinds as $kind)
									<option value="{{ $kind }}">{{ ucfirst($kind) }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-8">
							<label class="form-label fs-13">Title</label>
							<input type="text" name="title" class="form-control form-control-sm" placeholder="Episode 12 — breaking into data" required>
						</div>
						<div class="col-12">
							<label class="form-label fs-13">Link</label>
							<input type="url" name="url" class="form-control form-control-sm" placeholder="https://…" required>
						</div>
						<div class="col-12">
							<button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-plus me-1"></i>Publish</button>
						</div>
					</form>

					@forelse ($content as $item)
						<div class="d-flex align-items-center gap-2 py-2 border-bottom">
							<span class="badge badge-soft-info">{{ $item->kind }}</span>
							<div class="flex-grow-1 min-w-0">
								<a href="{{ $item->url }}" target="_blank" class="fw-medium fs-13 d-block text-truncate">{{ $item->title }}</a>
								<div class="fs-12 text-muted">{{ $item->published_at?->format('d M Y') }}</div>
							</div>
							<form method="POST" action="{{ route('student-pulse.content.destroy', $item->id) }}">
								@csrf @method('DELETE')
								<button class="btn btn-sm btn-icon btn-outline-light text-danger" title="Remove"><i class="ti ti-trash"></i></button>
							</form>
						</div>
					@empty
						<p class="text-muted fs-13 text-center py-3 mb-0">Nothing published — this is why the students' Content Hub is empty.</p>
					@endforelse
				</div>
			</div>
		</div>
	</div>

@endsection
