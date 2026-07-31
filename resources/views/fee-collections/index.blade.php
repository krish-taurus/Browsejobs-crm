@extends('layouts.app')

@section('title', 'Fee Collections')

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<h4 class="mb-0">Fee Collections</h4>
		<div class="d-flex align-items-center gap-2">
			<form method="POST" action="{{ route('fee-collections.sync') }}">
				@csrf
				<button type="submit" class="btn btn-sm btn-outline-primary"><i class="ti ti-refresh me-1"></i>Sync payments from Razorpay</button>
			</form>
			<span class="text-muted fs-13"><i class="ti ti-database me-1"></i>Live data from the BrowseJobs LMS</span>
		</div>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			{{ session('success') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			{{ session('error') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	@endif

	<!-- Stat Cards -->
	<div class="row mb-3">
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Expected</p>
					<h4 class="mb-0">₹{{ number_format($stats['expected'] / 100) }}</h4>
					<span class="avatar avatar-rounded bg-info-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-report-money text-info"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Collected</p>
					<h4 class="mb-0 text-success">₹{{ number_format($stats['collected'] / 100) }}</h4>
					<span class="avatar avatar-rounded bg-success-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-circle-check text-success"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Outstanding</p>
					<h4 class="mb-0 text-danger">₹{{ number_format($stats['outstanding'] / 100) }}</h4>
					<span class="avatar avatar-rounded bg-danger-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-alert-circle text-danger"></i>
					</span>
				</div>
			</div>
		</div>
		<div class="col-md-3 col-sm-6 mb-3">
			<div class="card h-100">
				<div class="card-body position-relative">
					<p class="fw-medium mb-1">Needs Follow-up Call</p>
					<h4 class="mb-0 text-warning">{{ $stats['follow_ups'] }}</h4>
					<span class="avatar avatar-rounded bg-warning-transparent position-absolute top-0 end-0 m-3">
						<i class="ti ti-phone-call text-warning"></i>
					</span>
				</div>
			</div>
		</div>
	</div>
	<!-- End Stat Cards -->

	<!-- Filters -->
	<div class="card mb-3">
		<div class="card-body">
			<form method="GET" action="{{ route('fee-collections.index') }}" class="row g-3 align-items-end">
				<div class="col-md-3">
					<label class="form-label">Search Student</label>
					<input type="text" name="search" class="form-control" placeholder="Name, email or phone..." value="{{ request('search') }}">
				</div>
				<div class="col-md-3">
					<label class="form-label">Batch</label>
					<select name="batch_id" class="form-select">
						<option value="">All batches</option>
						@foreach ($batchOptions as $batch)
							<option value="{{ $batch->id }}" {{ request('batch_id') == $batch->id ? 'selected' : '' }}>
								{{ $batch->number }} — {{ $batch->course?->name }}
							</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label">Status</label>
					<select name="status" class="form-select">
						<option value="">All</option>
						<option value="blocked" {{ request('status') === 'blocked' ? 'selected' : '' }}>🔒 Blocked</option>
						<option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
						<option value="due_soon" {{ request('status') === 'due_soon' ? 'selected' : '' }}>Due within 7 days</option>
						<option value="on_track" {{ request('status') === 'on_track' ? 'selected' : '' }}>On track</option>
						<option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Fully paid</option>
					</select>
				</div>
				<div class="col-md-3 d-flex gap-2">
					<button type="submit" class="btn btn-primary">Apply</button>
					<a href="{{ route('fee-collections.index') }}" class="btn btn-light">Reset</a>
				</div>
			</form>
		</div>
	</div>
	<!-- End Filters -->

	<!-- Collections table -->
	<div class="card">
		<div class="card-header"><h5 class="card-title mb-0"><i class="ti ti-cash me-1"></i>Student Payments ({{ $rows->count() }})</h5></div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-hover mb-0 align-middle">
					<thead class="table-white">
						<tr>
							<th style="width: 48px;">#</th>
							<th>Student</th>
							<th>Batch</th>
							<th>Progress</th>
							<th>Collected</th>
							<th>Outstanding</th>
							<th>Next Due</th>
							<th>Status</th>
							<th>Last Payment</th>
							<th>Follow up</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($rows as $row)
							<tr>
								<td class="text-muted">{{ $loop->iteration }}</td>
								<td>
									<div class="fw-medium">{{ $row->student?->name ?? 'Unknown' }}</div>
									<div class="text-muted fs-12">{{ $row->student?->phone ?? $row->student?->email ?? '' }}</div>
								</td>
								<td>
									<div>{{ $row->batch?->number ?? '—' }}</div>
									<div class="text-muted fs-12">{{ $row->batch?->course?->name ?? '' }}</div>
								</td>
								<td>
									<span class="fw-medium">{{ $row->paid_count }}/{{ $row->instalment_count }}</span>
									<span class="text-muted fs-12">EMIs</span>
								</td>
								<td class="text-success fw-medium">₹{{ number_format($row->collected_paise / 100) }}</td>
								<td class="{{ $row->outstanding_paise > 0 ? 'text-danger fw-medium' : 'text-muted' }}">₹{{ number_format($row->outstanding_paise / 100) }}</td>
								<td>
									@if ($row->next_due_on !== null)
										<div>₹{{ number_format($row->next_amount_paise / 100) }}</div>
										<div class="fs-12 {{ $row->overdue_days > 0 ? 'text-danger' : 'text-muted' }}">
											{{ $row->next_due_on->format('d M Y') }}
											@if ($row->overdue_days > 0) · {{ $row->overdue_days }}d late @endif
										</div>
									@else
										<span class="text-muted">—</span>
									@endif
								</td>
								<td>
									@if ($row->bucket === 'paid')
										<span class="badge badge-soft-success">Fully Paid</span>
									@elseif ($row->bucket === 'blocked')
										<span class="badge badge-soft-danger"><i class="ti ti-lock me-1"></i>{{ ucfirst($row->block) }} Block</span>
									@elseif ($row->bucket === 'overdue')
										<span class="badge badge-soft-danger">Overdue</span>
									@elseif ($row->bucket === 'due_soon')
										<span class="badge badge-soft-warning">Due Soon</span>
									@else
										<span class="badge badge-soft-info">On Track</span>
									@endif
								</td>
								<td class="text-muted fs-12">{{ $row->last_paid_at?->format('d M Y') ?? '—' }}</td>
								<td>
									<div class="d-flex gap-1 flex-wrap">
										@if ($row->next_instalment_id !== null)
											<form method="POST" action="{{ route('fee-collections.send-link', $row->next_instalment_id) }}">
												@csrf
												<button type="submit" class="btn btn-sm btn-primary" title="Create the Razorpay link and WhatsApp it to the student">
													<i class="ti ti-send me-1"></i>{{ $row->has_link ? 'Resend Link' : 'Send Link' }}
												</button>
											</form>
										@endif
										@if ($row->student?->phone)
											<a href="tel:{{ $row->student->phone }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-phone me-1"></i>Call</a>
											<a href="https://wa.me/91{{ preg_replace('/\D/', '', substr($row->student->phone, -10)) }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="ti ti-brand-whatsapp"></i></a>
										@endif
									</div>
								</td>
							</tr>
						@empty
							<tr><td colspan="10" class="text-center text-muted py-4">No fee plans found.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

@endsection
