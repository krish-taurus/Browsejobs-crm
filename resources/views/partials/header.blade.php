<!-- Topbar Start -->
<header class="navbar-header">
	<div class="page-container topbar-menu">
		<div class="d-flex align-items-center gap-2">

			<!-- Logo -->
			<a href="{{ url('/') }}" class="logo">

				<!-- Logo Normal -->
				<span class="logo-light">
					<span class="logo-lg"><img src="{{ asset('build/assets/img/logo.svg') }}" alt="logo"></span>
					<span class="logo-sm"><img src="{{ asset('build/assets/img/logo-small.svg') }}" alt="small logo"></span>
				</span>

				<!-- Logo Dark -->
				<span class="logo-dark">
					<span class="logo-lg"><img src="{{ asset('build/assets/img/logo-white.svg') }}" alt="dark logo"></span>
				</span>
			</a>

			<!-- Sidebar Mobile Button -->
			<a id="mobile_btn" class="mobile-btn" href="#sidebar">
				<i class="ti ti-menu-deep fs-24"></i>
			</a>

			<button class="sidenav-toggle-btn btn border-0 p-0" id="toggle_btn2">
				<i class="ti ti-arrow-bar-to-right"></i>
			</button>

			<!-- Search -->
			<div class="me-auto d-flex align-items-center header-search d-lg-flex d-none">
				<!-- Search -->
				<div class="input-icon position-relative me-2">
					<input type="text" class="form-control" placeholder="Search Keyword">
					<span class="input-icon-addon d-inline-flex p-0 header-search-icon"><i
							class="ti ti-command"></i></span>
				</div>
				<!-- /Search -->
			</div>

		</div>

		<div class="d-flex align-items-center">

			<!-- Search for Mobile -->
			<div class="header-item d-flex d-lg-none me-2">
				<button class="topbar-link btn" data-bs-toggle="modal" data-bs-target="#searchModal"
					type="button">
					<i class="ti ti-search fs-16"></i>
				</button>
			</div>

			<!-- Minimize -->
			<div class="header-item">
				<div class="dropdown me-2">
					<a href="javascript:void(0);" class="btn topbar-link btnFullscreen"><i
							class="ti ti-maximize"></i></a>
				</div>
			</div>
			<!-- Minimize -->

			<!-- Light/Dark Mode Button -->
			<div class="header-item d-none d-sm-flex me-2">
				<button class="topbar-link btn topbar-link" id="light-dark-mode" type="button">
					<i class="ti ti-moon fs-16"></i>
				</button>
			</div>

			<div class="header-line"></div>

			<!-- message -->
			<div class="header-item">
				<div class="dropdown me-2">
					<a href="{{ url('chat') }}" class="btn topbar-link">
						<i class="ti ti-message-circle-exclamation"></i>
						<span class="badge rounded-pill">&nbsp;</span>
					</a>
				</div>
			</div>

			<!-- Enable Push Notifications Button -->
			<div class="header-item">
				<button type="button" id="enable-push-btn" class="btn btn-sm btn-outline-primary d-none me-2">
					<i class="ti ti-bell-ringing me-1"></i> Enable Push
				</button>
			</div>

			<!-- Notification Dropdown -->
			<div class="header-item">
				<div class="dropdown me-2">

					<button class="topbar-link btn topbar-link dropdown-toggle drop-arrow-none"
						data-bs-toggle="dropdown" data-bs-offset="0,24" type="button" aria-haspopup="false"
						aria-expanded="false">
						<i class="ti ti-bell-check fs-18 animate-ring"></i>
						<span id="notif-badge" class="badge rounded-pill {{ $headerUnreadCount > 0 ? '' : 'd-none' }}">
							{{ $headerUnreadCount }}
						</span>
					</button>

					<div class="dropdown-menu p-0 dropdown-menu-end dropdown-menu-lg"
						style="min-height: 200px;">

						<div class="p-2 border-bottom d-flex align-items-center justify-content-between">
							<h6 class="m-0 fs-16 fw-semibold">Notifications</h6>
							<div class="d-flex align-items-center gap-2">
								<button type="button" id="test-push-btn" class="btn btn-link btn-sm p-0 fs-12">Test push</button>
								<form method="POST" action="{{ route('notifications.read-all') }}">
									@csrf
									<button type="submit" class="btn btn-link btn-sm p-0 fs-12">Mark all as read</button>
								</form>
							</div>
						</div>

						<!-- Notification Body -->
						<div id="notif-list" class="notification-body rounded-0" style="max-height: 360px; overflow-y: auto;">
							@forelse ($headerNotifications as $n)
								<a href="{{ route('notifications.read', $n->id) }}"
									class="dropdown-item notification-item py-3 text-wrap border-bottom d-block text-decoration-none {{ $n->read_at ? '' : 'bg-light' }}">
									<div class="d-flex">
										<div class="me-2 position-relative flex-shrink-0">
											<i class="{{ $n->data['icon'] ?? 'ti ti-bell' }} fs-20"></i>
										</div>
										<div class="flex-grow-1">
											<p class="mb-0 fw-medium text-dark">{{ $n->data['title'] ?? 'Notification' }}</p>
											<p class="mb-1 text-wrap fs-13">{{ $n->data['message'] ?? '' }}</p>
											<span class="fs-12"><i class="ti ti-clock me-1"></i>{{ $n->created_at->diffForHumans() }}</span>
										</div>
									</div>
								</a>
							@empty
								<div class="p-3 text-center text-muted fs-13">No notifications yet.</div>
							@endforelse
						</div>

						<!-- View All-->
						<div class="p-2 rounded-bottom border-top text-center">
							<a href="{{ route('notifications.index') }}"
								class="text-center text-decoration-underline fs-14 mb-0">
								View All Notifications
							</a>
						</div>

					</div>
				</div>
			</div>

			<!-- User Dropdown -->
			<div class="dropdown profile-dropdown d-flex align-items-center justify-content-center">
				<a href="javascript:void(0);"
					class="topbar-link dropdown-toggle drop-arrow-none position-relative"
					data-bs-toggle="dropdown" data-bs-offset="0,22" aria-haspopup="false" aria-expanded="false">
					<img src="{{ asset('build/assets/img/users/user-40.jpg') }}" width="38" class="rounded-1 d-flex"
						alt="user-image">
					<span class="online text-success"><i
							class="ti ti-circle-filled d-flex bg-white rounded-circle border border-1 border-white"></i></span>
				</a>
				<div class="dropdown-menu dropdown-menu-end dropdown-menu-md p-2">

					<div class="d-flex align-items-center bg-light rounded-3 p-2 mb-2">
						<img src="{{ asset('build/assets/img/users/user-40.jpg') }}" class="rounded-circle" width="42" height="42"
							alt="Img">
						<div class="ms-2">
							<p class="fw-medium text-dark mb-0">{{ auth()->user()->full_name ?? 'Katherine Brooks' }}</p>
							<span class="d-block fs-13">Installer</span>
						</div>
					</div>

					<!-- Item-->
					<a href="{{ url('profile-settings') }}" class="dropdown-item">
						<i class="ti ti-user-circle me-1 align-middle"></i>
						<span class="align-middle">Profile Settings</span>
					</a>

					<!-- item -->
					<div
						class="form-check form-switch form-check-reverse d-flex align-items-center justify-content-between dropdown-item mb-0">
						<label class="form-check-label" for="notify"><i
								class="ti ti-bell"></i>Notifications</label>
						<input class="form-check-input me-0" type="checkbox" role="switch" id="notify">
					</div>

					<!-- Item-->
					<a href="javascript:void(0);" class="dropdown-item">
						<i class="ti ti-help-circle me-1 align-middle"></i>
						<span class="align-middle">Help & Support</span>
					</a>

					<!-- Item-->
					<a href="{{ route('password.change') }}" class="dropdown-item">
						<i class="ti ti-lock me-1 align-middle"></i>
						<span class="align-middle">Change Password</span>
					</a>

					<!-- Item-->
					<a href="{{ url('profile-settings') }}" class="dropdown-item">
						<i class="ti ti-settings me-1 align-middle"></i>
						<span class="align-middle">Settings</span>
					</a>

					<!-- Item-->
					<div class="pt-2 mt-2 border-top">
						<form method="POST" action="{{ route('logout') }}">
							@csrf
							<button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start">
								<i class="ti ti-logout me-1 fs-17 align-middle"></i>
								<span class="align-middle">Sign Out</span>
							</button>
						</form>
					</div>
				</div>
			</div>

		</div>
	</div>
