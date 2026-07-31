<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mentors page under Student Management: manage the LMS mentor pool from
     * the CRM. Gets its own permission, granted to every role that already
     * manages live batches (same audience), and a sidebar entry.
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'manage_mentors',
            'name' => 'Manage Mentors',
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
            'title' => 'Mentors',
            'icon' => 'ti ti-heart-handshake',
            'url' => 'mentors',
            'menu_group' => 'Student Management',
            'permission_id' => $permissionId,
            'sort_order' => 37,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', 'mentors')->delete();

        $permissionId = DB::table('permissions')->where('slug', 'manage_mentors')->value('id');
        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
