<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:flag-overdue')->dailyAt('09:00');

// ============================================================
// IMPORTANT: Laravel's scheduler only runs when something calls
// `php artisan schedule:run` every minute. On a real Linux server
// you'd add this to crontab:
//
//   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
//
// On Windows/XAMPP (your setup), there's no cron — use Windows Task
// Scheduler instead:
//   1. Open "Task Scheduler" (search in Start menu)
//   2. Create a new Basic Task, trigger: "Daily", repeat every 1 minute
//   3. Action: Start a program
//      Program: C:\xampp\php\php.exe
//      Arguments: artisan schedule:run
//      Start in: C:\xampp\htdocs\BrowseJobsBackendLaravel
//
// Without this running every minute, tasks:flag-overdue will simply
// never fire on its own, no matter how correct the code is.
// ============================================================

Schedule::command('social:fetch-stats')->hourly();

// Hourly (not just once a day) so "posts today" and "reach" stay reasonably
// current throughout the day rather than only updating at midnight.
// Remember: Windows/XAMPP needs Task Scheduler running
// `php artisan schedule:run` every minute — same setup as your task reminders.

// Daily standup: WhatsApp + web-push the meeting link 5 minutes before the
// 9:30 call. Weekdays only — add ->saturdays() etc. if the team stands up then.
Schedule::command('standup:remind')->weekdays()->dailyAt('09:25');

// Analyze new Google Meet transcripts (attendance + AI report) shortly after
// the standup and periodically through the day for other recorded meetings.
Schedule::command('meetings:analyze')->everyThirtyMinutes();

// ---- Login reminders (Feature 1) ----
// Mid-morning: nudge anyone who has not logged in yet (skips Sundays + holidays).
Schedule::command('logins:remind')->weekdays()->dailyAt('11:00');
// Early evening: report anyone STILL not logged in to SUPER_ADMIN + HEAD_OF_OPERATIONS.
Schedule::command('logins:escalate')->weekdays()->dailyAt('17:30');

// ---- Social posts + engagement (Feature 4) ----
// Every 4 hours: fetch latest posts/videos + likes/comments/shares/views, then check for
// posting inactivity. If an account has no new post in the last 24h, email + WhatsApp the
// admins (SUPER_ADMIN, HEAD_OF_OPERATIONS, SOCIAL_MEDIA_MANAGER). Reminders are throttled to
// once per 24h per account, so the 4-hourly checks don't spam recipients.
Schedule::command('social:fetch-posts')->everyFourHours();
Schedule::command('social:check-inactivity')->everyFourHours();

// AI call result sync — fallback when the Caller.Digital webhook can't reach this server.
Schedule::command('calls:sync')->everyTenMinutes();

// Safety net: any lead still in "New" with no AI call (missed job, downtime,
// imports) gets its auto-call within 10 minutes.
Schedule::command('leads:auto-call')->everyTenMinutes();

// Pull leads captured on the LMS site (forms, masterclass bookings, syllabus
// downloads) into the CRM. They arrive in "New" status, so the normal
// new-lead pipeline — notifications + AI auto-call — fires for them too.
Schedule::command('leads:sync-lms')->everyFiveMinutes();

// Next-day "did you watch the masterclass?" nudge (WhatsApp + AI call) for
// interested leads who got the watch link — once per lead, stamped.
Schedule::command('leads:masterclass-followup')->hourly();

// Drain queued jobs (lead emails/WhatsApp/auto-call, campaigns) every minute.
// --stop-when-empty + --max-time keep it from becoming a stuck long-running
// process; withoutOverlapping stops two workers running at once.
// withoutOverlapping(2): if a worker is ever killed mid-run, its stale lock
// self-heals in 2 minutes (the default is 24 HOURS of silently skipped runs).
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(2);

// Keep the LMS's automation alive too: its scheduler (funnel:advance — the
// Sat/Sun masterclass builder, bootcamp rollover, day-5 payment nudges) and
// its database queue (Zoom meeting creation, 12h/2h/5min class reminders,
// credential/welcome messages) only run when something invokes them. The CRM
// is that something — one heartbeat per minute, in the background so the
// CRM's own queue drain above is never delayed by a slow LMS beat.
Schedule::command('lms:heartbeat')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
