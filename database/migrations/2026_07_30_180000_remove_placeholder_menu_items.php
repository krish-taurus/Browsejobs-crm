<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Founder request (Jul 2026): no dead ends in the CRM. Removes every
     * sidebar item that points at an "under maintenance" placeholder (content
     * pages, blog, locations, extra report stubs, growth dashboard) and any
     * parent group that ends up empty. The maintenance routes stay as a
     * friendly fallback for old bookmarks; rebuilding a section later means
     * re-adding its menu item.
     */
    private const PLACEHOLDER_URLS = [
        'pages', 'blogs', 'blog-posts', 'blog-categories', 'blog-comments', 'blog-tags',
        'countries', 'states', 'cities', 'testimonials', 'faq',
        'growth-dashboard', 'email-engagement',
        'company-reports', 'revenue-report', 'attendance-summary-report', 'leave-balance-summary-report',
    ];

    public function up(): void
    {
        $parentIds = DB::table('menu_items')
            ->whereIn('url', self::PLACEHOLDER_URLS)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique();

        DB::table('menu_items')->whereIn('url', self::PLACEHOLDER_URLS)->delete();

        // Drop parent groups (Blog, Location, HR Reports …) left with no children.
        foreach ($parentIds as $parentId) {
            $remaining = DB::table('menu_items')->where('parent_id', $parentId)->count();
            $parent = DB::table('menu_items')->find($parentId);
            if ($remaining === 0 && $parent !== null && $parent->url === null) {
                DB::table('menu_items')->where('id', $parentId)->delete();
            }
        }
    }

    public function down(): void
    {
        // Deliberately irreversible — the placeholders were template leftovers.
    }
};
