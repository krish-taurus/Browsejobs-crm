<?php

namespace App\Console\Commands;

use App\Services\LmsCommandRunner;
use Illuminate\Console\Command;

/**
 * Keeps the LMS alive from the CRM's scheduler. The LMS's own automation
 * (funnel:advance, class reminders, Zoom meeting creation, credential and
 * nudge messages) lives in ITS scheduler and database queue — but nothing on
 * this Windows box runs them. The CRM already has a Task Scheduler entry
 * firing `schedule:run` every minute, so this command piggybacks on it:
 * each beat runs the LMS scheduler once, then drains the LMS queue.
 *
 * If the LMS folder is missing (other machine, path change) both calls fail
 * soft inside LmsCommandRunner — the CRM's own schedule is never affected.
 */
class LmsHeartbeat extends Command
{
    protected $signature = 'lms:heartbeat';

    protected $description = 'Run the LMS scheduler and drain its queue (Zoom, reminders, funnel automation)';

    public function handle(LmsCommandRunner $lms): int
    {
        $scheduleOk = $this->beat($lms, ['schedule:run'], 120);

        // --max-time keeps the worker under our timeout; --stop-when-empty
        // prevents a stuck long-running process, same pattern as the CRM's own
        // queue drain in routes/console.php.
        $queueOk = $this->beat($lms, ['queue:work', '--stop-when-empty', '--tries=3', '--max-time=45'], 70);

        return $scheduleOk && $queueOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function beat(LmsCommandRunner $lms, array $arguments, int $timeoutSeconds): bool
    {
        try {
            $result = $lms->run($arguments, $timeoutSeconds, retryOnce: false);
        } catch (\Throwable $e) {
            // A hung child (e.g. a stalled Zoom HTTP call) must not take the
            // CRM scheduler down with it — log and let the next beat retry.
            report($e);
            $result = ['ok' => false, 'output' => $e->getMessage()];
        }

        $this->line($arguments[0].' — '.($result['ok'] ? 'ok' : 'FAILED: '.mb_substr($result['output'], 0, 200)));

        return $result['ok'];
    }
}
