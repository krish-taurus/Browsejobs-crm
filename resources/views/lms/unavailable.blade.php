@extends('layouts.app')

@section('title', 'LMS Unavailable')

@section('content')

	<div class="card">
		<div class="card-body text-center py-5">
			<span class="avatar avatar-xl avatar-rounded bg-warning-transparent mb-3">
				<i class="ti ti-plug-connected-x text-warning fs-24"></i>
			</span>
			<h5 class="mb-2">LMS database is not reachable</h5>
			<p class="text-muted mb-0">
				This page reads live data from the BrowseJobs LMS database and it could not be reached right now.<br>
				Check that MySQL is running and the <code>LMS_DB_*</code> settings in <code>.env</code> are correct, then reload.
			</p>
		</div>
	</div>

@endsection
