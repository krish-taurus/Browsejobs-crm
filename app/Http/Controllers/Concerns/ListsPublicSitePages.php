<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Lms\Course;

/**
 * Single source of truth for "what pages does the public site have" — shared
 * by Website → Pages (edit the content) and Website → SEO (edit the search
 * title/description). Course pages are appended from the LMS courses table so
 * a new course shows up in both menus without a code change.
 */
trait ListsPublicSitePages
{
    /**
     * Pages whose body is rich-text editable from the CRM (page:{path}).
     *
     * @var array<string, string>
     */
    protected static array $editablePages = [
        '/privacy-policy' => 'Privacy Policy',
        '/terms' => 'Terms of Service',
        '/refund-policy' => 'Refund Policy',
    ];

    /**
     * Design-managed pages: the layout is built in code, but their SEO is
     * CRM-editable like every other page.
     *
     * @var array<string, string>
     */
    protected static array $designedPages = [
        '/' => 'Homepage',
        '/courses' => 'All Courses',
        '/masterclass' => 'Masterclass',
        '/jobs' => 'Jobs',
        '/for-employers' => 'For Employers',
        '/skills' => 'Skills',
        '/salaries' => 'Salaries',
        '/reviews' => 'Reviews',
        '/brief' => 'Career Brief',
        '/register' => 'Register',
    ];

    /**
     * Every public page, in menu order.
     *
     * @return array<int, array{path: string, label: string, kind: string, slug?: string}>
     */
    protected function publicSitePages(): array
    {
        $pages = [];

        foreach (self::$designedPages as $path => $label) {
            $pages[] = ['path' => $path, 'label' => $label, 'kind' => 'designed'];
        }

        foreach (self::$editablePages as $path => $label) {
            $pages[] = ['path' => $path, 'label' => $label, 'kind' => 'text'];
        }

        foreach (Course::query()->orderBy('name')->get(['slug', 'name']) as $course) {
            $pages[] = [
                'path' => '/courses/'.$course->slug,
                'label' => $course->name,
                'kind' => 'course',
                'slug' => $course->slug,
            ];
        }

        return $pages;
    }

    /** Normalise a user-typed path the same way on read and write ("courses/" → "/courses"). */
    protected function normalisePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return rtrim($path, '/') ?: '/';
    }
}
