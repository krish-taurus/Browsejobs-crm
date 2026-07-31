<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListsPublicSitePages;
use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Course;
use App\Models\Lms\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Website → Pages: every public page in one list; clicking a page opens a
 * rich-text (Summernote) editor pre-filled with the page's CURRENT content —
 * the CMS version if one was saved, else the live built-in text scraped from
 * the running site — so the founder edits, never starts blank.
 */
class SitePagesController extends Controller
{
    use ListsPublicSitePages, ReadsLmsDatabase;

    public const SITE_URL = 'http://localhost:3000';

    public function index(): View
    {
        return $this->renderLmsPage(function () {
            $seo = (array) (SiteContent::query()->where('key', 'seo')->value('data') ?? []);
            $customBodies = SiteContent::query()->where('key', 'like', 'page:%')->pluck('data', 'key');
            $customCourses = SiteContent::query()->where('key', 'like', 'course:%')->pluck('key');

            $pages = array_map(function (array $page) use ($seo, $customBodies, $customCourses) {
                $page['customised'] = match ($page['kind']) {
                    'text' => trim((string) (((array) ($customBodies['page:'.$page['path']] ?? []))['body'] ?? '')) !== '',
                    'course' => $customCourses->contains('course:'.$page['slug']),
                    default => false,
                };
                $page['has_seo'] = trim((string) (($seo[$page['path']] ?? [])['title'] ?? '')) !== '';

                return $page;
            }, $this->publicSitePages());

            return view('site-pages.index', ['pages' => $pages]);
        });
    }

    public function edit(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $path = (string) $request->query('path', '');

            if (str_starts_with($path, '/courses/')) {
                $slug = substr($path, strlen('/courses/'));
                $course = Course::query()->where('slug', $slug)->firstOrFail();
                $override = (array) (SiteContent::query()->where('key', 'course:'.$slug)->value('data') ?? []);

                return view('site-pages.edit-course', [
                    'course' => $course,
                    'hero' => $override['hero'] ?? '',
                    'path' => $path,
                ]);
            }

            abort_unless(isset(self::$editablePages[$path]), 404);

            $body = (string) (((array) (SiteContent::query()->where('key', 'page:'.$path)->value('data') ?? []))['body'] ?? '');
            $isLiveCopy = trim($body) === '';
            if ($isLiveCopy) {
                $body = $this->scrapeLiveContent($path);
            }

            return view('site-pages.edit', [
                'path' => $path,
                'label' => self::$editablePages[$path],
                'body' => $body,
                'isLiveCopy' => $isLiveCopy,
            ]);
        });
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'in:'.implode(',', array_keys(self::$editablePages))],
            'body' => ['nullable', 'string', 'max:100000'],
        ]);

        $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);
        $clean = $this->sanitizeHtml((string) ($validated['body'] ?? ''));

        SiteContent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'page:'.$validated['path']],
            ['data' => ['body' => $clean]],
        );

        return redirect()->route('site-pages.index')
            ->with('success', self::$editablePages[$validated['path']].' saved — live within a minute.');
    }

    public function updateCourse(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:80'],
            'hero' => ['nullable', 'string', 'max:600'],
        ]);

        $tenantId = (int) (SiteContent::query()->value('tenant_id') ?? 1);

        SiteContent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'course:'.$validated['slug']],
            ['data' => array_filter(['hero' => trim(strip_tags((string) ($validated['hero'] ?? '')))])],
        );

        return redirect()->route('site-pages.index')->with('success', 'Course page saved — live within a minute.');
    }

    /**
     * The live page's current content as clean HTML (h2/p/ul only) — the
     * "old content" the founder starts editing from.
     */
    private function scrapeLiveContent(string $path): string
    {
        try {
            $html = Http::timeout(10)->get(self::SITE_URL.$path)->body();
        } catch (\Throwable) {
            return '';
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        if (! $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'))) {
            return '';
        }

        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//main//*[self::h2 or self::p or self::ul]');
        if ($nodes === false) {
            return '';
        }

        $out = '';
        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $class = $node->getAttribute('class');
            // Skip the header kicker + "Last updated" line.
            if (str_contains($class, 'kicker') || str_contains($class, 'mono')) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if ($tag === 'ul') {
                $out .= '<ul>';
                foreach ($xpath->query('.//li', $node) ?: [] as $li) {
                    $out .= '<li>'.e(trim($li->textContent)).'</li>';
                }
                $out .= '</ul>';
            } else {
                $out .= "<{$tag}>".e(trim(preg_replace('/\s+/', ' ', $node->textContent)))."</{$tag}>";
            }
        }

        return $out;
    }

    /** Allowlist sanitizer for Summernote HTML — no scripts, no event handlers. */
    private function sanitizeHtml(string $html): string
    {
        $clean = strip_tags($html, '<p><h1><h2><h3><h4><ul><ol><li><b><strong><i><em><u><a><br><blockquote><span>');
        $clean = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $clean) ?? '';
        $clean = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $clean) ?? '';

        return trim($clean);
    }
}
