<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Taurus Neural Ops — the founder's command centre.
     *
     * The permission exists only so the dynamic sidebar can hide the link; it
     * is granted to SUPER_ADMIN and nobody else. The route itself is gated by
     * the `super-admin` middleware, so granting this permission to another
     * role from the Roles & Permissions screen shows the link but still 403s
     * on click — the gate is role membership, not a togglable permission.
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'slug' => 'view_taurus_ops',
            'name' => 'View Taurus Neural Ops',
            'module' => 'Command Centre',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $superAdminRoleId = DB::table('roles')->where('role_code', 'SUPER_ADMIN')->value('id');

        if ($superAdminRoleId !== null) {
            DB::table('role_permissions')->insert([
                'role_id' => $superAdminRoleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Its own group, pinned above everything else — this is the page the
        // founder opens first, not another entry buried under Reports.
        DB::table('menu_items')->insert([
            'parent_id' => null,
            'title' => 'Taurus Neural Ops',
            'icon' => 'ti ti-brain',
            'url' => 'taurus',
            'menu_group' => 'Command Centre',
            'permission_id' => $permissionId,
            'sort_order' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', 'taurus')->delete();

        $permissionId = DB::table('permissions')->where('slug', 'view_taurus_ops')->value('id');

        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
