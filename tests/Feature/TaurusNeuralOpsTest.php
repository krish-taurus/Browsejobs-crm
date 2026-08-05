<?php

use App\Models\Expense;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TaurusAction;
use App\Models\TaurusSubscription;
use App\Models\TaurusTarget;
use App\Models\User;
use App\Services\TaurusSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * These tests run against SQLite with no `lms` connection reachable, which is
 * deliberate: it proves the console degrades to the CRM half rather than
 * 500-ing when the LMS is down.
 */
function taurusUser(string $roleCode = 'SUPER_ADMIN', string $email = 'founder@test.local'): User
{
    $role = Role::firstOrCreate(
        ['role_code' => $roleCode],
        ['role_name' => str($roleCode)->lower()->headline()->toString(), 'is_active' => true]
    );

    return User::create([
        'employee_id' => 'EMP-'.$roleCode.'-'.substr(md5($email), 0, 6),
        'first_name' => 'Krish',
        'full_name' => 'Krish B',
        'email' => $email,
        'password' => 'password',
        'role_id' => $role->id,
        'is_active' => true,
        'employment_status' => 'Active',
    ]);
}

/* ------------------------------------------------------------------ */
/* Access control */
/* ------------------------------------------------------------------ */

it('sends a guest to the login screen', function () {
    $this->get('/taurus')->assertRedirect('/login');
});

it('opens for the Super Admin', function () {
    $this->actingAs(taurusUser())
        ->get('/taurus')
        ->assertOk()
        ->assertSee('TAURUS', false)
        ->assertSee('super admin only', false);
});

it('refuses every other role, including Admin and Head of Operations', function (string $roleCode) {
    $this->actingAs(taurusUser($roleCode, strtolower($roleCode).'@test.local'))
        ->get('/taurus')
        ->assertForbidden();
})->with(['ADMIN', 'HEAD_OF_OPERATIONS', 'HR_MANAGER', 'SALES_EXECUTIVE']);

it('refuses a non-Super-Admin even when the permission is granted to their role', function () {
    $user = taurusUser('ADMIN', 'admin@test.local');
    $permission = Permission::firstOrCreate(
        ['slug' => 'view_taurus_ops'],
        ['name' => 'View Taurus Neural Ops', 'module' => 'Command Centre']
    );
    $user->role->permissions()->syncWithoutDetaching([$permission->id]);

    // The sidebar link would show, but the route still refuses — role
    // membership is the gate, not the togglable permission.
    $this->actingAs($user)->get('/taurus')->assertForbidden();
});

it('guards every write route behind the same gate', function (string $method, string $uri) {
    $this->actingAs(taurusUser('ADMIN', 'admin2@test.local'))
        ->call($method, $uri)
        ->assertForbidden();
})->with([
    ['GET', '/taurus/snapshot'],
    ['POST', '/taurus/ask'],
    ['POST', '/taurus/analyse'],
    ['POST', '/taurus/subscriptions'],
    ['POST', '/taurus/targets'],
    ['POST', '/taurus/actions'],
]);

/* ------------------------------------------------------------------ */
/* Snapshot: conversion + funnel */
/* ------------------------------------------------------------------ */

it('measures conversion and the funnel from real CRM leads', function () {
    $new = LeadStatus::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'sort_order' => 1]);
    $interested = LeadStatus::firstOrCreate(['slug' => 'interested'], ['name' => 'Interested', 'sort_order' => 2]);
    $joined = LeadStatus::firstOrCreate(['slug' => 'joined'], ['name' => 'Joined', 'sort_order' => 6]);

    // 4 leads this month: 1 new, 2 interested (one with the masterclass sent), 1 joined.
    Lead::create(['mobile' => '9000000001', 'current_status_id' => $new->id]);
    Lead::create(['mobile' => '9000000002', 'current_status_id' => $interested->id]);
    Lead::create([
        'mobile' => '9000000003',
        'current_status_id' => $interested->id,
        'masterclass_link_sent_at' => now(),
    ]);
    Lead::create(['mobile' => '9000000004', 'current_status_id' => $joined->id]);

    $vitals = app(TaurusSnapshotService::class)->vitals();

    expect($vitals['leadsMTD'])->toBe(4)
        ->and($vitals['joinedMTD'])->toBe(1)
        ->and($vitals['convRate'])->toBe(25.0)
        // Leads, Engaged (interested + joined), Masterclass sent, Joined
        ->and($vitals['funnel']['data'])->toBe([4, 3, 1, 1]);
});

it('reports zero conversion rather than dividing by zero when there are no leads', function () {
    $vitals = app(TaurusSnapshotService::class)->vitals();

    expect($vitals['leadsMTD'])->toBe(0)
        ->and($vitals['convRate'])->toBe(0.0);
});

/* ------------------------------------------------------------------ */
/* Snapshot: finance */
/* ------------------------------------------------------------------ */

