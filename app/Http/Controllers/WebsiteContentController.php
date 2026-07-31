<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListsHomepageCopy;
use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Website → Homepage: every line of copy on the public landing page, section by
 * section — live on the next page load (the site caches content for ~60s), no
 * deploy. Page bodies live under Website → Pages, snippets under Website → SEO.
 *
 * Storage: site_contents key "homepage" => { "section.field": "text" }. Only
 * changed values are stored; a blank box falls back to the site's shipped copy.
 */
class WebsiteContentController extends Controller
{
    use ListsHomepageCopy, ReadsLmsDatabase;

    public function index(): View
    {
        return $this->renderLmsPage(function () {
            $saved = $this->savedCopy();

            return view('website-content.index', [
                'sections' => $this->homepageSections(),
                'saved' => $saved,
                // What the page says right now: the override if there is one,
                // otherwise the wording the site ships with. Every box is
                // prefilled with this so the founder edits, never guesses.
                'current' => array_merge($this->homepageDefaults(), $saved),
            ]);
        });
    }

    /** Save one section's fields, leaving every other section untouched. */
    public function updateSection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section' => ['required', 'string'],
            'copy' => ['required', 'array'],
            'copy.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $sections = $this->homepageSections();
        abort_unless(isset($sections[$validated['section']]), 404);

        $defaults = $this->homepageDefaults();
        $data = $this->savedCopy();

        foreach (array_keys($sections[$validated['section']]['fields']) as $key) {
            $value = trim((string) ($validated['copy'][$key] ?? ''));

            // Blank, or untouched from the prefilled original, means "keep
            // following the site's own copy" — so store nothing for that field.
            if ($value === '' || $value === trim($defaults[$key])) {
                unset($data[$key]);

                continue;
            }

            $data[$key] = $value;
        }

        $this->storeCopy($data);

        return back()->with('success', $validated['section'].' saved — live on the site within a minute.');
    }

    /** Restore a whole section to the copy the site ships with. */
    public function resetSection(Request $request): RedirectResponse
    {
        $validated = $request->validate(['section' => ['required', 'string']]);

        $sections = $this->homepageSections();
        abort_unless(isset($sections[$validated['section']]), 404);

        $data = array_diff_key($this->savedCopy(), $sections[$validated['section']]['fields']);
        $this->storeCopy($data);

        return back()->with('success', $validated['section'].' reset to the original wording.');
    }

    /**
     * Saved overrides, with the pre-existing hero-only record folded in under
     * its "hero.*" keys so nothing the founder already wrote is lost.
     *
     * @return array<string, string>
     */
    private function savedCopy(): array
    {
        $copy = (array) (SiteContent::query()->where('key', 'homepage')->value('data') ?? []);

        foreach ((array) (SiteContent::query()->where('key', 'hero')->value('data') ?? []) as $key => $value) {
            $copy['hero.'.$key] ??= (string) $value;
        }

        return array_filter($copy, fn ($value) => is_string($value) && trim($value) !== '');
    }

    /** @param  array<string, string>  $data */
    private function storeCopy(array $data): void
    {
        $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);

        SiteContent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'homepage'],
            ['data' => $data],
        );

        // The site still reads the legacy "hero" record for the first screen;
        // keep it in step so the hero never goes stale behind the new editor.
        $hero = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'hero.')) {
                $hero[substr($key, 5)] = $value;
            }
        }

        SiteContent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'hero'],
            ['data' => $hero],
        );
    }
}
