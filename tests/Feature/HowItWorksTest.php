<?php

use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function anyStaffUser(string $roleCode = 'HR'): User
{
    $role = Role::firstOrCreate(['role_code' => $roleCode], ['role_name' => $roleCode, 'is_active' => true]);

    return User::create([
        'employee_id' => 'EMP-HIW-'.$roleCode, 'first_name' => 'Staff', 'full_name' => 'Staff Member',
        'email' => strtolower($roleCode).'-hiw@test.local', 'password' => 'password', 'role_id' => $role->id,
    ]);
}

it('renders the whole walkthrough', function () {
    $this->actingAs(anyStaffUser())
        ->get('/how-it-works')
        ->assertOk()
        ->assertSee('How BrowseJobs works')
        ->assertSee('From enquiry to enrolment')
        ->assertSee('After they join')
        ->assertSee('Who gets told what')
        ->assertSee('How the two systems stay in step')
        ->assertSee('What runs by itself');
});

it('shows every funnel stage and notification channel', function () {
    $response = $this->actingAs(anyStaffUser())->get('/how-it-works')->assertOk();

    foreach (['New', 'AI Call Running', 'Interested', 'Follow-up', 'Joined'] as $stage) {
        $response->assertSee($stage);
    }

    foreach (['In-app', 'Browser push', 'Email', 'WhatsApp', 'AI call'] as $channel) {
        $response->assertSee($channel);
    }
});

it('is readable by any role, not just admins', function () {
    // The whole point is that every user can understand their own workflow,
    // so this page must not sit behind a permission the junior roles lack.
    foreach (['HR', 'ACCOUNTANT', 'TECH_MENTOR', 'SOCIAL_MEDIA_MANAGER'] as $roleCode) {
        $this->actingAs(anyStaffUser($roleCode))
            ->get('/how-it-works')
            ->assertOk();
    }
});

it('renders even when the LMS database is unreachable', function () {
    // This is the page someone opens when something else is broken, so it must
    // not depend on the LMS connection.
    config(['database.connections.lms.host' => '127.0.0.1', 'database.connections.lms.port' => '1']);

    $this->actingAs(anyStaffUser())->get('/how-it-works')->assertOk();
});

it('is in the sidebar for everyone, with no permission gate', function () {
    MenuItem::create([
        'title' => 'How It Works', 'url' => 'how-it-works', 'icon' => 'ti ti-help-circle',
        'menu_group' => 'Main Menu', 'permission_id' => null, 'sort_order' => 1, 'is_active' => true,
    ]);

    // A user whose role grants nothing at all still sees it.
    $tree = MenuItem::treeForUser(anyStaffUser());

    expect($tree->pluck('title')->all())->toContain('How It Works');
});