it('excludes cancelled expenses and reports lakhs', function () {
    $author = taurusUser();

    Expense::create([
        'title' => 'Office rent', 'category' => 'Rent', 'amount' => 150000,
        'expense_date' => today(), 'payment_method' => 'bank_transfer',
        'status' => 'paid', 'created_by' => $author->id,
    ]);
    Expense::create([
        'title' => 'Cancelled order', 'category' => 'Rent', 'amount' => 500000,
        'expense_date' => today(), 'payment_method' => 'bank_transfer',
        'status' => 'cancelled', 'created_by' => $author->id,
    ]);

    $finance = app(TaurusSnapshotService::class)->finance();

    // 1.5 lakhs counted, the cancelled 5 lakhs ignored.
    expect($finance['expMTD'])->toBe(1.5)
        // No LMS reachable in tests, so revenue degrades to zero, not an error.
        ->and($finance['revMTD'])->toBe(0.0)
        ->and($finance['marginMTD'])->toBeNull();
});

it('separates ad spend from other expenses and derives cost per lead', function () {
    $author = taurusUser();
    LeadStatus::firstOrCreate(['slug' => 'new'], ['name' => 'New', 'sort_order' => 1]);

    foreach (['9100000001', '9100000002'] as $mobile) {
        Lead::create(['mobile' => $mobile]);
    }

    Expense::create([
        'title' => 'Meta ads', 'category' => 'Marketing — Meta Ads', 'amount' => 2000,
        'expense_date' => today(), 'payment_method' => 'card', 'status' => 'paid', 'created_by' => $author->id,
    ]);
    Expense::create([
        'title' => 'Chai', 'category' => 'Pantry', 'amount' => 900,
        'expense_date' => today(), 'payment_method' => 'cash', 'status' => 'paid', 'created_by' => $author->id,
    ]);

    $vitals = app(TaurusSnapshotService::class)->vitals();

    expect($vitals['adSpendMTD'])->toBe('₹2,000')
        ->and($vitals['cpl'])->toBe('₹1,000');
});

/* ------------------------------------------------------------------ */
/* Expense sentinel */
/* ------------------------------------------------------------------ */

it('flags subscriptions by how long they have sat unused', function () {
    TaurusSubscription::create(['name' => 'Semrush', 'monthly_cost' => 19100, 'last_used_on' => today()->subDays(90)]);
    TaurusSubscription::create(['name' => 'Canva', 'monthly_cost' => 3000, 'last_used_on' => today()->subDays(20)]);
    TaurusSubscription::create(['name' => 'Notion', 'monthly_cost' => 6400, 'last_used_on' => today()]);
    TaurusSubscription::create(['name' => 'Never opened', 'monthly_cost' => 1000]);

    $subs = app(TaurusSnapshotService::class)->subscriptions();
    $byName = collect($subs['rows'])->keyBy('name');

    expect($byName['Semrush']['flag'])->toBe('waste')
        ->and($byName['Canva']['flag'])->toBe('watch')
        ->and($byName['Notion']['flag'])->toBe('ok')
        // Never recorded as used is the worst case, not an unknown.
        ->and($byName['Never opened']['flag'])->toBe('waste')
        ->and($subs['waste_monthly'])->toBe(20100.0)
        ->and($subs['total_monthly'])->toBe(29500.0);
});

it('leaves inactive subscriptions out of the sentinel', function () {
    TaurusSubscription::create(['name' => 'Dropped', 'monthly_cost' => 5000, 'is_active' => false]);

    expect(app(TaurusSnapshotService::class)->subscriptions()['rows'])->toBeEmpty();
});

/* ------------------------------------------------------------------ */
/* Approval queue — proposals never self-execute */
/* ------------------------------------------------------------------ */

it('queues a cancellation for approval instead of acting on it', function () {
    $sub = TaurusSubscription::create(['name' => 'Zoom Pro', 'monthly_cost' => 5600, 'last_used_on' => today()->subDays(40)]);

    $this->actingAs(taurusUser())
        ->postJson('/taurus/actions', ['action' => 'cancel_subscription', 'id' => $sub->id])
        ->assertOk()
        ->assertJson(['queued' => true]);

    expect(TaurusAction::pending()->count())->toBe(1)
        // Still active — proposing is not doing.
        ->and($sub->fresh()->is_active)->toBeTrue();
});

it('does not queue the same cancellation twice', function () {
    $sub = TaurusSubscription::create(['name' => 'Zoom Pro', 'monthly_cost' => 5600]);
    $user = taurusUser();

    $this->actingAs($user)->postJson('/taurus/actions', ['action' => 'cancel_subscription', 'id' => $sub->id]);
    $this->actingAs($user)->postJson('/taurus/actions', ['action' => 'cancel_subscription', 'id' => $sub->id])
        ->assertJson(['duplicate' => true]);

    expect(TaurusAction::count())->toBe(1);
});

it('deactivates the subscription only once the Super Admin approves', function () {
    $sub = TaurusSubscription::create(['name' => 'Zoom Pro', 'monthly_cost' => 5600]);
    $user = taurusUser();

    $action = TaurusAction::create([
        'action' => 'cancel_subscription',
        'target_type' => 'taurus_subscription',
        'target_id' => $sub->id,
        'status' => TaurusAction::STATUS_PENDING,
    ]);

    $this->actingAs($user)
        ->patch('/taurus/actions/'.$action->id, ['decision' => 'approved'])
        ->assertRedirect();

    expect($sub->fresh()->is_active)->toBeFalse()
        ->and($action->fresh()->decided_by_user_id)->toBe($user->id);
});

