<?php

namespace App\Services;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs artisan commands inside the BrowseJobs LMS application on this machine.
 * This is how the CRM drives LMS-side behaviour (funnel runs, masterclass
 * planning/rescheduling/cancelling) without duplicating its business logic —
 * Zoom, notifications and reminder ladders all stay in the LMS.
 *
 * The child process gets a CLEAN environment: every variable inherited from
 * the CRM's process is dropped (dotenv never overrides real env vars, so a
 * leaked DB_DATABASE would silently point the LMS at the CRM database).
 */
class LmsCommandRunner
{
    /**
     * @param  list<string>  $arguments  artisan arguments, e.g. ['masterclass:plan', 'data-eng', '2026-08-01', '11:00']
     * @return array{ok: bool, output: string}
     */
    public function run(array $arguments, int $timeoutSeconds = 120, bool $retryOnce = true): array
    {
        $lmsPath = config('services.lms.path');

        if (! is_dir($lmsPath)) {
            return ['ok' => false, 'output' => "LMS path not found: {$lmsPath}. Set LMS_APP_PATH in .env."];
        }

        $php = config('services.lms.php_binary');
        if (! is_file($php)) {
            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        }

        $command = array_merge([$php, 'artisan'], $arguments, ['--no-interaction']);
        $env = $this->isolatedEnv();

        $attempts = 0;
        do {
            $attempts++;
            $process = new Process($command, $lmsPath, $env, null, $timeoutSeconds);
            $process->run();

            if ($process->isSuccessful()) {
                return ['ok' => true, 'output' => trim($process->getOutput())];
            }

            if ($retryOnce && $attempts < 2) {
                sleep(2); // transient MySQL "2002 Unknown error" happens under socket churn on Windows
            }
        } while ($retryOnce && $attempts < 2);

        $detail = trim($process->getErrorOutput()."\n".$process->getOutput());
        report(new \RuntimeException('LMS command failed ('.implode(' ', $arguments).'): '.$detail));

        return ['ok' => false, 'output' => $detail ?: 'no output — check LMS_PHP_BINARY in .env'];
    }

    /**
     * @return array<string, string|false>
     */
    private function isolatedEnv(): array
    {
        $keep = ['SYSTEMROOT', 'WINDIR', 'PATH', 'PATHEXT', 'COMSPEC', 'TEMP', 'TMP',
            'USERPROFILE', 'HOMEDRIVE', 'HOMEPATH', 'PROCESSOR_ARCHITECTURE', 'NUMBER_OF_PROCESSORS'];

        $env = [];
        $inherited = array_merge(
            array_keys(getenv()),
            array_keys($_ENV),
            array_keys(array_filter($_SERVER, 'is_string')),
        );

        foreach (array_unique($inherited) as $key) {
            if (! in_array(strtoupper((string) $key), $keep, true)) {
                $env[$key] = false;
            }
        }

        $env['SystemRoot'] = getenv('SystemRoot') ?: 'C:\\Windows';
        $env['windir'] = getenv('windir') ?: 'C:\\Windows';
        $env['TEMP'] = getenv('TEMP') ?: sys_get_temp_dir();
        $env['TMP'] = getenv('TMP') ?: sys_get_temp_dir();

        return $env;
    }
}
