<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Website group v2 (founder request): dedicated "Pages" (rich-text page
     * editor) and "SEO" (per-page titles/snippets) entries; the combined
     * Website Content page is renamed "Homepage" and keeps only the hero.
     */
    public function up(): void
    {
        $parentId = DB::table('menu_items')->whereNull('parent_id')->where('title', 'Website')->value('id');
        $permissionId = DB::table('permissions')->where('slug', 'manage_website_content')->value('id');

        DB::table('menu_items')->where('url', 'website-content')->update([
            'title' => 'Homepage', 'icon' => 'ti ti-home-edit', 'sort_order' => 1, 'updated_at' => now(),
        ]);
        DB::table('menu_items')->where('url', 'whitelabel')->update(['sort_order' => 4, 'updated_at' => now()]);

        DB::table('menu_items')->insert([
            [
                'parent_id' => $parentId, 'title' => 'Pages', 'icon' => 'ti ti-files', 'url' => 'website-pages',
                'menu_group' => 'Website', 'permission_id' => $permissionId, 'sort_order' => 2, 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'parent_id' => $parentId, 'title' => 'SEO', 'icon' => 'ti ti-search', 'url' => 'website-seo',
                'menu_group' => 'Website', 'permission_id' => $permissionId, 'sort_order' => 3, 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('url', ['website-pages', 'website-seo'])->delete();
        DB::table('menu_items')->where('url', 'website-content')->update(['title' => 'Website Content', 'icon' => 'ti ti-world']);
    }
};
