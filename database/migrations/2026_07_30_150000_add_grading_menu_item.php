<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grading page under Student Management: create assignments with rubrics,
     * trigger AI draft grading, and release grades to students — the CRM home
     * of the PRD §6.5 human-approval workflow.
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'manage_grading',
            'name' => 'Manage Grading',
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
            'title' => 'Grading',
            'icon' => 'ti ti-checklist',
            'url' => 'grading',
            'menu_group' => 'Student Management',
            'permission_id' => $permissionId,
            'sort_order' => 39,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', 'grading')->delete();

        $permissionId = DB::table('permissions')->where('slug', 'manage_grading')->value('id');
        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
