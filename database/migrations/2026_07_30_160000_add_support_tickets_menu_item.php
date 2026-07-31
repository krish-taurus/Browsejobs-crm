<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Support Tickets page under Student Management: the CRM support desk over
     * the LMS ticket system (queue, thread, reply, status) so student help
     * requests never sit unseen.
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'manage_support_tickets',
            'name' => 'Manage Support Tickets',
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
            'title' => 'Support Tickets',
            'icon' => 'ti ti-headset',
            'url' => 'support-tickets',
            'menu_group' => 'Student Management',
            'permission_id' => $permissionId,
            'sort_order' => 40,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', 'support-tickets')->delete();

        $permissionId = DB::table('permissions')->where('slug', 'manage_support_tickets')->value('id');
        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
