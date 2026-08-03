<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The CRM root is a staff tool, not a landing page. It used to render Laravel's
 * stock welcome screen, which showed the framework version and a "Deploy now"
 * button to anyone who hit crm.browsejobs.ai.
 */
it('sends a signed-out visitor to the login screen', function () {
    $this->get('/')->assertRedirect(route('login.show'));
});

it('sends a signed-in user straight to their dashboard', function () {
    $role = Role::firstOrCreate(['role_code' => 'HR'], ['role_name' => 'HR', 'is_active' => true]);
    $user = User::create([
        'employee_id' => 'EMP-ROOT', 'first_name' => 'Root', 'full_name' => 'Root Tester',
        'email' => 'root@test.local', 'password' => 'password', 'role_id' => $role->id,
    ]);

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
});

it('no longer ships the default Laravel welcome page', function () {
    expect(file_exists(resource_path('views/welcome.blade.php')))->toBeFalse();
});
