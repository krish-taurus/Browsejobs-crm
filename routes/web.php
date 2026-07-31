<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BatchFunnelController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CallerDigitalWebhookController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClassAttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\ExpenseAnalyticsController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeeCollectionController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GradingController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\IncentiveController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadsDashboardController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LiveBatchController;
use App\Http\Controllers\LoginReminderController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MasterclassController;
use App\Http\Controllers\MeetingReportController;
use App\Http\Controllers\MentorManagementController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\MockTestResultController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RevenueDashboardController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SitePagesController;
use App\Http\Controllers\SiteSeoController;
use App\Http\Controllers\SocialAccountController;
use App\Http\Controllers\SocialAlertController;
use App\Http\Controllers\SocialAnalyticsController;
use App\Http\Controllers\SocialDashboardController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPulseController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WebsiteContentController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\WhitelabelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [LoginController::class, 'loginForm'])->name('login.show');
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Forgot password (guest) — email OTP flow. Throttled: OTP emails cost + brute-force guard.
Route::get('/forgot-password', [PasswordController::class, 'showForgot'])->name('password.forgot');
Route::post('/forgot-password/send-otp', [PasswordController::class, 'sendForgotOtp'])
    ->middleware('throttle:5,10')->name('password.forgot.send');
Route::post('/forgot-password/reset', [PasswordController::class, 'resetWithOtp'])
    ->middleware('throttle:10,10')->name('password.forgot.reset');

// Public legal pages (used as the app's Privacy Policy URL for LinkedIn / Google / Meta).
Route::view('/privacy-policy', 'legal.privacy-policy')->name('privacy-policy');

// Public, token/secret-protected endpoints (no session auth). Rate-limited to curb abuse —
// each captured lead can fan out to notifications + a paid AI call.
Route::post('/api/leads/capture', [LeadController::class, 'apiCapture'])
    ->middleware('throttle:30,1')->name('leads.capture');
