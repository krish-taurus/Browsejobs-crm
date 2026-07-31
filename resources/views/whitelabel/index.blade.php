@extends('layouts.app')

@section('title', 'Whitelabel')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Whitelabel Branding</h4>
		<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif
	@if ($errors->any())
		<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
	@endif

	<p class="text-muted fs-13 mb-3">
		Each tenant is one customer (institute) running the platform under their own brand.
		Name, logo and colours apply to their student portal instantly — no rebuild, no deploy.
	</p>

	@foreach ($tenants as $tenant)
		@php $colors = (array) data_get($tenant->branding, 'colors', []); @endphp
		<div class="card mb-3">
			<div class="card-header d-flex align-items-center justify-content-between">
				<h5 class="mb-0">
					<i class="ti ti-palette me-2"></i>{{ $tenant->name }}
					<span class="text-muted fs-12 fw-normal ms-2">{{ $tenant->slug }}{{ $tenant->domain ? ' · ' . $tenant->domain : '' }} · {{ $tenant->plan }}</span>
				</h5>
				<span class="badge {{ $tenant->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ ucfirst($tenant->status) }}</span>
			</div>
			<div class="card-body">
				<form method="POST" action="{{ route('whitelabel.update', $tenant->id) }}">
					@csrf
					@method('PATCH')
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label">Business name</label>
							<input type="text" name="name" class="form-control" value="{{ $tenant->name }}" required>
						</div>
						<div class="col-md-8">
							<label class="form-label">Logo URL <span class="text-muted fs-12">(square image; blank = default mark)</span></label>
							<div class="d-flex gap-2 align-items-center">
								<input type="url" name="logo_url" class="form-control" placeholder="https://…/logo.png" value="{{ data_get($tenant->branding, 'logo_url') }}">
								@if (data_get($tenant->branding, 'logo_url'))
									<img src="{{ data_get($tenant->branding, 'logo_url') }}" alt="logo" style="height:38px;width:38px;object-fit:contain;border-radius:8px;border:1px solid #eee;">
								@endif
							</div>
						</div>
						@foreach ($colorFields as $key => $label)
							<div class="col-md-3 col-sm-6">
								<label class="form-label fs-12">{{ $label }}</label>
								<div class="d-flex gap-2 align-items-center">
									<input type="color" name="colors[{{ $key }}]" class="form-control form-control-color"
										value="{{ $colors[$key] ?? '#000000' }}" title="{{ $key }}">
									<code class="fs-12">{{ $colors[$key] ?? '—' }}</code>
								</div>
							</div>
						@endforeach
						<div class="col-12">
							<button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save branding</button>
							<span class="text-muted fs-12 ms-2">Students see the new look on their next page load.</span>
						</div>
					</div>
				</form>
			</div>
		</div>
	@endforeach

@endsection
