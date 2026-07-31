@extends('layouts.app')

@section('title', 'Edit ' . $label)

{{-- Summernote (lite build — no Bootstrap theme dependency). Loaded through the
     layout's stacks so it runs AFTER the layout's jQuery, never before it. --}}
@push('styles')
	<link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.css" rel="stylesheet">
	<style>
		.note-editor.note-frame { border-color: var(--bs-border-color); border-radius: .5rem; }
		.note-editor .note-editable { font-size: 14px; line-height: 1.65; }
		.note-editor .note-editable h2 { font-size: 1.15rem; font-weight: 600; margin-top: 1.25rem; }
		.note-editor .note-editable h3 { font-size: 1rem; font-weight: 600; margin-top: 1rem; }
	</style>
@endpush

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<a href="{{ route('site-pages.index') }}" class="text-muted fs-13"><i class="ti ti-arrow-left me-1"></i>All pages</a>
			<h4 class="mb-0 mt-1">{{ $label }}</h4>
			<code class="fs-12 text-muted">{{ $path }}</code>
		</div>
		<div class="d-flex gap-2">
			<a href="{{ route('site-seo.edit', ['path' => $path]) }}" class="btn btn-light"><i class="ti ti-search me-1"></i>Edit SEO</a>
			<a href="{{ \App\Http\Controllers\SitePagesController::SITE_URL . $path }}" target="_blank" class="btn btn-light">View live page <i class="ti ti-external-link ms-1"></i></a>
		</div>
	</div>

	@if ($errors->any())
		<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
	@endif

	@if ($isLiveCopy && trim($body) !== '')
		<div class="alert alert-info fs-13" role="alert">
			<i class="ti ti-info-circle me-1"></i>This is the page's <strong>current live text</strong>, pulled straight from the website. Edit it and save to take over the page.
		</div>
	@elseif ($isLiveCopy)
		<div class="alert alert-warning fs-13" role="alert">
			<i class="ti ti-alert-triangle me-1"></i>Couldn't reach the live site to load the existing text, so the editor is empty. Anything you save here replaces the built-in page.
		</div>
	@endif

	<div class="card">
		<div class="card-body">
			<form method="POST" action="{{ route('site-pages.update') }}">
				@csrf
				<input type="hidden" name="path" value="{{ $path }}">
				<textarea id="page-editor" name="body">{{ $body }}</textarea>
				<div class="d-flex gap-2 mt-3">
					<button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save &amp; publish</button>
					<a href="{{ route('site-pages.index') }}" class="btn btn-light">Cancel</a>
				</div>
				<p class="text-muted fs-12 mt-2 mb-0">Saving publishes to the live site within a minute. Clearing all content restores the original built-in page.</p>
			</form>
		</div>
	</div>

@endsection

@push('scripts')
	<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>
	<script>
		$(function () {
			if (!$.fn.summernote) {
				return; // editor CDN unreachable — the plain textarea still saves
			}
			$('#page-editor').summernote({
				height: 460,
				disableDragAndDrop: true,
				toolbar: [
					['style', ['style']],
					['font', ['bold', 'italic', 'underline', 'clear']],
					['para', ['ul', 'ol']],
					['insert', ['link']],
					['view', ['codeview']],
				],
				styleTags: ['p', 'h2', 'h3', 'blockquote'],
			});
		});
	</script>
@endpush
