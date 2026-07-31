<?php

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The HR Manager supervises the calling team: they hand leads out and read the
 * performance board, but they never carry a lead list themselves.
 */
function roleUser(string $code, string $name, array $permissionSlugs = []): User
{
    $role = Role::firstOrCreate(['role_code' => $code], ['role_name' => $code, 'is_active' => true]);

    foreach ($permissionSlugs as $slug) {
        $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    return User::create([
        'employee_id' => 'EMP-'.$code.'-'.substr(md5($name), 0, 5),
        'first_name' => $name, 'full_name' => $name,
        'email' => str($name)->slug().'@test.local', 'password' => 'password',
        'role_id' => $role->id, 'is_active' => true,
    ]);
}

it('refuses to assign a lead to an HR Manager', function () {
    $manager = roleUser('HR_MANAGER', 'Priyanka Manager', ['view_leads']);
    $otherManager = roleUser('HR_MANAGER', 'Second Manager');
    LeadStatus::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'sort_order' => 1]);
    $lead = Lead::create(['name' => 'A Lead', 'mobile' => '9000000001']);

    $this->actingAs($manager)
        ->from('/leads')
        ->post("/leads/{$lead->id}/assign", ['assigned_to_user_id' => $otherManager->id])
        ->assertSessionHasErrors('assigned_to_user_id');

    expect($lead->fresh()->assigned_to_user_id)->toBeNull();
});

it('refuses to assign a lead to someone outside the calling roles', function () {
    $manager = roleUser('HR_MANAGER', 'Priyanka Manager', ['view_leads']);
    $accountant = roleUser('ACCOUNTANT', 'Ana Accountant');
    $lead = Lead::create(['name' => 'A Lead', 'mobile' => '9000000002']);

    $this->actingAs($manager)
        ->from('/leads')
        ->post("/leads/{$lead->id}/assign", ['assigned_to_user_id' => $accountant->id])
        ->assertSessionHasErrors('assigned_to_user_id');

    expect($lead->fresh()->assigned_to_user_id)->toBeNull();
});

it('allows assigning a lead to a calling HR', function () {
    $manager = roleUser('HR_MANAGER', 'Priyanka Manager', ['view_leads']);
    $caller = roleUser('HR', 'Asha Caller', ['view_leads']);
    $lead = Lead::create(['name' => 'A Lead', 'mobile' => '9000000003']);

    $this->actingAs($manager)
        ->post("/leads/{$lead->id}/assign", ['assigned_to_user_id' => $caller->id])
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->assigned_to_user_id)->toBe($caller->id);
});

it('leaves HR Managers off the performance board', function () {
    $manager = roleUser('HR_MANAGER', 'Priyanka Manager', ['view_leads']);
    roleUser('HR', 'Asha Caller', ['view_leads']);

    $this->actingAs($manager)
        ->get('/leads/performance')
        ->assertOk()
        ->assertSee('Asha Caller')
        ->assertDontSee('Priyanka Manager');
})->skip(fn () => config('database.default') === 'sqlite', 'Performance board uses MySQL TIMESTAMPDIFF');

it('gives the HR Manager a supervision dashboard, not a call list', function () {
    $manager = roleUser('HR_MANAGER', 'Priyanka Manager', ['view_leads']);
    roleUser('HR', 'Asha Caller', ['view_leads']);

    $this->actingAs($manager)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('My calling team')
        ->assertSee('Supervising')
        ->assertSee('Waiting to be assigned')
        ->assertDontSee('My Assigned Leads')
        ->assertDontSee('My Leads to Call');
});

it('still gives a calling HR their own lead list', function () {
    $caller = roleUser('HR', 'Asha Caller', ['view_leads']);

    $this->actingAs($caller)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('My Assigned Leads')
        ->assertDontSee('My calling team');
});
