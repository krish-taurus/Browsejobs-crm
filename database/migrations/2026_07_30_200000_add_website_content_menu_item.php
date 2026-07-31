<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Website Content page under Student Management: edit the public site's
     * hero copy and per-page SEO from the CRM — the founder's no-deploy CMS.
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'manage_website_content',
            'name' => 'Manage Website Content',
            'module' => 'Student Management',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $liveBatchPermissionId = DB::table('permissions')->where('slug', 'manage_live_batches')->value('id');

        if ($liveBatchPermissionId !== null) {
            $roleIds = DB::table('role_permissions')->where('permission_id', $liveBatchPermissionId)->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $parentId = DB::table('menu_items')->where('url', 'live-batches')->value('parent_id');

        DB::table('menu_items')->insert([
            'parent_id' => $parentId,
            'title' => 'Website Content',
            'icon' => 'ti ti-world',
            'url' => 'website-content',
            'menu_group' => 'Student Management',
            'permission_id' => $permissionId,
            'sort_order' => 42,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', 'website-content')->delete();

        $permissionId = DB::table('permissions')->where('slug', 'manage_website_content')->value('id');
        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
