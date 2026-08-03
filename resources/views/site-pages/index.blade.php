@extends('layouts.app')

@section('title', 'Website Pages')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Website Pages</h4>
		<span class="text-muted fs-13"><i class="ti ti-world me-1"></i>Click a page to edit it — live within a minute, no deploy</span>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif

	<div class="row">
		@foreach ($pages as $page)
			@php
				$icon = ['text' => 'ti-file-text', 'course' => 'ti-school', 'designed' => 'ti-layout-board'][$page['kind']] ?? 'ti-file';
				$tone = ['text' => 'primary', 'course' => 'info', 'designed' => 'secondary'][$page['kind']] ?? 'secondary';
			@endphp
			<div class="col-xl-3 col-md-4 col-sm-6 mb-3">
				<div class="card h-100">
					<div class="card-body d-flex flex-column">
						<div class="d-flex align-items-center justify-content-between">
							<span class="avatar avatar-rounded bg-{{ $tone }}-transparent">
								<i class="ti {{ $icon }} text-{{ $tone }}"></i>
							</span>
							@if ($page['kind'] === 'designed')
								<span class="badge badge-soft-secondary">Design-managed</span>
							@elseif ($page['customised'])
								<span class="badge badge-soft-success">Edited</span>
							@else
								<span class="badge badge-soft-secondary">Default copy</span>
							@endif
						</div>

						<h6 class="mt-3 mb-1">{{ $page['label'] }}</h6>
						<code class="fs-12 text-muted">{{ $page['path'] }}</code>

						<div class="mt-2 fs-12 {{ $page['has_seo'] ? 'text-success' : 'text-warning' }}">
							<i class="ti {{ $page['has_seo'] ? 'ti-circle-check' : 'ti-alert-circle' }} me-1"></i>{{ $page['has_seo'] ? 'SEO set' : 'No SEO yet' }}
						</div>

						<div class="mt-auto pt-3 d-flex gap-2">
							@if ($page['kind'] === 'designed')
								<a href="{{ $page['path'] === '/' ? route('website-content.index') : route('site-seo.edit', ['path' => $page['path']]) }}" class="btn btn-sm btn-light flex-grow-1">
									<i class="ti ti-pencil me-1"></i>{{ $page['path'] === '/' ? 'Edit hero' : 'Edit SEO' }}
								</a>
							@else
								<a href="{{ route('site-pages.edit', ['path' => $page['path']]) }}" class="btn btn-sm btn-primary flex-grow-1"><i class="ti ti-pencil me-1"></i>Edit content</a>
							@endif
							<a href="{{ route('site-seo.edit', ['path' => $page['path']]) }}" class="btn btn-sm btn-light" title="Edit SEO"><i class="ti ti-search"></i></a>
							<a href="{{ config('services.lms.site_url').$page['path'] }}" target="_blank" class="btn btn-sm btn-light" title="View live page"><i class="ti ti-external-link"></i></a>
						</div>
					</div>
				</div>
			</div>
		@endforeach
	</div>

	<p class="text-muted fs-12">
		<strong>Design-managed</strong> pages are laid out in code so nothing can break — their titles &amp; Google snippets are still editable under
		<a href="{{ route('site-seo.index') }}">Website → SEO</a>. The homepage hero lives under <a href="{{ route('website-content.index') }}">Website → Homepage</a>.
	</p>

@endsection
