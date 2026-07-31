<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListsPublicSitePages;
use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Website → SEO: every public page listed with its search title/description —
 * whether or not one has been saved yet — so each page can be given SEO by
 * clicking it. Editing shows live length counters and a Google preview.
 */
class SiteSeoController extends Controller
{
    use ListsPublicSitePages, ReadsLmsDatabase;

    public function index(): View
    {
        return $this->renderLmsPage(function () {
            $seo = (array) (SiteContent::query()->where('key', 'seo')->value('data') ?? []);

            $pages = array_map(fn (array $page) => $page + [
                'title' => (string) (($seo[$page['path']] ?? [])['title'] ?? ''),
                'description' => (string) (($seo[$page['path']] ?? [])['description'] ?? ''),
            ], $this->publicSitePages());

            // Saved paths that are not in the catalogue (hand-added earlier, or
            // a page that has since been removed) still need to be editable.
            $known = array_column($pages, 'path');
            foreach ($seo as $path => $entry) {
                if (in_array($path, $known, true)) {
                    continue;
                }
                $pages[] = [
                    'path' => $path,
                    'label' => $path,
                    'kind' => 'custom',
                    'title' => (string) ($entry['title'] ?? ''),
                    'description' => (string) ($entry['description'] ?? ''),
                ];
            }

            return view('site-seo.index', ['pages' => $pages]);
        });
    }

    public function edit(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $raw = (string) $request->query('path', '');
            $path = $raw === '' ? '' : $this->normalisePath($raw);
            $seo = (array) (SiteContent::query()->where('key', 'seo')->value('data') ?? []);

            $labels = array_column($this->publicSitePages(), 'label', 'path');

            return view('site-seo.edit', [
                'path' => $path,
                'label' => $labels[$path] ?? $path,
                'entry' => (array) ($seo[$path] ?? []),
                'isNew' => $path === '' || ! isset($seo[$path]),
                'isKnownPage' => $path !== '' && isset($labels[$path]),
            ]);
        });
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:300'],
        ]);

        $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);
        $row = SiteContent::query()->firstOrNew(['tenant_id' => $tenantId, 'key' => 'seo']);
        $data = (array) ($row->data ?? []);

        $path = $this->normalisePath($validated['path']);
        $data[$path] = [
            'title' => trim((string) ($validated['title'] ?? '')),
            'description' => trim((string) ($validated['description'] ?? '')),
        ];

        $row->data = $data;
        $row->save();

        return redirect()->route('site-seo.index')->with('success', "SEO for {$path} saved — search engines see it on their next crawl.");
    }
}
