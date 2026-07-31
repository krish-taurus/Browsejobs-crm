@extends('layouts.app')

@section('title', 'Social Media Dashboard')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Social Media Dashboard</h4>
		<div class="dropdown">
			<button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
				<i class="ti ti-settings me-1"></i> Manage Accounts
			</button>
			<ul class="dropdown-menu dropdown-menu-end">
				<li><a class="dropdown-item" href="{{ route('social-accounts.instagram.index') }}"><i class="ti ti-brand-instagram me-1"></i> Instagram</a></li>
				<li><a class="dropdown-item" href="{{ route('social-accounts.youtube.index') }}"><i class="ti ti-brand-youtube me-1"></i> YouTube</a></li>
				<li><a class="dropdown-item" href="{{ route('social-accounts.linkedin.index') }}"><i class="ti ti-brand-linkedin me-1"></i> LinkedIn</a></li>
			</ul>
		</div>
	</div>

	<!-- Filters -->
	<div class="card mb-3">
		<div class="card-body">
			<form method="GET" action="{{ route('social-dashboard.index') }}" class="row g-3 align-items-end">
				<div class="col-md-4">
					<label class="form-label">Account</label>
					<select name="account_id" class="form-select" onchange="this.form.submit()">
						@forelse ($accounts as $acc)
							<option value="{{ $acc->id }}" @selected($selectedAccountId == $acc->id)>{{ $acc->label }}</option>
						@empty
							<option value="">No accounts connected</option>
						@endforelse
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label">Date Range</label>
					<select name="days" class="form-select" onchange="this.form.submit()">
						<option value="7" @selected($days == 7)>Last 7 Days</option>
						<option value="30" @selected($days == 30)>Last 30 Days</option>
						<option value="90" @selected($days == 90)>Last 90 Days</option>
					</select>
				</div>
			</form>
		</div>
	</div>
	<!-- End Filters -->

	@if ($accounts->isEmpty())
		<div class="alert alert-info">
			No active social accounts connected yet. Connect
			<a href="{{ route('social-accounts.instagram.create') }}">Instagram</a>,
			<a href="{{ route('social-accounts.youtube.create') }}">YouTube</a>, or
			<a href="{{ route('social-accounts.linkedin.index') }}">LinkedIn</a> to get started.
		</div>
	@else
		<!-- Overview Cards -->
		<div class="row mb-3">
			<div class="col-md-4">
				<div class="card">
					<div class="card-body">
						<p class="fw-medium mb-1">Current Followers</p>
						<h4 class="mb-0">{{ number_format($latest?->followers_count ?? 0) }}</h4>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card">
					<div class="card-body">
						<p class="fw-medium mb-1">Reach (Latest Day)</p>
						<h4 class="mb-0">{{ number_format($latest?->reach ?? 0) }}</h4>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card">
					<div class="card-body">
						<p class="fw-medium mb-1">Posts Today</p>
						<h4 class="mb-0">{{ $latest?->posts_today ?? 0 }}</h4>
					</div>
				</div>
			</div>
		</div>
		<!-- End Overview Cards -->

		<div class="row">
			<div class="col-md-6 mb-3">
				<div class="card h-100">
					<div class="card-header"><h6 class="mb-0">Follower Growth</h6></div>
					<div class="card-body">
						<canvas id="followersChart" height="180"></canvas>
					</div>
				</div>
			</div>
			<div class="col-md-6 mb-3">
				<div class="card h-100">
					<div class="card-header"><h6 class="mb-0">Daily Reach</h6></div>
					<div class="card-body">
						<canvas id="reachChart" height="180"></canvas>
					</div>
				</div>
			</div>
			<div class="col-md-12">
				<div class="card">
					<div class="card-header"><h6 class="mb-0">Posts Per Day</h6></div>
					<div class="card-body">
						<canvas id="postsChart" height="120"></canvas>
					</div>
				</div>
			</div>
		</div>
	@endif

@endsection

@push('scripts')
	<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
	<script>
		const labels = @json($chartData['labels'] ?? []);
		const followers = @json($chartData['followers'] ?? []);
		const reach = @json($chartData['reach'] ?? []);
		const posts = @json($chartData['posts'] ?? []);

		if (document.getElementById('followersChart')) {
			new Chart(document.getElementById('followersChart'), {
				type: 'line',
				data: {
					labels: labels,
					datasets: [{
						label: 'Followers',
						data: followers,
						borderColor: '#4299e1',
						backgroundColor: 'rgba(66,153,225,0.1)',
						fill: true,
						tension: 0.3,
					}],
				},
				options: { responsive: true, plugins: { legend: { display: false } } },
			});

			new Chart(document.getElementById('reachChart'), {
				type: 'line',
				data: {
					labels: labels,
					datasets: [{
						label: 'Reach',
						data: reach,
						borderColor: '#48bb78',
						backgroundColor: 'rgba(72,187,120,0.1)',
						fill: true,
						tension: 0.3,
					}],
				},
				options: { responsive: true, plugins: { legend: { display: false } } },
			});

			new Chart(document.getElementById('postsChart'), {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [{
						label: 'Posts',
						data: posts,
						backgroundColor: '#ed8936',
					}],
				},
				options: { responsive: true, plugins: { legend: { display: false } } },
			});
		}
	</script>
@endpush