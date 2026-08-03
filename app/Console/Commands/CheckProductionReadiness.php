<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-flight check for going live. Catches the failures that are invisible
 * until a real person is affected — notifications that went out with localhost
 * links, debug mode left on, a queue nobody is draining.
 *
 * Run it on the server after deploying: php artisan app:check-live
 */
class CheckProductionReadiness extends Command
{
    protected $signature = 'app:check-live {--strict : Exit non-zero on warnings too}';

    protected $description = 'Verify this install is safe and complete to serve real users.';

    /** @var array<int, array{level: string, area: string, detail: string, fix: string}> */
    private array $findings = [];

    public function handle(): int
    {
        $this->checkUrls();
        $this->checkAppSafety();
        $this->checkOutboundChannels();
        $this->checkQueueAndSchedule();
        $this->checkLmsLink();

        return $this->report();
    }

    /**
     * The one that bit us: class reminders went out over WhatsApp containing
     * http://localhost:8000/magic/... links, which are dead for every student.
     * Anything that ends up inside a message must be a public URL.
     */
    private function checkUrls(): void
    {
        $appUrl = (string) config('app.url');
        $siteUrl = (string) config('services.lms.site_url');
        $watchUrl = (string) config('services.lms.masterclass_watch_url');

        foreach ([
            'APP_URL' => $appUrl,
            'LMS_SITE_URL' => $siteUrl,
            'LMS_MASTERCLASS_WATCH_URL' => $watchUrl,
        ] as $key => $value) {
            if ($value === '') {
                $this->blocker('URLs', "{$key} is empty.", "Set {$key} in .env.");

                continue;
            }

            if (preg_match('~^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0)~i', $value)) {
                $this->blocker('URLs', "{$key} points at {$value}.",
                    "Set {$key} to the public address. Links in emails, WhatsApp messages and push notifications are built from it, so students receive dead links until you do.");

                continue;
            }

            if ($this->isProduction() && ! str_starts_with($value, 'https://')) {
                $this->caution('URLs', "{$key} is not HTTPS ({$value}).", 'Use https:// so login links and magic links are not sent in the clear.');
            }
        }
    }

    private function checkAppSafety(): void
    {
        if (config('app.debug')) {
            $this->blocker('Safety', 'APP_DEBUG is on.',
                'Set APP_DEBUG=false. With it on, any error page shows your database credentials and API keys to whoever triggered it.');
        }

        if ($this->isProduction() && config('app.env') !== 'production') {
            $this->caution('Safety', 'APP_ENV is "'.config('app.env').'".', 'Set APP_ENV=production.');
        }

        if (blank(config('app.key'))) {
            $this->blocker('Safety', 'APP_KEY is not set.', 'Run php artisan key:generate — sessions and encrypted values depend on it.');
        }
    }

    /** A channel that is half-configured fails silently, which is the worst kind. */
    private function checkOutboundChannels(): void
    {
        if (blank(config('services.whatsapp.access_token')) || blank(config('services.whatsapp.phone_number_id'))) {
            $this->caution('WhatsApp', 'Not fully configured.', 'Set WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID, or WhatsApp notifications quietly never send.');
        }

        if (config('mail.default') === 'log') {
            $this->caution('Email', 'MAIL_MAILER is "log" — nothing is actually emailed.', 'Set real SMTP credentials.');
        }

        if (config('services.caller_digital.auto_call') && blank(config('services.caller_digital.api_key'))) {
            $this->blocker('AI calling', 'Auto-call is ON but no API key is set.', 'Set CALLER_DIGITAL_API_KEY, or turn CALLER_DIGITAL_AUTO_CALL off.');
        }

        if (blank(config('webpush.vapid.public_key'))) {
            $this->caution('Web push', 'VAPID keys missing.', 'Run php artisan webpush:vapid — browser notifications will not work without them.');
        }
    }

    /**
     * Everything time-based in this system — lead auto-calling, reminders,
     * digests, the LMS heartbeat — only runs if the scheduler is running.
     */
    private function checkQueueAndSchedule(): void
    {
        try {
            $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(15)->timestamp)->count();
            if ($stuck > 0) {
                $this->blocker('Queue', "{$stuck} job(s) queued for over 15 minutes.",
                    'No worker is draining the queue, so emails, WhatsApp messages and AI calls are piling up unsent. Check the scheduler / supervisor.');
            }

            $failed = DB::table('failed_jobs')->count();
            if ($failed > 0) {
                $this->caution('Queue', "{$failed} failed job(s).", 'Inspect with php artisan queue:failed.');
            }
        } catch (\Throwable $e) {
            $this->blocker('Queue', 'Cannot read the jobs table.', $e->getMessage());
        }

        $this->line('  <fg=gray>Reminder: the scheduler must run every minute (cron or Task Scheduler) or nothing automatic fires.</>');
    }

    private function checkLmsLink(): void
    {
        try {
            DB::connection('lms')->select('select 1');
        } catch (\Throwable $e) {
            $this->blocker('LMS', 'The LMS database is unreachable.',
                'Student, batch, fee and grading pages will all show an error until this connects. Check LMS_DB_* in .env.');

            return;
        }

        if (! is_dir((string) config('services.lms.path'))) {
            $this->caution('LMS', 'LMS_APP_PATH does not exist on this server.',
                'Actions the CRM delegates to the LMS (masterclass planning, digests) will fail. Set LMS_APP_PATH.');
        }
    }

    private function isProduction(): bool
    {
        return app()->environment('production') || ! config('app.debug');
    }

    private function blocker(string $area, string $detail, string $fix): void
    {
        $this->findings[] = ['level' => 'fail', 'area' => $area, 'detail' => $detail, 'fix' => $fix];
    }

    private function caution(string $area, string $detail, string $fix): void
    {
        $this->findings[] = ['level' => 'warn', 'area' => $area, 'detail' => $detail, 'fix' => $fix];
    }

    private function report(): int
    {
        $fails = array_filter($this->findings, fn ($f) => $f['level'] === 'fail');
        $warns = array_filter($this->findings, fn ($f) => $f['level'] === 'warn');

        $this->newLine();

        foreach ($this->findings as $f) {
            $tag = $f['level'] === 'fail' ? '<fg=white;bg=red> BLOCKER </>' : '<fg=black;bg=yellow> WARN </>';
            $this->line("{$tag} <options=bold>{$f['area']}</> — {$f['detail']}");
            $this->line("          <fg=gray>{$f['fix']}</>");
        }

        $this->newLine();

        if ($fails !== []) {
            $this->error(count($fails).' blocker(s), '.count($warns).' warning(s). Do not go live until the blockers are fixed.');

            return self::FAILURE;
        }

        if ($warns !== []) {
            $this->warn(count($warns).' warning(s), no blockers.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('All checks passed — safe to serve real users.');

        return self::SUCCESS;
    }
}
