<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Founder request (Jul 2026): Whitelabel + Website Content are platform/
     * marketing tools, not student operations — give them their own "Website"
     * sidebar section instead of hiding them inside Student Management.
     */
    public function up(): void
    {
        $parentId = DB::table('menu_items')->insertGetId([
            'parent_id' => null,
            'title' => 'Website',
            'icon' => 'ti ti-world-www',
            'url' => null,
            'menu_group' => 'Website',
            'permission_id' => DB::table('permissions')->where('slug', 'manage_website_content')->value('id'),
            'sort_order' => 140,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->where('url', 'whitelabel')->update([
            'parent_id' => $parentId, 'menu_group' => 'Website', 'sort_order' => 1, 'updated_at' => now(),
        ]);
        DB::table('menu_items')->where('url', 'website-content')->update([
            'parent_id' => $parentId, 'menu_group' => 'Website', 'sort_order' => 2, 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $studentParent = DB::table('menu_items')->whereNull('parent_id')->where('title', 'Student Management')->value('id');

        DB::table('menu_items')->whereIn('url', ['whitelabel', 'website-content'])->update([
            'parent_id' => $studentParent, 'menu_group' => 'Student Management', 'updated_at' => now(),
        ]);
        DB::table('menu_items')->where('title', 'Website')->whereNull('url')->delete();
    }
};
