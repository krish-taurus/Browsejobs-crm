<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Guards the failure that already reached real students: class reminders sent
 * over WhatsApp containing http://localhost:8000/magic/... links. Anything that
 * ends up inside a message is built from these URLs, so a localhost value must
 * be a hard blocker, not a warning.
 */
it('blocks going live when a public URL still points at localhost', function () {
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.url' => 'http://localhost:8000',
        'services.lms.site_url' => 'https://browsejobs.ai',
        'services.lms.masterclass_watch_url' => 'https://browsejobs.ai/masterclass/watch',
    ]);

    $this->artisan('app:check-live')
        ->expectsOutputToContain('APP_URL points at http://localhost:8000')
        ->assertFailed();
});

it('blocks going live with debug mode on', function () {
    config([
        'app.debug' => true,
        'app.url' => 'https://crm.browsejobs.ai',
        'services.lms.site_url' => 'https://browsejobs.ai',
        'services.lms.masterclass_watch_url' => 'https://browsejobs.ai/masterclass/watch',
    ]);

    $this->artisan('app:check-live')
        ->expectsOutputToContain('APP_DEBUG is on')
        ->assertFailed();
});

it('blocks auto-calling that is switched on with no API key', function () {
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.url' => 'https://crm.browsejobs.ai',
        'services.lms.site_url' => 'https://browsejobs.ai',
        'services.lms.masterclass_watch_url' => 'https://browsejobs.ai/masterclass/watch',
        'services.caller_digital.auto_call' => true,
        'services.caller_digital.api_key' => '',
    ]);

    $this->artisan('app:check-live')
        ->expectsOutputToContain('Auto-call is ON but no API key is set')
        ->assertFailed();
});

it('passes once every public URL is a real https domain', function () {
    config([
        'app.debug' => false,
        'app.env' => 'production',
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'app.url' => 'https://crm.browsejobs.ai',
        'services.lms.site_url' => 'https://browsejobs.ai',
        'services.lms.masterclass_watch_url' => 'https://browsejobs.ai/masterclass/watch',
        'services.caller_digital.auto_call' => false,
        'services.whatsapp.access_token' => 'token',
        'services.whatsapp.phone_number_id' => '123',
        'mail.default' => 'smtp',
        'webpush.vapid.public_key' => 'key',
    ]);

    $this->artisan('app:check-live')->assertSuccessful();
})->skip(fn () => ! extension_loaded('pdo_mysql'), 'Needs the LMS connection check to run');

it('has no hardcoded localhost left in application code or views', function () {
    $roots = [app_path(), resource_path('views')];
    $offenders = [];

    // The readiness checker legitimately contains the pattern — it is what
    // detects it.
    $allowed = ['CheckProductionReadiness.php'];

    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['php'], true)) {
                continue;
            }
            if (in_array($file->getFilename(), $allowed, true)) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            // env() defaults are fine — they are overridden in production.
            $contents = preg_replace('/env\([^)]*\)/', '', $contents) ?? $contents;

            if (preg_match('~https?://(localhost|127\.0\.0\.1)~i', $contents)) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([]);
});
