@extends('layouts.app')

@section('title', 'Edit ' . $course->name)

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<a href="{{ route('site-pages.index') }}" class="text-muted fs-13"><i class="ti ti-arrow-left me-1"></i>All pages</a>
			<h4 class="mb-0 mt-1">{{ $course->name }} — course page</h4>
			<code class="fs-12 text-muted">{{ $path }}</code>
		</div>
		<div class="d-flex gap-2">
			<a href="{{ route('site-seo.edit', ['path' => $path]) }}" class="btn btn-light"><i class="ti ti-search me-1"></i>Edit SEO</a>
			<a href="{{ config('services.lms.site_url').$path }}" target="_blank" class="btn btn-light">View live page <i class="ti ti-external-link ms-1"></i></a>
		</div>
	</div>

	@if ($errors->any())
		<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
	@endif

	<div class="card">
		<div class="card-body">
			<form method="POST" action="{{ route('site-pages.update-course') }}">
				@csrf
				<input type="hidden" name="slug" value="{{ $course->slug }}">
				<label class="form-label">Opening paragraph (the course "hero" text)</label>
				<textarea name="hero" class="form-control" rows="4" placeholder="Leave blank to keep the built-in copy…">{{ $hero }}</textarea>
				<div class="d-flex gap-2 mt-3">
					<button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save &amp; publish</button>
					<a href="{{ route('site-pages.index') }}" class="btn btn-light">Cancel</a>
				</div>
				<p class="text-muted fs-12 mt-2 mb-0">Plain text only — this same line is what Google shows as the course description, so formatting is stripped. The rest of the course page (modules, tools, projects) stays design-managed so nothing can break.</p>
			</form>
		</div>
	</div>

@endsection
