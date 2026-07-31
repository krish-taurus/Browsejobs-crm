<?php

use App\Models\Lms\SiteContent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Website → Pages / SEO write to the LMS site_contents table, so these smoke
 * tests only run where that database is reachable (local dev).
 */
function siteDatabaseAvailable(): bool
{
    try {
        DB::connection('lms')->select('select 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function websiteTestUser(): User
{
    $role = Role::create(['role_name' => 'ADMIN', 'role_code' => 'ADMIN', 'is_active' => true]);

    foreach (['manage_website_content', 'manage_whitelabel'] as $slug) {
        $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    return User::create([
        'employee_id' => 'EMP-WEB-1',
        'first_name' => 'Web',
        'email' => 'website-smoke@test.local',
        'password' => 'password',
        'role_id' => $role->id,
    ]);
}

/** Restore a site_contents key to what it was before the test touched it. */
function restoreSiteContent(string $key, ?array $original): void
{
    $row = SiteContent::query()->where('key', $key)->first();
    if (! $row) {
        return;
    }
    if ($original === null) {
        $row->delete();

        return;
    }
    $row->data = $original;
    $row->save();
}

it('shows every homepage section with its saved copy', function () {
    $original = SiteContent::query()->where('key', 'homepage')->value('data');
    $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);

    SiteContent::query()->updateOrCreate(
        ['tenant_id' => $tenantId, 'key' => 'homepage'],
        ['data' => ['fees.kicker' => 'What it costs']],
    );

    try {
        $this->actingAs(websiteTestUser())
            ->get('/website-content')
            ->assertOk()
            ->assertSee('Homepage')
            ->assertSee('Closing call-to-action')   // last section is rendered
            ->assertSee('The Path (7 steps)')
            ->assertSee('What it costs', false)                  // saved override is prefilled
            ->assertSee('Free live masterclass', false)          // untouched field shows the LIVE wording
            ->assertSee('One fee. The whole machine.', false);
    } finally {
        restoreSiteContent('homepage', $original ? (array) $original : null);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('does not store a field left at the wording the site already ships', function () {
    $original = SiteContent::query()->where('key', 'homepage')->value('data');
    $originalHero = SiteContent::query()->where('key', 'hero')->value('data');

    try {
        $this->actingAs(websiteTestUser())
            ->post('/website-content/section', [
                'section' => 'Reviews',
                'copy' => [
                    // Submitted exactly as prefilled — the founder changed nothing here.
                    'reviews.kicker' => 'In their words',
                    'reviews.headline' => 'The people who did it, on the record.',
                    'reviews.body' => 'Only the real ones.',
                ],
            ])
            ->assertRedirect()->assertSessionHas('success');

        $saved = (array) SiteContent::query()->where('key', 'homepage')->value('data');

        expect($saved)->not->toHaveKey('reviews.kicker')
            ->and($saved)->not->toHaveKey('reviews.headline')
            ->and($saved['reviews.body'])->toBe('Only the real ones.');
    } finally {
        restoreSiteContent('homepage', $original ? (array) $original : null);
        restoreSiteContent('hero', $originalHero ? (array) $originalHero : null);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('saves one homepage section without touching the others', function () {
    $original = SiteContent::query()->where('key', 'homepage')->value('data');
    $originalHero = SiteContent::query()->where('key', 'hero')->value('data');
    $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);

    SiteContent::query()->updateOrCreate(
        ['tenant_id' => $tenantId, 'key' => 'homepage'],
        ['data' => ['final.cta' => 'Grab my seat']],
    );

    try {
        $this->actingAs(websiteTestUser())
            ->post('/website-content/section', [
                'section' => 'Reviews',
                'copy' => [
                    'reviews.kicker' => 'What students say',
                    'reviews.headline' => '',       // blank = keep the built-in line
                    'reviews.body' => 'Only real, verified reviews.',
                ],
            ])
            ->assertRedirect()->assertSessionHas('success');

        $saved = (array) SiteContent::query()->where('key', 'homepage')->value('data');

        expect($saved['reviews.kicker'])->toBe('What students say')
            ->and($saved)->not->toHaveKey('reviews.headline')   // blanks are not stored
            ->and($saved['final.cta'])->toBe('Grab my seat');   // other sections survive
    } finally {
        restoreSiteContent('homepage', $original ? (array) $original : null);
        restoreSiteContent('hero', $originalHero ? (array) $originalHero : null);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('keeps the legacy hero record in step when hero fields are saved', function () {
    $original = SiteContent::query()->where('key', 'homepage')->value('data');
    $originalHero = SiteContent::query()->where('key', 'hero')->value('data');

    try {
        $this->actingAs(websiteTestUser())
            ->post('/website-content/section', [
                'section' => 'Hero',
                'copy' => ['hero.cta_primary' => 'Book my free seat'],
            ])
            ->assertRedirect()->assertSessionHas('success');

        $homepage = (array) SiteContent::query()->where('key', 'homepage')->value('data');
        $hero = (array) SiteContent::query()->where('key', 'hero')->value('data');

        expect($homepage['hero.cta_primary'])->toBe('Book my free seat')
            ->and($hero['cta_primary'])->toBe('Book my free seat');
    } finally {
        restoreSiteContent('homepage', $original ? (array) $original : null);
        restoreSiteContent('hero', $originalHero ? (array) $originalHero : null);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('resets a homepage section back to the shipped copy', function () {
    $original = SiteContent::query()->where('key', 'homepage')->value('data');
    $originalHero = SiteContent::query()->where('key', 'hero')->value('data');
    $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);

    SiteContent::query()->updateOrCreate(
        ['tenant_id' => $tenantId, 'key' => 'homepage'],
        ['data' => ['reviews.kicker' => 'Changed', 'final.cta' => 'Keep me']],
    );

    try {
        $this->actingAs(websiteTestUser())
            ->post('/website-content/reset', ['section' => 'Reviews'])
            ->assertRedirect()->assertSessionHas('success');

        $saved = (array) SiteContent::query()->where('key', 'homepage')->value('data');

        expect($saved)->not->toHaveKey('reviews.kicker')
            ->and($saved['final.cta'])->toBe('Keep me');
    } finally {
        restoreSiteContent('homepage', $original ? (array) $original : null);
        restoreSiteContent('hero', $originalHero ? (array) $originalHero : null);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('rejects a section name that is not part of the homepage', function () {
    $this->actingAs(websiteTestUser())
        ->post('/website-content/section', ['section' => 'Nope', 'copy' => ['x' => 'y']])
        ->assertNotFound();
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('lists every public page under Website → Pages', function () {
    $this->actingAs(websiteTestUser())
        ->get('/website-pages')
        ->assertOk()
        ->assertSee('Website Pages')
        ->assertSee('/privacy-policy')   // rich-text editable
        ->assertSee('/salaries')         // design-managed, SEO only
        ->assertSee('Design-managed');
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('opens a page in the rich-text editor prefilled with the saved content', function () {
    $key = 'page:/terms';
    $original = SiteContent::query()->where('key', $key)->value('data');
    $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);

    SiteContent::query()->updateOrCreate(
        ['tenant_id' => $tenantId, 'key' => $key],
        ['data' => ['body' => '<h2>Existing clause</h2><p>Saved earlier.</p>']],
    );

    try {
        $this->actingAs(websiteTestUser())
            ->get('/website-pages/edit?path=/terms')
            ->assertOk()
            ->assertSee('summernote', false)
            ->assertSee('Existing clause', false);
    } finally {
        restoreSiteContent($key, $original);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('falls back to the live page text when nothing has been saved yet', function () {
    Http::fake([
        '*' => Http::response('<html><body><main><h2>Built-in heading</h2><p>Built-in paragraph.</p></main></body></html>'),
    ]);

    $key = 'page:/refund-policy';
    $original = SiteContent::query()->where('key', $key)->value('data');
    SiteContent::query()->where('key', $key)->delete();

    try {
        $this->actingAs(websiteTestUser())
            ->get('/website-pages/edit?path=/refund-policy')
            ->assertOk()
            ->assertSee('current live text', false)
            ->assertSee('Built-in heading', false);
    } finally {
        restoreSiteContent($key, $original);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('sanitizes scripts out of saved rich text', function () {
    $key = 'page:/refund-policy';
    $original = SiteContent::query()->where('key', $key)->value('data');

    try {
        $this->actingAs(websiteTestUser())
            ->post('/website-pages', [
                'path' => '/refund-policy',
                'body' => '<p onclick="steal()">Refunds</p><script>alert(1)</script><a href="javascript:bad()">x</a>',
            ])
            ->assertRedirect(route('site-pages.index'));

        $saved = (string) ((array) SiteContent::query()->where('key', $key)->value('data'))['body'];

        expect($saved)->toContain('Refunds')
            ->and($saved)->not->toContain('<script')
            ->and($saved)->not->toContain('onclick')
            ->and($saved)->not->toContain('javascript:');
    } finally {
        restoreSiteContent($key, $original);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('lists every public page under Website → SEO, set or not', function () {
    $this->actingAs(websiteTestUser())
        ->get('/website-seo')
        ->assertOk()
        ->assertSee('Website SEO')
        ->assertSee('/privacy-policy')
        ->assertSee('/masterclass');
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');

it('saves SEO for a single page without dropping the other pages', function () {
    $original = SiteContent::query()->where('key', 'seo')->value('data');

    try {
        $this->actingAs(websiteTestUser())
            ->post('/website-seo', [
                'path' => 'refund-policy/',   // unnormalised on purpose
                'title' => 'Refund Policy — BrowseJobs',
                'description' => 'How refunds work.',
            ])
            ->assertRedirect(route('site-seo.index'));

        $saved = (array) SiteContent::query()->where('key', 'seo')->value('data');

        expect($saved['/refund-policy']['title'])->toBe('Refund Policy — BrowseJobs')
            ->and($saved)->toHaveKey('/');   // pre-existing entries survive
    } finally {
        restoreSiteContent('seo', $original ? (array) $original : null);
    }
})->skip(fn () => ! siteDatabaseAvailable(), 'LMS database not reachable');
