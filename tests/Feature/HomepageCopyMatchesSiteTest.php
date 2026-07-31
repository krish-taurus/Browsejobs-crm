<?php

use App\Http\Controllers\Concerns\ListsHomepageCopy;

/**
 * The CRM prefills every homepage box with the wording the website ships with,
 * so those defaults MUST stay identical to HOMEPAGE_DEFAULTS in the site's
 * src/content/homepage.ts. If they drift, the founder edits text that isn't
 * actually on the page — and a "no change" save would silently overwrite it.
 */
const SITE_COPY_FILE = 'C:/xampp/htdocs/Browsejobs-lms-main/apps/web/src/content/homepage.ts';

function crmHomepageDefaults(): array
{
    return (new class
    {
        use ListsHomepageCopy;

        public function defaults(): array
        {
            return $this->homepageDefaults();
        }
    })->defaults();
}

/** @return array<string, string> */
function siteHomepageDefaults(): array
{
    $source = (string) file_get_contents(SITE_COPY_FILE);
    preg_match_all('/"([a-z0-9_]+\.[a-z0-9_]+)":\s*"((?:[^"\\\\]|\\\\.)*)"/i', $source, $matches, PREG_SET_ORDER);

    $defaults = [];
    foreach ($matches as $match) {
        $defaults[$match[1]] = stripcslashes($match[2]);
    }

    return $defaults;
}

it('exposes exactly the copy keys the website defines', function () {
    $crm = array_keys(crmHomepageDefaults());
    $site = array_keys(siteHomepageDefaults());

    sort($crm);
    sort($site);

    expect($crm)->toBe($site);
})->skip(fn () => ! file_exists(SITE_COPY_FILE), 'Website source not available');

it('prefills every field with the exact wording the website ships', function () {
    $site = siteHomepageDefaults();

    foreach (crmHomepageDefaults() as $key => $default) {
        expect($default)->toBe($site[$key] ?? null, "default for {$key} drifted from the website");
    }
})->skip(fn () => ! file_exists(SITE_COPY_FILE), 'Website source not available');

it('gives every field a non-empty label and a valid input type', function () {
    $catalogue = new class
    {
        use ListsHomepageCopy;

        public function sections(): array
        {
            return $this->homepageSections();
        }
    };

    foreach ($catalogue->sections() as $name => $section) {
        expect($section['fields'])->not->toBeEmpty("section {$name} has no fields");

        foreach ($section['fields'] as $key => $field) {
            expect($field)->toHaveCount(3, "field {$key} is missing its default")
                ->and(trim($field[0]))->not->toBe('', "field {$key} has no label")
                ->and($field[1])->toBeIn(['text', 'area'], "field {$key} has an unknown type");
        }
    }
});
