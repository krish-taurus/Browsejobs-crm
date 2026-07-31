@extends('layouts.app')

@section('title', 'Roles & Permissions')

@push('styles')
	<style>
		/* The matrix is wide (one column per role) and tall (one row per
		   permission), so both the header row and the permission column are
		   pinned — you can always see which role and which permission a
		   checkbox belongs to. */
		.perm-scroll { max-height: calc(100vh - 300px); overflow: auto; border: 1px solid var(--bs-border-color); border-radius: .5rem; }
		.perm-table { margin: 0; border-collapse: separate; border-spacing: 0; }
		.perm-table th, .perm-table td { border-right: 1px solid var(--bs-border-color); border-bottom: 1px solid var(--bs-border-color); background: var(--bs-body-bg); }
		.perm-table th:last-child, .perm-table td:last-child { border-right: 0; }

		/* Pinned header row. */
		.perm-table thead th { position: sticky; top: 0; z-index: 3; vertical-align: bottom; padding: .6rem .5rem; font-size: .78rem; line-height: 1.2; text-align: center; box-shadow: inset 0 -1px 0 var(--bs-border-color); }

		/* Pinned first column. */
		.perm-table th.perm-col, .perm-table td.perm-col { position: sticky; left: 0; z-index: 2; text-align: left; min-width: 260px; box-shadow: inset -1px 0 0 var(--bs-border-color); }
		.perm-table thead th.perm-col { z-index: 5; }

		/* Module group rows stay readable while scrolling sideways. */
		.perm-table tr.perm-group td { position: sticky; left: 0; z-index: 2; background: var(--bs-tertiary-bg); font-weight: 600; letter-spacing: .02em; }

		.perm-table tbody tr:hover td { background: var(--bs-secondary-bg); }
		.perm-table td.is-col-hover, .perm-table th.is-col-hover { background: var(--bs-secondary-bg); }

		.perm-table td { padding: .5rem; text-align: center; }
		.perm-table .form-check-input { cursor: pointer; width: 1.05rem; height: 1.05rem; }

		.perm-role { display: block; font-weight: 600; }
		.perm-count { display: inline-block; margin-top: .25rem; font-size: .68rem; font-weight: 600; padding: .05rem .4rem; border-radius: 999px; background: var(--bs-secondary-bg); color: var(--bs-secondary-color); }
		.perm-toggle { font-size: .65rem; text-transform: uppercase; letter-spacing: .03em; cursor: pointer; color: var(--bs-primary); background: none; border: 0; padding: 0; }

		.perm-savebar { position: sticky; bottom: 0; z-index: 6; background: var(--bs-body-bg); border-top: 1px solid var(--bs-border-color); padding: .75rem 0; }
		.perm-row-all { opacity: 0; transition: opacity .12s; }
		.perm-table tbody tr:hover .perm-row-all { opacity: 1; }
	</style>
@endpush

@section('content')

	<div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
		<div>
			<h4 class="mb-0">Roles &amp; Permissions</h4>
			<span class="text-muted fs-13">{{ $permissions->count() }} permissions × {{ $roles->count() }} roles — the header and permission column stay pinned while you scroll</span>
		</div>
		<a href="{{ route('permissions.create') }}" class="btn btn-outline-primary">
			<i class="ti ti-plus me-1"></i> New Permission
		</a>
	</div>

	@if (session('success'))
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			{{ session('success') }}
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	@endif

	<form method="POST" action="{{ route('roles-permissions.update') }}" id="perm-form">
		@csrf

		<div class="card">
			<div class="card-body">
				<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
					<input type="search" id="perm-search" class="form-control form-control-sm" style="width: 240px;" placeholder="Filter permissions…">
					<span class="text-muted fs-12" id="perm-visible"></span>
					<span class="ms-auto text-muted fs-12"><i class="ti ti-bulb me-1"></i>Click a role name to tick or clear that whole column</span>
				</div>

				<div class="perm-scroll">
					<table class="table perm-table" id="perm-table">
						<thead>
							<tr>
								<th class="perm-col">Permission</th>
								@foreach ($roles as $role)
									@php $granted = count($assigned[$role->id] ?? []); @endphp
									<th data-col="{{ $loop->index + 1 }}" style="min-width: 96px;">
										<button type="button" class="perm-toggle perm-col-all" data-role="{{ $role->id }}" title="Tick or clear every permission for {{ $role->role_name }}">
											<span class="perm-role">{{ $role->role_name }}</span>
										</button>
										<span class="perm-count" data-count-for="{{ $role->id }}">{{ $granted }}</span>
									</th>
								@endforeach
							</tr>
						</thead>
						<tbody>
							@php $currentModule = null; @endphp
							@foreach ($permissions as $permission)
								@if ($permission->module !== $currentModule)
									@php $currentModule = $permission->module; @endphp
									<tr class="perm-group" data-group>
										<td colspan="{{ $roles->count() + 1 }}">{{ $currentModule ?? 'General' }}</td>
									</tr>
								@endif
								<tr data-perm="{{ mb_strtolower($permission->name.' '.$permission->slug.' '.$permission->module) }}">
									<td class="perm-col">
										<div class="d-flex align-items-center justify-content-between gap-2">
											<div>
												<div class="fw-medium">{{ $permission->name }}</div>
												<div class="fs-12 text-muted">{{ $permission->slug }}</div>
											</div>
											<button type="button" class="perm-toggle perm-row-all" title="Tick or clear this permission for every role">all</button>
										</div>
									</td>
									@foreach ($roles as $role)
										<td data-col="{{ $loop->index + 1 }}">
											<input type="checkbox"
												class="form-check-input"
												data-role="{{ $role->id }}"
												name="permissions[{{ $role->id }}][]"
												value="{{ $permission->id }}"
												@checked(in_array($permission->id, $assigned[$role->id] ?? []))>
										</td>
									@endforeach
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				<div class="perm-savebar d-flex align-items-center gap-3 mt-0">
					<button type="submit" class="btn btn-primary">
						<i class="ti ti-device-floppy me-1"></i> Save Permissions
					</button>
					<span class="text-muted fs-13" id="perm-dirty">No changes yet</span>
				</div>
			</div>
		</div>
	</form>