it('leaves the subscription alone when the request is dismissed', function () {
    $sub = TaurusSubscription::create(['name' => 'Zoom Pro', 'monthly_cost' => 5600]);
    $action = TaurusAction::create([
        'action' => 'cancel_subscription',
        'target_type' => 'taurus_subscription',
        'target_id' => $sub->id,
        'status' => TaurusAction::STATUS_PENDING,
    ]);

    $this->actingAs(taurusUser())->patch('/taurus/actions/'.$action->id, ['decision' => 'dismissed']);

    expect($sub->fresh()->is_active)->toBeTrue()
        ->and($action->fresh()->status)->toBe(TaurusAction::STATUS_DISMISSED);
});

/* ------------------------------------------------------------------ */
/* Team signal — targets stored, actuals measured live */
/* ------------------------------------------------------------------ */

it('measures the actual against a stored target from live CRM rows', function () {
    $user = taurusUser();

    // Three leads added by this person this month, against a target of four.
    foreach (['9200000001', '9200000002', '9200000003'] as $mobile) {
        Lead::create(['mobile' => $mobile, 'added_by_user_id' => $user->id]);
    }
    // One added by nobody — must not be credited to them.
    Lead::create(['mobile' => '9200000009']);

    TaurusTarget::create([
        'user_id' => $user->id,
        'metric' => 'leads_added',
        'target_value' => 4,
        'period' => now()->format('Y-m'),
    ]);

    $row = app(TaurusSnapshotService::class)->team()[0];

    expect($row['actual'])->toBe(3.0)
        ->and($row['target'])->toBe(4.0)
        ->and($row['pct'])->toBe(75)
        ->and($row['sig'])->toBe('track');
});

it('credits a conversion to whoever moved the lead to joined', function () {
    $closer = taurusUser('SUPER_ADMIN', 'closer@test.local');
    $joined = LeadStatus::firstOrCreate(['slug' => 'joined'], ['name' => 'Joined', 'sort_order' => 6]);
    $lead = Lead::create(['mobile' => '9300000001', 'current_status_id' => $joined->id]);

    DB::table('lead_status_history')->insert([
        'lead_id' => $lead->id,
        'status_id' => $joined->id,
        'changed_by_user_id' => $closer->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    TaurusTarget::create([
        'user_id' => $closer->id,
        'metric' => 'leads_converted',
        'target_value' => 1,
        'period' => now()->format('Y-m'),
    ]);

    expect(app(TaurusSnapshotService::class)->team()[0]['sig'])->toBe('keep');
});

it('replaces rather than duplicates a target for the same person, metric and month', function () {
    $user = taurusUser();
    $period = now()->format('Y-m');

    foreach ([5, 9] as $value) {
        $this->actingAs($user)->post('/taurus/targets', [
            'user_id' => $user->id,
            'metric' => 'calls_made',
            'target_value' => $value,
            'period' => $period,
        ])->assertRedirect();
    }

    expect(TaurusTarget::count())->toBe(1)
        ->and((float) TaurusTarget::first()->target_value)->toBe(9.0);
});

it('rejects a target for a metric that cannot be measured', function () {
    $user = taurusUser();

    $this->actingAs($user)->post('/taurus/targets', [
        'user_id' => $user->id,
        'metric' => 'revenue_collected',   // deliberately not offered: no CRM owner
        'target_value' => 100000,
        'period' => now()->format('Y-m'),
    ])->assertSessionHasErrors('metric');
});

/* ------------------------------------------------------------------ */
/* Agent */
/* ------------------------------------------------------------------ */

it('says so plainly when no AI provider is configured, rather than erroring', function () {
    config()->set('services.ai_analysis', [
        'anthropic' => ['label' => 'Claude', 'api_key' => null, 'model' => 'x', 'base_url' => 'https://example.test'],
    ]);

    $this->actingAs(taurusUser())
        ->postJson('/taurus/ask', ['query' => 'how are we doing?'])
        ->assertOk()
        ->assertJsonFragment(['reply' => 'No AI provider is configured. Add ANTHROPIC_API_KEY (or another provider key) to .env, then run php artisan config:clear.']);
});

it('validates the question before spending a model call', function () {
    $this->actingAs(taurusUser())
        ->postJson('/taurus/ask', ['query' => ''])
        ->assertStatus(422);
});

/* ------------------------------------------------------------------ */
/* Degradation */
/* ------------------------------------------------------------------ */

it('still serves the snapshot when the LMS database is unreachable', function () {
    $response = $this->actingAs(taurusUser())->getJson('/taurus/snapshot')->assertOk();

    expect($response->json('pipeline.available'))->toBeFalse()
        ->and($response->json('pipeline.mocks.today'))->toBe(0)
        // The CRM half keeps working.
        ->and($response->json('vitals.attendance.staff'))->toBeArray();
});
