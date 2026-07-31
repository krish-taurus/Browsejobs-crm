<?php

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadService;
use App\Services\LmsCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function manualFlowStatusId(string $slug): int
{
    return LeadStatus::where('slug', $slug)->value('id');
}

function manualFlowUser(): User
{
    $role = Role::create(['role_name' => 'ADMIN', 'role_code' => 'ADMIN', 'is_active' => true]);

    foreach (['view_leads', 'view_all_leads'] as $slug) {
        $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    return User::create([
        'employee_id' => 'EMP-MMF-1',
        'first_name' => 'Manual',
        'email' => 'manual-flow@test.local',
        'password' => 'password',
        'role_id' => $role->id,
    ]);
}

it('sends the masterclass watch link once when a lead turns interested', function () {
    Mail::fake();

    $lead = Lead::create([
        'mobile' => '9812340001', 'name' => 'Invite Test', 'email' => 'invite@test.local',
        'source' => 'test', 'interested_course_slug' => 'data-engineering',
        'current_status_id' => manualFlowStatusId('interested'),
    ]);

    $service = app(LeadService::class);
    $service->sendMasterclassInvite($lead);

    expect($lead->fresh()->masterclass_link_sent_at)->not->toBeNull();

    // Second call is a no-op — the link goes out exactly once.
    $sentAt = $lead->fresh()->masterclass_link_sent_at;
    $service->sendMasterclassInvite($lead->fresh());
    expect($lead->fresh()->masterclass_link_sent_at->equalTo($sentAt))->toBeTrue();
});

it('runs the did-you-watch follow-up exactly once per lead', function () {
    config(['services.caller_digital.auto_call' => false]);

    $due = Lead::create([
        'mobile' => '9812340002', 'name' => 'Followup Due', 'source' => 'test',
        'current_status_id' => manualFlowStatusId('interested'),
        'masterclass_link_sent_at' => now()->subDay(),
    ]);

    $tooFresh = Lead::create([
        'mobile' => '9812340003', 'name' => 'Too Fresh', 'source' => 'test',
        'current_status_id' => manualFlowStatusId('interested'),
        'masterclass_link_sent_at' => now()->subHours(2),
    ]);

    $this->artisan('leads:masterclass-followup')->assertSuccessful();

    expect($due->fresh()->masterclass_followup_at)->not->toBeNull()
        ->and($tooFresh->fresh()->masterclass_followup_at)->toBeNull();

    // Re-running never double-nudges.
    $stamp = $due->fresh()->masterclass_followup_at;
    $this->artisan('leads:masterclass-followup')->assertSuccessful();
    expect($due->fresh()->masterclass_followup_at->equalTo($stamp))->toBeTrue();
});

it('allocates a lead into an LMS batch and marks it joined', function () {
    $batchId = DB::connection('lms')->table('batches')->where('status', 'active')->value('id');

    $fake = new class extends LmsCommandRunner
    {
        public array $arguments = [];

        public function run(array $arguments, int $timeoutSeconds = 120, bool $retryOnce = true): array
        {
            $this->arguments = $arguments;

            return ['ok' => true, 'output' => 'Added (fake)'];
        }
    };
    app()->instance(LmsCommandRunner::class, $fake);

    $lead = Lead::create([
        'mobile' => '9812340004', 'name' => 'Allocate Me', 'email' => 'allocate@test.local',
        'source' => 'test', 'current_status_id' => manualFlowStatusId('interested'),
    ]);

    $this->actingAs(manualFlowUser())
        ->post("/leads/{$lead->id}/allocate-batch", ['lms_batch_id' => $batchId])
        ->assertRedirect()
        ->assertSessionHas('success');

    $batchNumber = DB::connection('lms')->table('batches')->where('id', $batchId)->value('number');

    expect($fake->arguments[0])->toBe('batch:add-student')
        ->and($fake->arguments)->toContain('--credentials')
        ->and($fake->arguments)->toContain('--phone=9812340004')
        ->and($lead->fresh()->status?->slug)->toBe('joined')
        ->and($lead->fresh()->allocated_batch_number)->toBe($batchNumber);
})->skip(function () {
    try {
        return DB::connection('lms')->table('batches')->where('status', 'active')->count() === 0;
    } catch (Throwable) {
        return true;
    }
}, 'LMS database not reachable or has no active batch');