@endsection

@push('scripts')
	<script>
		(function () {
			var table = document.getElementById('perm-table');
			if (!table) { return; }

			var boxes = Array.from(table.querySelectorAll('tbody input[type=checkbox]'));
			var initial = new Map(boxes.map(function (b) { return [b, b.checked]; }));
			var dirtyLabel = document.getElementById('perm-dirty');

			function refreshCounts() {
				var counts = {};
				boxes.forEach(function (b) {
					if (b.checked) { counts[b.dataset.role] = (counts[b.dataset.role] || 0) + 1; }
				});
				table.querySelectorAll('[data-count-for]').forEach(function (el) {
					el.textContent = counts[el.dataset.countFor] || 0;
				});

				var changed = boxes.filter(function (b) { return initial.get(b) !== b.checked; }).length;
				dirtyLabel.textContent = changed === 0
					? 'No changes yet'
					: changed + (changed === 1 ? ' change' : ' changes') + ' not saved yet';
				dirtyLabel.className = changed === 0 ? 'text-muted fs-13' : 'text-warning fw-semibold fs-13';
			}

			table.addEventListener('change', function (e) {
				if (e.target.matches('input[type=checkbox]')) { refreshCounts(); }
			});

			// Whole column: tick everything, or clear it if it is already full.
			table.querySelectorAll('.perm-col-all').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var mine = boxes.filter(function (b) {
						return b.dataset.role === btn.dataset.role && b.closest('tr').style.display !== 'none';
					});
					var fill = mine.some(function (b) { return !b.checked; });
					mine.forEach(function (b) { b.checked = fill; });
					refreshCounts();
				});
			});

			// Whole row: same idea, across every role.
			table.querySelectorAll('.perm-row-all').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var mine = Array.from(btn.closest('tr').querySelectorAll('input[type=checkbox]'));
					var fill = mine.some(function (b) { return !b.checked; });
					mine.forEach(function (b) { b.checked = fill; });
					refreshCounts();
				});
			});

			// Highlight the column under the cursor so a checkbox 15 columns
			// across is still traceable back to its role.
			table.addEventListener('mouseover', function (e) {
				var cell = e.target.closest('td[data-col], th[data-col]');
				table.querySelectorAll('.is-col-hover').forEach(function (el) { el.classList.remove('is-col-hover'); });
				if (!cell) { return; }
				table.querySelectorAll('[data-col="' + cell.dataset.col + '"]').forEach(function (el) { el.classList.add('is-col-hover'); });
			});
			table.addEventListener('mouseleave', function () {
				table.querySelectorAll('.is-col-hover').forEach(function (el) { el.classList.remove('is-col-hover'); });
			});

			// Filter rows; module headings hide when their group is empty.
			var search = document.getElementById('perm-search');
			var visibleLabel = document.getElementById('perm-visible');

			function applyFilter() {
				var term = (search.value || '').trim().toLowerCase();
				var rows = Array.from(table.querySelectorAll('tbody tr'));
				var shown = 0;

				rows.forEach(function (tr) {
					if (tr.hasAttribute('data-group')) { return; }
					var match = !term || tr.dataset.perm.includes(term);
					tr.style.display = match ? '' : 'none';
					if (match) { shown++; }
				});

				// A group heading is only useful if something under it survived.
				var group = null, groupHasRows = false;
				rows.forEach(function (tr) {
					if (tr.hasAttribute('data-group')) {
						if (group) { group.style.display = groupHasRows ? '' : 'none'; }
						group = tr; groupHasRows = false;
					} else if (tr.style.display !== 'none') {
						groupHasRows = true;
					}
				});
				if (group) { group.style.display = groupHasRows ? '' : 'none'; }

				visibleLabel.textContent = term ? shown + ' of {{ $permissions->count() }} shown' : '';
			}

			search?.addEventListener('input', applyFilter);

			// Don't lose a half-finished matrix to a stray click.
			window.addEventListener('beforeunload', function (e) {
				if (boxes.some(function (b) { return initial.get(b) !== b.checked; })) {
					e.preventDefault();
					e.returnValue = '';
				}
			});
			document.getElementById('perm-form').addEventListener('submit', function () {
				initial = new Map(boxes.map(function (b) { return [b, b.checked]; }));
			});

			refreshCounts();
		})();
	</script>
@endpush
