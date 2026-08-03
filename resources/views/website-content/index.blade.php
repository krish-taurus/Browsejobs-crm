@extends('layouts.app')

@section('title', 'Homepage')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-0">Homepage</h4>
			<span class="text-muted fs-13">Every line on the landing page, section by section — edits go live within a minute, no deploy</span>
		</div>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-light" id="expand-all"><i class="ti ti-arrows-maximize me-1"></i>Expand all</button>
			<a href="{{ config('services.lms.site_url') }}/" target="_blank" class="btn btn-light">View site <i class="ti ti-external-link ms-1"></i></a>
		</div>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif
	@if ($errors->any())
		<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
	@endif

	<div class="accordion" id="homepage-sections">
		@foreach ($sections as $name => $section)
			@php
				$slug = \Illuminate\Support\Str::slug($name);
				$edited = collect(array_keys($section['fields']))->filter(fn ($k) => isset($saved[$k]))->count();
			@endphp
			<div class="card mb-2">
				<div class="card-header d-flex align-items-center justify-content-between gap-2" role="button"
					data-bs-toggle="collapse" data-bs-target="#sec-{{ $slug }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
					<h5 class="mb-0 fs-15"><i class="ti {{ $section['icon'] }} me-2 text-primary"></i>{{ $name }}</h5>
					<div class="d-flex align-items-center gap-2">
						@if ($edited)
							<span class="badge badge-soft-success">{{ $edited }} edited</span>
						@endif
						<span class="badge badge-soft-secondary">{{ count($section['fields']) }} fields</span>
						<i class="ti ti-chevron-down"></i>
					</div>
				</div>

				<div id="sec-{{ $slug }}" class="collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#homepage-sections">
					<div class="card-body">
						<p class="text-muted fs-12">{{ $section['note'] }} <span class="text-body-secondary">Each box shows the text that is on the site right now — edit it and save.</span></p>

						<form method="POST" action="{{ route('website-content.section') }}">
							@csrf
							<input type="hidden" name="section" value="{{ $name }}">
							<div class="row g-3">
								@foreach ($section['fields'] as $key => [$label, $type, $default])
									<div class="{{ $type === 'area' ? 'col-12' : 'col-md-6' }}">
										<label class="form-label fs-13 d-flex align-items-center gap-2 flex-wrap">
											{{ $label }}
											@if (isset($saved[$key]))
												<span class="badge badge-soft-success fs-10">edited</span>
												<button type="button" class="btn btn-link btn-sm p-0 fs-11 text-decoration-none js-revert" data-target="f-{{ $slug }}-{{ $loop->index }}">
													<i class="ti ti-rotate"></i> undo
												</button>
											@endif
										</label>
										@if ($type === 'area')
											<textarea id="f-{{ $slug }}-{{ $loop->index }}" name="copy[{{ $key }}]" class="form-control" rows="{{ mb_strlen($current[$key] ?? '') > 160 ? 3 : 2 }}" data-original="{{ $default }}">{{ $current[$key] ?? '' }}</textarea>
										@else
											<input type="text" id="f-{{ $slug }}-{{ $loop->index }}" name="copy[{{ $key }}]" class="form-control" value="{{ $current[$key] ?? '' }}" data-original="{{ $default }}">
										@endif
									</div>
								@endforeach
							</div>

							<div class="d-flex gap-2 mt-3">
								<button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save {{ \Illuminate\Support\Str::lower($name) }}</button>
							</div>
						</form>

						@if ($edited)
							<form method="POST" action="{{ route('website-content.reset') }}" class="mt-2"
								onsubmit="return confirm('Reset “{{ $name }}” back to the wording the site ships with?');">
								@csrf
								<input type="hidden" name="section" value="{{ $name }}">
								<button type="submit" class="btn btn-sm btn-light text-danger"><i class="ti ti-rotate me-1"></i>Reset this section to the original</button>
							</form>
						@endif
					</div>
				</div>
			</div>
		@endforeach
	</div>

	<p class="text-muted fs-12 mt-3">
		Every box is prefilled with the words that are on the live site right now — change what you want and save that section.
		Putting a line back to its original wording (or clearing the box) makes that line follow the site's built-in copy again.
		Other pages are edited under <a href="{{ route('site-pages.index') }}">Website → Pages</a>; titles &amp; Google snippets under <a href="{{ route('site-seo.index') }}">Website → SEO</a>.
	</p>

@endsection

@push('scripts')
	<script>
		// "undo" next to an edited field: put the site's original wording back
		// in the box. Saving the section then drops the override.
		document.querySelectorAll('.js-revert').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var field = document.getElementById(btn.dataset.target);
				if (field) {
					field.value = field.dataset.original || '';
					field.focus();
				}
			});
		});

		document.getElementById('expand-all')?.addEventListener('click', function () {
			var panels = document.querySelectorAll('#homepage-sections .collapse');
			var openAll = document.querySelectorAll('#homepage-sections .collapse.show').length < panels.length;
			panels.forEach(function (el) {
				// Drop the parent link so panels stop closing each other once expanded.
				el.removeAttribute('data-bs-parent');
				bootstrap.Collapse.getOrCreateInstance(el, { toggle: false })[openAll ? 'show' : 'hide']();
			});
			this.innerHTML = openAll
				? '<i class="ti ti-arrows-minimize me-1"></i>Collapse all'
				: '<i class="ti ti-arrows-maximize me-1"></i>Expand all';
		});
	</script>
@endpush
