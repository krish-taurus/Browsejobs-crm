@extends('layouts.app')

@section('title', 'Ticket ' . $ticket->reference)

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-4 flex-wrap">
		<div>
			<a href="{{ route('support-tickets.index') }}" class="text-muted fs-13"><i class="ti ti-arrow-left me-1"></i>All tickets</a>
			<h4 class="mb-0 mt-1">{{ $ticket->reference }} — {{ $ticket->subject }}</h4>
			<span class="text-muted fs-13">{{ $ticket->student?->name }} · {{ $ticket->student?->phone }} · opened {{ $ticket->created_at?->diffForHumans() }}</span>
		</div>
		<form method="POST" action="{{ route('support-tickets.status', $ticket->id) }}" class="d-flex gap-2 align-items-center">
			@csrf
			<select name="status" class="form-select form-select-sm" style="width:auto;">
				@foreach (['open' => 'Open', 'in_progress' => 'In progress', 'waiting_on_student' => 'Waiting on student', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)
					<option value="{{ $value }}" {{ $ticket->status === $value ? 'selected' : '' }}>{{ $label }}</option>
				@endforeach
			</select>
			<button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
		</form>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif
	@if (session('error'))
		<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
	@endif

	<!-- Thread -->
	<div class="card mb-3">
		<div class="card-body">
			@forelse ($ticket->messages as $message)
				@if (! $message->is_internal)
					<div class="d-flex mb-3 {{ $message->author_type === 'student' ? '' : 'flex-row-reverse' }}">
						<div class="p-3 rounded {{ $message->author_type === 'student' ? 'bg-light' : 'bg-primary-transparent' }}" style="max-width: 75%;">
							<div class="fw-medium fs-12 mb-1 {{ $message->author_type === 'student' ? 'text-muted' : 'text-primary' }}">
								{{ $message->author_type === 'student' ? ($ticket->student?->name ?? 'Student') : 'Staff' }}
								<span class="text-muted fw-normal">· {{ $message->created_at?->format('d M, H:i') }}</span>
							</div>
							<div style="white-space: pre-wrap;">{{ $message->body }}</div>
						</div>
					</div>
				@endif
			@empty
				<p class="text-muted mb-0">No messages yet.</p>
			@endforelse
		</div>
	</div>

	<!-- Reply -->
	<div class="card">
		<div class="card-header"><h5 class="mb-0"><i class="ti ti-send me-2"></i>Reply to {{ $ticket->student?->name ?? 'student' }}</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('support-tickets.reply', $ticket->id) }}">
				@csrf
				<textarea name="body" class="form-control" rows="4" placeholder="Type your reply — the student is notified on their portal and messages…" required></textarea>
				<button type="submit" class="btn btn-primary mt-3"><i class="ti ti-send me-1"></i>Send reply</button>
			</form>
		</div>
	</div>

@endsection