</header>
@push('scripts')
	<script>
		(function () {
			const VAPID_PUBLIC_KEY = "{{ config('webpush.vapid.public_key') }}";
			const enableBtn = document.getElementById('enable-push-btn');

			function urlBase64ToUint8Array(base64String) {
				const padding = '='.repeat((4 - base64String.length % 4) % 4);
				const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
				const rawData = window.atob(base64);
				return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
			}

			// POST the subscription to the server. Runs on every subscribe AND
			// once per session for existing subscriptions, so the server row is
			// always current — even after a DB prune or a different user logging
			// in on this browser.
			async function syncSubscription(subscription) {
				await fetch("{{ route('push-subscriptions.store') }}", {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
					},
					body: JSON.stringify(subscription),
				});
			}

			async function subscribeToPush() {
				const registration = await navigator.serviceWorker.ready;

				const subscription = await registration.pushManager.subscribe({
					userVisibleOnly: true,
					applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
				});

				await syncSubscription(subscription);

				if (enableBtn) enableBtn.classList.add('d-none');
			}

			// Report push-setup progress to the server log so problems on any
			// user's machine are diagnosable without their browser console.
			function diag(step, detail) {
				try {
					fetch("{{ route('push-subscriptions.diag') }}", {
						method: 'POST',
						keepalive: true,
						headers: {
							'Content-Type': 'application/json',
							'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
						},
						body: JSON.stringify({ step: step, detail: String(detail || '') }),
					}).catch(function () {});
				} catch (e) { /* diagnostics must never break the flow */ }
			}

			async function init() {
				if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
					diag('unsupported', navigator.userAgent);
					return;
				}

				diag('init-start', 'permission=' + Notification.permission);

				// updateViaCache 'none' + update() make every page load pick up a
				// changed service-worker.js (which then self-activates immediately).
				const swRegistration = await navigator.serviceWorker.register('/service-worker.js', { updateViaCache: 'none' });
				swRegistration.update().catch(function () { /* offline — next load retries */ });

				const registration = await navigator.serviceWorker.ready;
				const existingSubscription = await registration.pushManager.getSubscription();

				// Track WHO the browser's subscription was last synced for. Any
				// time the logged-in user differs (fresh session OR an account
				// switch A→B→A), re-sync so pushes follow the current user.
				const currentUserId = '{{ auth()->id() }}';
				const lastSyncedUser = sessionStorage.getItem('push-synced-user');

				diag('sw-ready', 'hasSub=' + !!existingSubscription + ' lastSynced=' + lastSyncedUser + ' endpoint=' + (existingSubscription ? existingSubscription.endpoint.slice(0, 60) : '-'));

				if (Notification.permission === 'granted') {
					if (!existingSubscription) {
						await subscribeToPush();
						sessionStorage.setItem('push-synced-user', currentUserId);
						diag('subscribed-new', '');
					} else if (lastSyncedUser !== currentUserId) {
						await syncSubscription(existingSubscription);
						sessionStorage.setItem('push-synced-user', currentUserId);
						diag('resynced', 'from=' + lastSyncedUser);
					} else {
						diag('already-synced', '');
					}
					return;
				}

				// Permission not granted yet ('default' or 'denied'). Browsers
				// suppress permission prompts that aren't caused by a click, so
				// show the button and let the user trigger the prompt themselves.
				diag('permission-not-granted', Notification.permission);
				if (enableBtn) enableBtn.classList.remove('d-none');
			}

			// "Test push" sends a real web push to yourself so you can verify
			// this exact browser + Windows will show the banner and play sound.
			const testPushBtn = document.getElementById('test-push-btn');
			if (testPushBtn) {
				testPushBtn.addEventListener('click', function () {
					fetch("{{ route('push-subscriptions.test') }}", {
						method: 'POST',
						headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
					})
						.then(function (res) { return res.json(); })
						.then(function (data) {
							if (!data.success) {
								alert(data.message || 'Could not send the test notification.');
							}
						})
						.catch(function () { alert('Test push request failed — is the server running?'); });
				});
			}

			if (enableBtn) {
				enableBtn.addEventListener('click', async function () {
					if (Notification.permission === 'denied') {
						alert("Notifications are blocked for this site. Allow them in your browser's site settings (padlock icon in the address bar), then click Enable Push again.");
						return;
					}
					const permission = await Notification.requestPermission();
					if (permission === 'granted') {
						subscribeToPush();
					}
				});
			}

			init().catch(function (e) { diag('init-failed', (e && e.name) + ': ' + (e && e.message)); console.warn('Push setup failed:', e); });
		})();
	</script>
