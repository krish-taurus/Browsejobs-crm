@extends('layouts.app')

@section('title', 'Website SEO')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<h4 class="mb-0">Website SEO</h4>
			<span class="text-muted fs-13">Every public page — click one to set the Google title &amp; description</span>
		</div>
		<a href="{{ route('site-seo.edit', ['path' => '']) }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add a page</a>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif

	<div class="row">
		@foreach ($pages as $page)
			@php $isSet = trim($page['title']) !== ''; @endphp
			<div class="col-xl-3 col-md-4 col-sm-6 mb-3">
				<div class="card h-100">
					<div class="card-body d-flex flex-column">
						<div class="d-flex align-items-center justify-content-between gap-2">
							<code class="fs-12 text-primary text-truncate">{{ $page['path'] }}</code>
							@if ($isSet)
								<span class="badge badge-soft-success">SEO set</span>
							@else
								<span class="badge badge-soft-warning">Not set</span>
							@endif
						</div>

						<h6 class="mt-2 mb-1 text-truncate" title="{{ $page['title'] }}">{{ $isSet ? $page['title'] : $page['label'] }}</h6>
						<p class="text-muted fs-13 mb-0" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
							{{ $page['description'] ?: 'No description yet — Google will pick its own text.' }}
						</p>

						<div class="mt-auto pt-3">
							<a href="{{ route('site-seo.edit', ['path' => $page['path']]) }}" class="btn btn-sm {{ $isSet ? 'btn-light' : 'btn-primary' }} w-100">
								<i class="ti ti-pencil me-1"></i>{{ $isSet ? 'Edit SEO' : 'Add SEO' }}
							</a>
						</div>
					</div>
				</div>
			</div>
		@endforeach
	</div>

	<p class="text-muted fs-12">Page content itself is edited under <a href="{{ route('site-pages.index') }}">Website → Pages</a>.</p>

@endsection
