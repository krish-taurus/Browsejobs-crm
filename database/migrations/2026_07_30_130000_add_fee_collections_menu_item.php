<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fee Collections page under Student Management: the batch-wise payment
     * report (paid / due / overdue / blocked, with call links) so the team runs
     * fee follow-up calls from the CRM. Permission mirrors the live-batches
     * audience, same as the mentors page.
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'view_fee_collections',
            'name' => 'View Fee Collections',
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
            'title' => 'Fee Collections',
            'icon' => 'ti ti-cash',
            'url' => 'fee-collections',
            'menu_group' => 'Student Management',
            'permission_id' => $permissionId,
            'sort_order' => 38,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', 'fee-collections')->delete();

        $permissionId = DB::table('permissions')->where('slug', 'view_fee_collections')->value('id');
        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
