<?php

use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function menuUser(array $permissionSlugs = [], string $roleCode = 'HR'): User
{
    $role = Role::firstOrCreate(['role_code' => $roleCode], ['role_name' => $roleCode, 'is_active' => true]);

    foreach ($permissionSlugs as $slug) {
        $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    return User::create([
        'employee_id' => 'EMP-MENU', 'first_name' => 'Menu', 'full_name' => 'Menu Tester',
        'email' => 'menu@test.local', 'password' => 'password', 'role_id' => $role->id,
    ]);
}

it('drops a heading whose children are all hidden, instead of a dead link', function () {
    $visible = Permission::firstOrCreate(['slug' => 'can_see'], ['name' => 'Can see', 'module' => 'Test']);
    $hidden = Permission::firstOrCreate(['slug' => 'cannot_see'], ['name' => 'Cannot see', 'module' => 'Test']);

    // A heading (no URL) whose only child the user may NOT see.
    $deadHeading = MenuItem::create(['title' => 'Campaigns', 'url' => null, 'menu_group' => 'Main', 'sort_order' => 1, 'is_active' => true]);
    MenuItem::create(['title' => 'Blast', 'url' => 'campaigns/blast', 'parent_id' => $deadHeading->id,
        'permission_id' => $hidden->id, 'menu_group' => 'Main', 'sort_order' => 2, 'is_active' => true]);

    // A heading whose child the user MAY see — must survive.
    $liveHeading = MenuItem::create(['title' => 'Dashboard', 'url' => null, 'menu_group' => 'Main', 'sort_order' => 3, 'is_active' => true]);
    MenuItem::create(['title' => 'Leads Dashboard', 'url' => 'leads-dashboard', 'parent_id' => $liveHeading->id,
        'permission_id' => $visible->id, 'menu_group' => 'Main', 'sort_order' => 4, 'is_active' => true]);

    $tree = MenuItem::treeForUser(menuUser(['can_see']));

    expect($tree->pluck('title')->all())->toBe(['Dashboard'])
        ->and($tree->first()->childrenTree->pluck('title')->all())->toBe(['Leads Dashboard']);
});

it('drops a heading left empty because its only child was itself pruned', function () {
    $outer = MenuItem::create(['title' => 'Applications', 'url' => null, 'menu_group' => 'Main', 'sort_order' => 1, 'is_active' => true]);
    $inner = MenuItem::create(['title' => 'Social Feed', 'url' => null, 'parent_id' => $outer->id, 'menu_group' => 'Main', 'sort_order' => 2, 'is_active' => true]);
    $hidden = Permission::firstOrCreate(['slug' => 'nope'], ['name' => 'Nope', 'module' => 'Test']);
    MenuItem::create(['title' => 'Feed', 'url' => 'social/feed', 'parent_id' => $inner->id,
        'permission_id' => $hidden->id, 'menu_group' => 'Main', 'sort_order' => 3, 'is_active' => true]);

    expect(MenuItem::treeForUser(menuUser())->pluck('title')->all())->toBe([]);
});

it('keeps a heading that has a URL of its own even with no children', function () {
    MenuItem::create(['title' => 'Leads', 'url' => 'leads', 'menu_group' => 'Main', 'sort_order' => 1, 'is_active' => true]);

    expect(MenuItem::treeForUser(menuUser())->pluck('title')->all())->toBe(['Leads']);
});

it('renders the redesigned role dashboard for a calling HR', function () {
    $this->actingAs(menuUser([], 'HR'))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('My Assigned Leads')
        ->assertSee('Quick links')
        ->assertSee('dash-hero', false)
        ->assertSee('No leads assigned to you yet.');
});
