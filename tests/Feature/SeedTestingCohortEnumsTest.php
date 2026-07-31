<?php

use App\Console\Commands\SeedTestingCohort;

/**
 * The demo cohort writes straight into the LMS database, bypassing its models —
 * so nothing stops it storing a value the LMS enums reject. When that happens
 * the row looks fine until the LMS loads it and throws
 * "released is not a valid backing value for GradeStatus", which is exactly how
 * the Grading page's Release button broke.
 *
 * This reads the real enum files and fails the build instead.
 */
const LMS_ENUM_DIR = 'C:/xampp/htdocs/Browsejobs-lms-main/apps/api/app/Enums';

/** @return array<int, string> the backing values of one LMS enum */
function lmsEnumValues(string $enum): array
{
    $source = (string) file_get_contents(LMS_ENUM_DIR."/{$enum}.php");
    preg_match_all("/case\s+\w+\s*=\s*'([^']+)'/", $source, $m);

    return $m[1];
}

it('only seeds values the LMS enums actually accept', function () {
    $reflection = new ReflectionClass(SeedTestingCohort::class);
    $values = $reflection->getConstant('ENUM_VALUES');

    expect($values)->toBeArray()->not->toBeEmpty();

    $violations = [];

    foreach ($values as $key => $value) {
        // "GradeStatusApproved" and "GradeStatus" both check GradeStatus.php —
        // the suffix only distinguishes which case the seeder uses where.
        $enum = preg_replace('/(Approved|Paid|Single|Ended)$/', '', $key);

        if (! file_exists(LMS_ENUM_DIR."/{$enum}.php")) {
            $violations[] = "{$key}: no LMS enum file {$enum}.php";

            continue;
        }

        $cases = lmsEnumValues($enum);
        if (! in_array($value, $cases, true)) {
            $violations[] = "{$key}: '{$value}' is not a case of {$enum} (valid: ".implode('|', $cases).')';
        }
    }

    expect($violations)->toBe([]);
})->skip(fn () => ! is_dir(LMS_ENUM_DIR), 'LMS source not available');

it('rejects the exact value that broke the Release button', function () {
    // Guards the specific regression: GradeStatus has draft|approved only.
    expect(lmsEnumValues('GradeStatus'))
        ->toBe(['draft', 'approved'])
        ->not->toContain('released');
})->skip(fn () => ! is_dir(LMS_ENUM_DIR), 'LMS source not available');
