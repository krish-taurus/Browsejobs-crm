<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function matrixAdmin(): User
{
    $role = Role::firstOrCreate(['role_code' => 'SUPER_ADMIN'], ['role_name' => 'Super Admin', 'is_active' => true]);

    return User::create([
        'employee_id' => 'EMP-MATRIX', 'first_name' => 'Matrix', 'full_name' => 'Matrix Admin',
        'email' => 'matrix@test.local', 'password' => 'password', 'role_id' => $role->id,
    ]);
}

it('renders the matrix with a pinned header and permission column', function () {
    Role::firstOrCreate(['role_code' => 'HR'], ['role_name' => 'HR', 'is_active' => true]);
    Permission::firstOrCreate(['slug' => 'view_leads'], ['name' => 'View Leads', 'module' => 'CRM']);

    $this->actingAs(matrixAdmin())
        ->get('/roles-permissions')
        ->assertOk()
        ->assertSee('perm-scroll', false)     // the scroll container that makes sticky work
        ->assertSee('perm-col', false)        // the pinned permission column
        ->assertSee('View Leads')
        ->assertSee('Click a role name to tick or clear that whole column');
});

it('saves the matrix and clears a role that had every box unticked', function () {
    $hr = Role::firstOrCreate(['role_code' => 'HR'], ['role_name' => 'HR', 'is_active' => true]);
    $sales = Role::firstOrCreate(['role_code' => 'SALES'], ['role_name' => 'Sales', 'is_active' => true]);
    $view = Permission::firstOrCreate(['slug' => 'view_leads'], ['name' => 'View Leads', 'module' => 'CRM']);
    $edit = Permission::firstOrCreate(['slug' => 'edit_leads'], ['name' => 'Edit Leads', 'module' => 'CRM']);

    // Both roles start with both permissions.
    foreach ([$hr, $sales] as $role) {
        $role->permissions()->syncWithoutDetaching([$view->id, $edit->id]);
    }

    // Submit with Sales absent entirely — that is what "clear the column" posts,
    // since unticked checkboxes are never sent by the browser.
    $this->actingAs(matrixAdmin())
        ->post('/roles-permissions', ['permissions' => [$hr->id => [$view->id]]])
        ->assertRedirect();

    expect(DB::table('role_permissions')->where('role_id', $hr->id)->pluck('permission_id')->all())
        ->toBe([$view->id])
        ->and(DB::table('role_permissions')->where('role_id', $sales->id)->count())
        ->toBe(0);
});
