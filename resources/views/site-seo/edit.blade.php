@extends('layouts.app')

@section('title', 'Edit SEO')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<a href="{{ route('site-seo.index') }}" class="text-muted fs-13"><i class="ti ti-arrow-left me-1"></i>All pages</a>
			<h4 class="mb-0 mt-1">SEO — {{ $path === '' ? 'new page' : $label }}</h4>
			@if ($path !== '')
				<code class="fs-12 text-muted">{{ $path }}</code>
			@endif
		</div>
		@if ($path !== '')
			<a href="{{ config('services.lms.site_url').$path }}" target="_blank" class="btn btn-light">View live page <i class="ti ti-external-link ms-1"></i></a>
		@endif
	</div>

	@if ($errors->any())
		<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
	@endif

	<div class="card" style="max-width: 780px;">
		<div class="card-body">
			<form method="POST" action="{{ route('site-seo.update') }}">
				@csrf
				@if ($isKnownPage)
					<input type="hidden" name="path" value="{{ $path }}">
				@else
					<label class="form-label">Page path <span class="text-muted fs-12">(e.g. /courses/devops-cloud)</span></label>
					<input type="text" name="path" class="form-control" value="{{ $path }}" placeholder="/path" required>
				@endif

				<label class="form-label mt-3">Title <span class="text-muted fs-12">(browser tab + Google headline)</span></label>
				<input type="text" id="seo-title" name="title" class="form-control" maxlength="150" value="{{ $entry['title'] ?? '' }}">
				<div class="text-end fs-12 text-muted" id="title-count"></div>

				<label class="form-label mt-2">Description <span class="text-muted fs-12">(Google snippet)</span></label>
				<textarea id="seo-desc" name="description" class="form-control" rows="3" maxlength="300">{{ $entry['description'] ?? '' }}</textarea>
				<div class="text-end fs-12 text-muted" id="desc-count"></div>

				<!-- Google preview -->
				<div class="mt-3 p-3 rounded" style="background:#f8fafd;border:1px solid #e5eaf2;">
					<p class="fs-12 text-muted mb-1">Google preview</p>
					<div style="font-family:arial,sans-serif;">
						<div id="pv-title" style="color:#1a0dab;font-size:18px;line-height:1.3;">{{ $entry['title'] ?? 'Page title' }}</div>
						<div style="color:#006621;font-size:13px;">browsejobs.ai{{ $path }}</div>
						<div id="pv-desc" style="color:#545454;font-size:13px;">{{ $entry['description'] ?? 'Page description appears here.' }}</div>
					</div>
				</div>

				<div class="d-flex gap-2 mt-3">
					<button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save</button>
					<a href="{{ route('site-seo.index') }}" class="btn btn-light">Cancel</a>
				</div>
			</form>
		</div>
	</div>

	<script>
		(function () {
			function wire(inputId, countId, previewId, max) {
				var input = document.getElementById(inputId), count = document.getElementById(countId), pv = document.getElementById(previewId);
				function update() {
					count.textContent = input.value.length + ' / ~' + max + ' characters';
					count.style.color = input.value.length > max ? '#dc2626' : '';
					if (input.value) pv.textContent = input.value;
				}
				input.addEventListener('input', update); update();
			}
			wire('seo-title', 'title-count', 'pv-title', 60);
			wire('seo-desc', 'desc-count', 'pv-desc', 160);
		})();
	</script>

@endsection