Route::post('/webhooks/caller-digital', [CallerDigitalWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')->name('webhooks.caller-digital');

// Meta WhatsApp Cloud API webhook: GET = verification handshake, POST = events.
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:300,1')->name('webhooks.whatsapp');

// ---- User Management ----
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');
    Route::resource('manage-users', UserManagementController::class)->parameters(['manage-users' => 'user'])->names('users');
    Route::patch('manage-users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::resource('menu-items', MenuItemController::class);
    Route::resource('permissions', PermissionController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::get('roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');
    Route::post('roles-permissions', [RolePermissionController::class, 'update'])->name('roles-permissions.update');

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');        // Summary (main page)
        Route::get('/log', [AttendanceController::class, 'log'])->name('log');          // Detailed session log
        Route::get('/create', [AttendanceController::class, 'create'])->name('create');
        Route::post('/', [AttendanceController::class, 'store'])->name('store');
        Route::patch('/{log}/force-logout', [AttendanceController::class, 'forceLogout'])->name('force-logout');
    });

    Route::prefix('leave-requests')->name('leave-requests.')->group(function () {
        Route::get('/', [LeaveRequestController::class, 'index'])->name('index');
        Route::get('/create', [LeaveRequestController::class, 'create'])->name('create');
        Route::post('/', [LeaveRequestController::class, 'store'])->name('store');
        Route::get('/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('show');
        Route::patch('/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('approve');
        Route::patch('/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('reject');
        Route::patch('/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/poll', [NotificationController::class, 'poll'])->name('poll');
        Route::get('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    });
    // Deliberately NOT named "push-subscriptions" in the URL — ad blockers
    // (uBlock, AdGuard, Brave) block requests matching that pattern.
    // Change password (logged in) — verified with an email OTP.
    Route::get('change-password', [PasswordController::class, 'showChange'])->name('password.change');
    Route::post('change-password/send-otp', [PasswordController::class, 'sendChangeOtp'])
        ->middleware('throttle:5,10')->name('password.change.send');
    Route::post('change-password', [PasswordController::class, 'changeWithOtp'])
        ->middleware('throttle:10,10')->name('password.change.update');

    Route::post('browser-alerts/register', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::post('browser-alerts/test', [PushSubscriptionController::class, 'sendTest'])->name('push-subscriptions.test');
    Route::post('browser-alerts/diag', [PushSubscriptionController::class, 'diag'])->name('push-subscriptions.diag');
    Route::delete('browser-alerts/register', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::resource('leave-types', LeaveTypeController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::resource('holidays', HolidayController::class)->except(['show']);
    Route::get('holidays-export', [HolidayController::class, 'export'])->name('holidays.export');
    Route::resource('tasks', TaskController::class)->except(['edit', 'update']);
    Route::patch('tasks/{task}/complete', [TaskController::class, 'markComplete'])->name('tasks.complete');

    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::get('/list-poll', [ChatController::class, 'listPoll'])->name('list-poll');

        Route::get('/group/create', [ChatController::class, 'createGroup'])->name('group.create');
        Route::post('/group', [ChatController::class, 'storeGroup'])->name('group.store');

        Route::post('/start/{user}', [ChatController::class, 'startPrivate'])->name('start');

        Route::get('/{conversation}', [ChatController::class, 'show'])->name('show');
        Route::post('/{conversation}/messages', [ChatController::class, 'storeMessage'])->name('messages.store');
        Route::get('/{conversation}/messages/poll', [ChatController::class, 'pollMessages'])->name('messages.poll');
    });

    Route::get('social-dashboard', [SocialDashboardController::class, 'index'])->name('social-dashboard.index');
    Route::get('social-analytics', [SocialAnalyticsController::class, 'index'])->name('social-analytics.index');
    Route::prefix('social-accounts')->name('social-accounts.')->group(function () {

        // ---- Instagram ----
        Route::prefix('instagram')->name('instagram.')->group(function () {
            Route::get('/', [SocialAccountController::class, 'indexInstagram'])->name('index');
            Route::get('/create', [SocialAccountController::class, 'create'])->name('create');
            Route::post('/', [SocialAccountController::class, 'store'])->name('store');
        });

        // ---- YouTube ----
        Route::prefix('youtube')->name('youtube.')->group(function () {
            Route::get('/', [SocialAccountController::class, 'indexYoutube'])->name('index');
            Route::get('/create', [SocialAccountController::class, 'createYoutube'])->name('create');
            Route::post('/', [SocialAccountController::class, 'storeYoutube'])->name('store');
        });

        // ---- LinkedIn (OAuth) ----
        Route::prefix('linkedin')->name('linkedin.')->group(function () {
            Route::get('/', [SocialAccountController::class, 'indexLinkedIn'])->name('index');
            Route::get('/connect', [SocialAccountController::class, 'connectLinkedIn'])->name('connect');
            Route::get('/callback', [SocialAccountController::class, 'linkedInCallback'])->name('callback');
            Route::post('/', [SocialAccountController::class, 'storeLinkedIn'])->name('store');
        });

        // ---- Shared actions — work on any account by ID, regardless of platform ----
        Route::post('/{socialAccount}/sync', [SocialAccountController::class, 'sync'])->name('sync');
        Route::post('/{socialAccount}/refresh-token', [SocialAccountController::class, 'refreshToken'])->name('refresh-token');
        Route::patch('/{socialAccount}/toggle', [SocialAccountController::class, 'toggleActive'])->name('toggle');
        Route::delete('/{socialAccount}', [SocialAccountController::class, 'destroy'])->name('destroy');
    });

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
    Route::resource('expenses', ExpenseController::class)->except(['show', 'create', 'edit']);
    Route::get('expenses-analytics', [ExpenseAnalyticsController::class, 'index'])->name('expenses.analytics');

    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');

    // ---- Student Management (reads the BrowseJobs LMS database, read-only) ----
    Route::get('live-batches', [LiveBatchController::class, 'index'])->name('live-batches.index');
    Route::get('live-batches/{batch}', [LiveBatchController::class, 'show'])->name('live-batches.show');
    Route::get('live-batches/{batch}/students/{user}', [LiveBatchController::class, 'student'])->name('live-batches.student');
    Route::post('live-batches', [LiveBatchController::class, 'storeBatch'])->name('live-batches.store');
    Route::patch('live-batches/{batch}', [LiveBatchController::class, 'updateBatch'])->name('live-batches.update');
    Route::patch('live-batches/course-fee/{course}', [LiveBatchController::class, 'updateCourseFee'])->name('live-batches.course-fee');
    Route::post('live-batches/{batch}/announce', [LiveBatchController::class, 'announce'])->name('live-batches.announce');
    Route::post('live-batches/{batch}/students', [LiveBatchController::class, 'addStudent'])->name('live-batches.students.store');
    Route::patch('live-batches/{batch}/students/{user}/status', [LiveBatchController::class, 'memberStatus'])->name('live-batches.students.status');
    Route::patch('live-batches/{batch}/students/{user}/profile', [LiveBatchController::class, 'updateStudentProfile'])->name('live-batches.students.profile');
    Route::delete('live-batches/{batch}/students/{user}', [LiveBatchController::class, 'removeStudent'])->name('live-batches.students.destroy');
    Route::post('live-batches/{batch}/classes', [LiveBatchController::class, 'scheduleClass'])->name('live-batches.classes.store');
    Route::post('live-batches/{batch}/class-series', [LiveBatchController::class, 'scheduleSeries'])->name('live-batches.classes.series');
    Route::patch('live-batches/{batch}/classes/{session}/schedule', [LiveBatchController::class, 'rescheduleClass'])->name('live-batches.classes.reschedule');
    Route::delete('live-batches/{batch}/classes/{session}', [LiveBatchController::class, 'cancelClass'])->name('live-batches.classes.cancel');
    Route::post('live-batches/{batch}/classes/{session}/recording', [LiveBatchController::class, 'setRecording'])->name('live-batches.classes.recording');
    Route::get('masterclasses', [MasterclassController::class, 'index'])->name('masterclasses.index');
    Route::post('masterclasses', [MasterclassController::class, 'store'])->name('masterclasses.store');
    Route::post('masterclasses/recording', [MasterclassController::class, 'setVideo'])->name('masterclasses.video');
    Route::patch('masterclasses/{batch}/schedule', [MasterclassController::class, 'reschedule'])->name('masterclasses.reschedule');
    Route::delete('masterclasses/{batch}', [MasterclassController::class, 'cancel'])->name('masterclasses.cancel');
    Route::get('class-attendance', [ClassAttendanceController::class, 'index'])->name('class-attendance.index');
    Route::get('class-attendance/{session}', [ClassAttendanceController::class, 'show'])->name('class-attendance.show');
    Route::post('class-attendance/{session}/mark', [ClassAttendanceController::class, 'mark'])->name('class-attendance.mark');
    Route::get('mock-test-results', [MockTestResultController::class, 'index'])->name('mock-test-results.index');
    Route::patch('mock-test-results/pricing/{product}', [MockTestResultController::class, 'updatePricing'])->name('mock-test-results.pricing');
    Route::get('fee-collections', [FeeCollectionController::class, 'index'])->name('fee-collections.index');
    Route::post('fee-collections/{instalment}/send-link', [FeeCollectionController::class, 'sendLink'])->name('fee-collections.send-link');
    Route::post('fee-collections/sync', [FeeCollectionController::class, 'syncPayments'])->name('fee-collections.sync');
    Route::get('grading', [GradingController::class, 'index'])->name('grading.index');
    Route::post('grading/assignments', [GradingController::class, 'storeAssignment'])->name('grading.assignments.store');
    Route::post('grading/submissions/{submission}/grade', [GradingController::class, 'gradeSubmission'])->name('grading.grade');
    Route::post('grading/grades/{grade}/release', [GradingController::class, 'releaseGrade'])->name('grading.release');
    Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
    Route::get('support-tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
    Route::post('support-tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support-tickets.reply');
    Route::post('support-tickets/{ticket}/status', [SupportTicketController::class, 'status'])->name('support-tickets.status');
    Route::get('whitelabel', [WhitelabelController::class, 'index'])->name('whitelabel.index');
    Route::patch('whitelabel/{tenant}', [WhitelabelController::class, 'update'])->name('whitelabel.update');
    Route::get('leads-dashboard', [LeadsDashboardController::class, 'index'])->name('leads-dashboard.index');
    Route::get('revenue-summary-dashboard', [RevenueDashboardController::class, 'index'])->name('revenue-dashboard.index');
    Route::get('website-content', [WebsiteContentController::class, 'index'])->name('website-content.index');
    Route::post('website-content/section', [WebsiteContentController::class, 'updateSection'])->name('website-content.section');
    Route::post('website-content/reset', [WebsiteContentController::class, 'resetSection'])->name('website-content.reset');
    Route::get('website-pages', [SitePagesController::class, 'index'])->name('site-pages.index');
    Route::get('website-pages/edit', [SitePagesController::class, 'edit'])->name('site-pages.edit');
    Route::post('website-pages', [SitePagesController::class, 'update'])->name('site-pages.update');
    Route::post('website-pages/course', [SitePagesController::class, 'updateCourse'])->name('site-pages.update-course');
    Route::get('website-seo', [SiteSeoController::class, 'index'])->name('site-seo.index');
    Route::get('website-seo/edit', [SiteSeoController::class, 'edit'])->name('site-seo.edit');
    Route::post('website-seo', [SiteSeoController::class, 'update'])->name('site-seo.update');
    // Student Pulse curation — the three sections of the students' Pulse page.
    Route::get('student-pulse', [StudentPulseController::class, 'index'])->name('student-pulse.index');
    Route::post('student-pulse/items', [StudentPulseController::class, 'storeItem'])->name('student-pulse.items.store');
    Route::delete('student-pulse/items/{item}', [StudentPulseController::class, 'destroyItem'])->name('student-pulse.items.destroy')->whereNumber('item');
    Route::post('student-pulse/generate', [StudentPulseController::class, 'generateDigest'])->name('student-pulse.generate');
    Route::post('student-pulse/content', [StudentPulseController::class, 'storeContent'])->name('student-pulse.content.store');
    Route::delete('student-pulse/content/{item}', [StudentPulseController::class, 'destroyContent'])->name('student-pulse.content.destroy')->whereNumber('item');
    Route::post('student-pulse/celebrations', [StudentPulseController::class, 'storeCelebration'])->name('student-pulse.celebrations.store');
    Route::post('student-pulse/celebrations/{celebration}/publish', [StudentPulseController::class, 'publishCelebration'])->name('student-pulse.celebrations.publish')->whereNumber('celebration');
    Route::post('student-pulse/celebrations/{celebration}/unpublish', [StudentPulseController::class, 'unpublishCelebration'])->name('student-pulse.celebrations.unpublish')->whereNumber('celebration');

    Route::get('mentors', [MentorManagementController::class, 'index'])->name('mentors.index');
    Route::post('mentors', [MentorManagementController::class, 'store'])->name('mentors.store');
    Route::post('mentors/{mentor}/slots', [MentorManagementController::class, 'setSlots'])->name('mentors.slots');
    Route::get('batch-funnel', [BatchFunnelController::class, 'index'])->name('batch-funnel.index');
    Route::post('batch-funnel/run', [BatchFunnelController::class, 'run'])->name('batch-funnel.run');
    Route::patch('batch-funnel/leads/{lmsLead}/course', [BatchFunnelController::class, 'assignCourse'])->name('batch-funnel.assign-course');

    // ---- Notes ----
    Route::get('notes', [NotesController::class, 'index'])->name('notes.index');
    Route::post('notes', [NotesController::class, 'store'])->name('notes.store');
    Route::patch('notes/{note}', [NotesController::class, 'update'])->name('notes.update');
    Route::patch('notes/{note}/pin', [NotesController::class, 'togglePin'])->name('notes.toggle-pin');
    Route::delete('notes/{note}', [NotesController::class, 'destroy'])->name('notes.destroy');

    // ---- To Do ----
    Route::get('todos', [TodoController::class, 'index'])->name('todos.index');
    Route::post('todos', [TodoController::class, 'store'])->name('todos.store');
    Route::patch('todos/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::patch('todos/{todo}/complete', [TodoController::class, 'toggleComplete'])->name('todos.complete');
    Route::delete('todos/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');

    // ---- Email (SMTP send + IMAP inbox) ----
    Route::prefix('email')->name('emails.')->group(function () {
        Route::get('/', [EmailController::class, 'inbox'])->name('inbox');
        Route::get('/inbox/{uid}', [EmailController::class, 'showInbox'])->name('inbox.show')->whereNumber('uid');
        Route::get('/sent', [EmailController::class, 'sent'])->name('sent');
        Route::get('/sent/{email}', [EmailController::class, 'showSent'])->name('sent.show');
        Route::get('/compose', [EmailController::class, 'compose'])->name('compose');
        Route::post('/send', [EmailController::class, 'send'])->name('send');
        Route::get('/attachment/{attachment}', [EmailController::class, 'downloadAttachment'])->name('attachment');
        Route::delete('/{email}', [EmailController::class, 'destroy'])->name('destroy');
    });

    // ---- File Manager (local + Google Drive) ----
    Route::prefix('file-manager')->name('file-manager.')->group(function () {
        Route::get('/', [FileManagerController::class, 'index'])->name('index');
        Route::get('/google/connect', [FileManagerController::class, 'connectDrive'])->name('google.connect');
        Route::get('/google/callback', [FileManagerController::class, 'driveCallback'])->name('google.callback');
        Route::post('/google/disconnect', [FileManagerController::class, 'disconnectDrive'])->name('google.disconnect');
        Route::post('/folders', [FileManagerController::class, 'storeFolder'])->name('folders.store');
        Route::post('/files', [FileManagerController::class, 'uploadFile'])->name('files.upload');
        Route::get('/files/{file}/download', [FileManagerController::class, 'download'])->name('files.download');
        Route::delete('/folders/{folder}', [FileManagerController::class, 'destroyFolder'])->name('folders.destroy');
        Route::delete('/files/{file}', [FileManagerController::class, 'destroyFile'])->name('files.destroy');
        Route::get('/{folder}', [FileManagerController::class, 'index'])->name('browse')->whereNumber('folder');
    });

    // ---- Leads (CRM) ----
    Route::middleware('permission:view_leads')->group(function () {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/poll', [LeadController::class, 'poll'])->name('leads.poll');
        Route::get('leads/performance', [LeadController::class, 'performance'])->name('leads.performance');
        Route::get('meeting-reports', [MeetingReportController::class, 'index'])->name('meeting-reports.index');
        Route::get('meeting-reports/{meetingReport}', [MeetingReportController::class, 'show'])->name('meeting-reports.show')->whereNumber('meetingReport');
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show')->whereNumber('lead');
        Route::post('leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign')->whereNumber('lead');
        Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status')->whereNumber('lead');
        Route::patch('leads/{lead}/course', [LeadController::class, 'updateCourse'])->name('leads.course')->whereNumber('lead');
        Route::post('leads/{lead}/allocate-batch', [LeadController::class, 'allocateToBatch'])->name('leads.allocate-batch')->whereNumber('lead');
        Route::post('leads/{lead}/ai-call', [LeadController::class, 'triggerAiCall'])->name('leads.ai-call')->whereNumber('lead');
        Route::post('leads/{lead}/ai-analysis', [LeadController::class, 'analyzeWithAi'])->name('leads.ai-analysis')->whereNumber('lead');
        Route::post('leads/{lead}/manual-call', [LeadController::class, 'storeManualCall'])->name('leads.manual-call')->whereNumber('lead');
        Route::get('lead-calls/{call}/audio', [LeadController::class, 'downloadAudio'])->name('leads.call-audio')->whereNumber('call');
    });

    // ---- Office Finance (payroll + expenses + incentives) ----
    Route::middleware('permission:view_office_finance')->group(function () {
        Route::get('office-finance', [FinanceController::class, 'dashboard'])->name('finance.dashboard');
        Route::get('office-finance/payroll', [FinanceController::class, 'payroll'])->name('finance.payroll');
        Route::patch('office-finance/payroll/{user}', [FinanceController::class, 'updatePayroll'])->name('finance.payroll.update')->whereNumber('user');

        Route::get('incentives', [IncentiveController::class, 'index'])->name('incentives.index');
        Route::post('incentives', [IncentiveController::class, 'store'])->name('incentives.store');
        Route::patch('incentives/{incentive}/status', [IncentiveController::class, 'updateStatus'])->name('incentives.status')->whereNumber('incentive');
        Route::patch('incentives/{incentive}', [IncentiveController::class, 'update'])->name('incentives.update')->whereNumber('incentive');
        Route::delete('incentives/{incentive}', [IncentiveController::class, 'destroy'])->name('incentives.destroy')->whereNumber('incentive');
    });

    // ---- Login Reminder Report (Feature 1) — SUPER_ADMIN, HEAD_OF_OPERATIONS ----
    Route::middleware('permission:view_login_reminders')->group(function () {
        Route::get('login-reminders', [LoginReminderController::class, 'index'])->name('login-reminders.index');
        Route::post('login-reminders/run', [LoginReminderController::class, 'runReminders'])->name('login-reminders.run');
        Route::post('login-reminders/escalate', [LoginReminderController::class, 'runEscalation'])->name('login-reminders.escalate');
    });

    // ---- Milestones (Feature 2) ----
    Route::middleware('permission:view_milestones')->group(function () {
        Route::get('milestones', [MilestoneController::class, 'index'])->name('milestones.index');
        Route::post('milestones', [MilestoneController::class, 'store'])->name('milestones.store');
        Route::patch('milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');
    });

    // ---- Campaigns (Feature 3) ----
    Route::middleware('permission:view_campaign')->group(function () {
        Route::get('campaign', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('campaign/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('campaign', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('campaign/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show')->whereNumber('campaign');
        Route::post('campaign/{campaign}/send', [CampaignController::class, 'send'])->name('campaigns.send')->whereNumber('campaign');
        Route::delete('campaign/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy')->whereNumber('campaign');
    });
    Route::get('email-campaign', [CampaignController::class, 'create'])->defaults('channel', 'email')->middleware('permission:view_email_campaign')->name('campaigns.email');
    Route::get('whatsapp-campaign', [CampaignController::class, 'create'])->defaults('channel', 'whatsapp')->middleware('permission:view_whatsapp_campaign')->name('campaigns.whatsapp');
    Route::get('sms-campaign', [CampaignController::class, 'maintenance'])->defaults('channel', 'SMS')->middleware('permission:view_sms_campaign')->name('campaigns.sms');
    Route::get('social-campaign', [CampaignController::class, 'maintenance'])->defaults('channel', 'Social')->middleware('permission:view_social_campaign')->name('campaigns.social');

    // ---- Social Alerts (Feature 4) — SUPER_ADMIN, HEAD_OF_OPERATIONS ----
    Route::middleware('permission:view_social_alerts')->group(function () {
        Route::get('social-alerts', [SocialAlertController::class, 'index'])->name('social-alerts.index');
        Route::patch('social-alerts', [SocialAlertController::class, 'update'])->name('social-alerts.update');
    });

    // Old Main-Menu "To Do" link — a real page now lives at /todos.
    Route::get('todo', fn () => redirect()->route('todos.index'))->name('todo.legacy');

    // ---- Placeholder pages: menu items whose feature is not built yet ----
    // These previously 404'd. They now render a friendly "Under maintenance" page.
    $underMaintenance = [
        'pages' => 'Pages',
        'blogs' => 'All Blogs',
        'blog-categories' => 'Blog Categories',
        'blog-comments' => 'Blog Comments',
        'blog-tags' => 'Blog Tags',
        'countries' => 'Countries',
        'states' => 'States',
        'cities' => 'Cities',
        'testimonials' => 'Testimonials',
        'faq' => 'FAQ',

        'growth-dashboard' => 'Growth Dashboard',
        'email-engagement' => 'Email Engagement',
        'company-reports' => 'Company Report',
        'revenue-report' => 'Revenue Report',
        'attendance-summary-report' => 'Attendance Summary',
        'leave-balance-summary-report' => 'Leave Balance Summary',
    ];

    foreach ($underMaintenance as $slug => $title) {
        Route::get($slug, [MaintenanceController::class, 'show'])
            ->defaults('title', $title)
            ->name('maintenance.'.$slug);
    }

});