@endpush
<!-- Topbar End -->

<!-- Search Modal -->
<div class="modal fade" id="searchModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content bg-transparent">
			<div class="card shadow-none mb-0">
				<div class="px-3 py-2 d-flex flex-row align-items-center" id="search-top">
					<i class="ti ti-search fs-22"></i>
					<input type="search" class="form-control border-0" placeholder="Search">
					<button type="button" class="btn p-0" data-bs-dismiss="modal" aria-label="Close"><i
							class="ti ti-x fs-22"></i></button>
				</div>
			</div>
		</div>
	</div>
</div>

@push('scripts')
	<script>
		// Polls every 20s for new notifications and refreshes the bell badge
		// + dropdown list without a full page reload. Not true push/WebSocket
		// real-time, but near-instant in practice for an internal HR tool.
		(function () {
			const POLL_URL = "{{ route('notifications.poll') }}";
			const READ_ALL_URL = "{{ route('notifications.read-all') }}";
			const badge = document.getElementById('notif-badge');
			const list = document.getElementById('notif-list');

			function iconHtml(icon) {
				return `<i class="${icon} fs-20"></i>`;
			}

			function render(items) {
				if (!items.length) {
					list.innerHTML = '<div class="p-3 text-center text-muted fs-13">No notifications yet.</div>';
					return;
				}

				list.innerHTML = items.map(function (n) {
					const unreadClass = n.is_read ? '' : 'bg-light';
					return `
						<a href="/notifications/${n.id}/read" class="dropdown-item notification-item py-3 text-wrap border-bottom d-block text-decoration-none ${unreadClass}">
							<div class="d-flex">
								<div class="me-2 position-relative flex-shrink-0">${iconHtml(n.icon)}</div>
								<div class="flex-grow-1">
									<p class="mb-0 fw-medium text-dark">${n.title}</p>
									<p class="mb-1 text-wrap fs-13">${n.message}</p>
									<span class="fs-12"><i class="ti ti-clock me-1"></i>${n.time}</span>
								</div>
							</div>
						</a>`;
				}).join('');
			}

			// ---- Notification sound (foreground) ----
			let lastUnread = null;
			let notifyCtx = null;
			function primeAudio() {
				try {
					notifyCtx = notifyCtx || new (window.AudioContext || window.webkitAudioContext)();
					if (notifyCtx.state === 'suspended') notifyCtx.resume();
				} catch (e) { /* audio unsupported */ }
			}
			window.addEventListener('click', primeAudio, { once: true });
			window.addEventListener('keydown', primeAudio, { once: true });
			function playNotificationSound() {
				try {
					if (!notifyCtx) primeAudio();
					if (!notifyCtx || notifyCtx.state !== 'running') return;
					const ctx = notifyCtx;
					const o = ctx.createOscillator();
					const g = ctx.createGain();
					o.connect(g); g.connect(ctx.destination);
					o.type = 'sine'; o.frequency.value = 880;
					g.gain.setValueAtTime(0.001, ctx.currentTime);
					g.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.02);
					g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
					o.start(); o.stop(ctx.currentTime + 0.5);
				} catch (e) { /* audio not allowed until user interacts */ }
			}

			// The service worker asks us to chime when a push arrives while a tab is open.
			navigator.serviceWorker && navigator.serviceWorker.addEventListener('message', function (e) {
				if (e.data && e.data.type === 'play-notification-sound') playNotificationSound();
			});

			function poll() {
				fetch(POLL_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
					.then(function (res) { return res.json(); })
					.then(function (data) {
						if (data.unread_count > 0) {
							badge.textContent = data.unread_count;
							badge.classList.remove('d-none');
						} else {
							badge.classList.add('d-none');
						}
						// New notification arrived since last poll → chime.
						if (lastUnread !== null && data.unread_count > lastUnread) playNotificationSound();
						lastUnread = data.unread_count;
						render(data.items);
					})
					.catch(function () { /* silent fail — next poll will retry */ });
			}

			poll();
			setInterval(poll, 20000);
		})();
	</script>
@endpush