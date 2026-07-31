<?php

use App\Models\Lms\Batch;
use App\Models\Lms\BatchMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\LmsCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The Student Management pages read the live BrowseJobs LMS database, so these
 * smoke tests only run where that database is reachable (local dev).
 */
function lmsDatabaseAvailable(): bool
{
    try {
        DB::connection('lms')->select('select 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function lmsTestUser(): User
{
    $role = Role::create(['role_name' => 'ADMIN', 'role_code' => 'ADMIN', 'is_active' => true]);

    foreach (['view_students', 'manage_live_batches', 'manage_masterclass', 'manage_class_attendance', 'manage_mock_test_results'] as $slug) {
        $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    return User::create([
        'employee_id' => 'EMP-LMS-1',
        'first_name' => 'Lms',
        'email' => 'lms-smoke@test.local',
        'password' => 'password',
        'role_id' => $role->id,
    ]);
}

it('renders the live batches list', function () {
    $this->actingAs(lmsTestUser())->get('/live-batches')->assertOk()->assertSee('Live Batches');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders a batch roster with per-student stats', function () {
    $batch = Batch::first();

    $this->actingAs(lmsTestUser())
        ->get("/live-batches/{$batch->id}")
        ->assertOk()
        ->assertSee('Student Roster');
})->skip(fn () => ! lmsDatabaseAvailable() || Batch::count() === 0, 'LMS database not reachable or empty');

it('renders an individual student report', function () {
    $member = BatchMember::first();

    $this->actingAs(lmsTestUser())
        ->get("/live-batches/{$member->batch_id}/students/{$member->user_id}")
        ->assertOk()
        ->assertSee('Student Report');
})->skip(fn () => ! lmsDatabaseAvailable() || BatchMember::count() === 0, 'LMS database not reachable or empty');

it('renders the masterclasses list', function () {
    $this->actingAs(lmsTestUser())->get('/masterclasses')->assertOk()->assertSee('Masterclasses');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('plans, reschedules and cancels a masterclass from the CRM', function () {
    $slug = DB::connection('lms')->table('courses')->orderBy('id')->value('slug');
    $date = now()->addDays(9)->toDateString();
    $newDate = now()->addDays(16)->toDateString();
    $user = lmsTestUser();

    $batchId = null;

    try {
        // Plan: batch + class slot created inside the LMS.
        $this->actingAs($user)
            ->post('/masterclasses', ['course_slug' => $slug, 'date' => $date, 'time' => '11:00', 'capacity' => 40])
            ->assertRedirect()->assertSessionHas('success');

        $batch = Batch::query()
            ->where('type', 'masterclass')->whereDate('starts_on', $date)->orderByDesc('id')->first();
        $batchId = $batch?->id;

        expect($batch)->not->toBeNull()
            ->and($batch->capacity)->toBe(40)
            ->and($batch->liveSessions()->count())->toBe(1)
            ->and($batch->liveSessions()->first()->scheduled_start->format('H:i'))->toBe('11:00');

        // Reschedule: batch date + class move together.
        $this->actingAs($user)
            ->patch("/masterclasses/{$batch->id}/schedule", ['date' => $newDate, 'time' => '15:30', 'reason' => 'Trainer availability'])
            ->assertRedirect()->assertSessionHas('success');

        $batch = $batch->fresh();
        expect($batch->starts_on->toDateString())->toBe($newDate)
            ->and($batch->liveSessions()->first()->scheduled_start->format('Y-m-d H:i'))->toBe($newDate.' 15:30');

        // Cancel: batch cancelled, class cancelled.
        $this->actingAs($user)
            ->delete("/masterclasses/{$batch->id}", ['reason' => 'Test cancellation'])
            ->assertRedirect()->assertSessionHas('success');

        $batch = $batch->fresh();
        expect($batch->status)->toBe('cancelled')
            ->and($batch->liveSessions()->first()->status)->toBe('cancelled');
    } finally {
        if ($batchId !== null) {
            DB::connection('lms')->table('session_changes')->whereIn(
                'live_session_id',
                DB::connection('lms')->table('live_sessions')->where('batch_id', $batchId)->pluck('id')
            )->delete();
            DB::connection('lms')->table('live_sessions')->where('batch_id', $batchId)->delete();
            DB::connection('lms')->table('batches')->where('id', $batchId)->delete();
        }
    }
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('assigns a course to a stuck lead from the funnel page', function () {
    $slug = DB::connection('lms')->table('courses')->orderBy('id')->value('slug');
    $phone = '+917996'.random_int(100000, 999999);

    $lmsLeadId = DB::connection('lms')->table('leads')->insertGetId([
        'tenant_id' => DB::connection('lms')->table('tenants')->value('id'),
        'lead_type' => 'masterclass', 'name' => 'Stuck Lead', 'phone' => $phone,
        'phone_normalized' => preg_replace('/\D+/', '', $phone),
        'consented_at' => now(), 'consent_version' => 'v1',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    try {
        $this->actingAs(lmsTestUser())
            ->patch("/batch-funnel/leads/{$lmsLeadId}/course", ['course_slug' => $slug])
            ->assertRedirect()->assertSessionHas('success');

        expect(DB::connection('lms')->table('leads')->where('id', $lmsLeadId)->value('course_slug'))->toBe($slug);
    } finally {
        DB::connection('lms')->table('leads')->where('id', $lmsLeadId)->delete();
    }
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('runs the funnel from the cockpit button with an isolated environment', function () {
    // PHPUnit exports DB_CONNECTION/DB_DATABASE into this process's env — the
    // exact pollution that once made the LMS child run against the wrong DB.
    // A successful run therefore proves the env isolation works.
    $this->actingAs(lmsTestUser())
        ->post('/batch-funnel/run')
        ->assertRedirect()
        ->assertSessionHas('success');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the batch funnel cockpit', function () {
    $this->actingAs(lmsTestUser())
        ->get('/batch-funnel')
        ->assertOk()
        ->assertSee('Batch Funnel')
        ->assertSee('Course Interest')
        ->assertSee('Funnel Chains');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('schedules a class for a live batch through the LMS runner', function () {
    $batch = Batch::query()->whereIn('type', ['bootcamp', 'paid'])->where('status', 'active')->first();

    // Fake the runner: the LMS side of class:schedule is covered by the LMS's
    // own test suite; here we only assert the CRM invokes it correctly without
    // creating a real class (which would message real students).
    $fake = new class extends LmsCommandRunner
    {
        public array $arguments = [];

        public function run(array $arguments, int $timeoutSeconds = 120, bool $retryOnce = true): array
        {
            $this->arguments = $arguments;

            return ['ok' => true, 'output' => 'Class scheduled (fake)'];
        }
    };
    app()->instance(LmsCommandRunner::class, $fake);

    $this->actingAs(lmsTestUser())
        ->post("/live-batches/{$batch->id}/classes", [
            'date' => now()->addDay()->toDateString(),
            'time' => '19:00',
            'title' => 'CRM Smoke Class',
            'duration' => 60,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($fake->arguments)
        ->toContain('class:schedule')
        ->toContain((string) $batch->id)
        ->toContain('--title=CRM Smoke Class')
        ->toContain('--duration=60');
})->skip(fn () => ! lmsDatabaseAvailable() || Batch::query()->whereIn('type', ['bootcamp', 'paid'])->where('status', 'active')->count() === 0, 'LMS database not reachable or has no active bootcamp/paid batch');

it('drives batch management through the LMS runner (announce, add student, member status)', function () {
    $batch = Batch::query()->where('status', 'active')->first();

    $fake = new class extends LmsCommandRunner
    {
        public array $calls = [];

        public function run(array $arguments, int $timeoutSeconds = 120, bool $retryOnce = true): array
        {
            $this->calls[] = $arguments;

            return ['ok' => true, 'output' => 'ok (fake)'];
        }
    };
    app()->instance(LmsCommandRunner::class, $fake);

    $user = lmsTestUser();

    $this->actingAs($user)
        ->post("/live-batches/{$batch->id}/announce", ['subject' => 'Heads up', 'message' => 'Class moved 30 minutes earlier.'])
        ->assertRedirect()->assertSessionHas('success');

    $this->actingAs($user)
        ->post("/live-batches/{$batch->id}/students", [
            'name' => 'Fake Student', 'phone' => '+919000012345', 'status' => 'enrolled', 'credentials' => 1,
        ])
        ->assertRedirect()->assertSessionHas('success');

    // The {user} segment is bound against the LMS users table, so give it a
    // real row to resolve (cleaned up below).
    $lmsUserId = DB::connection('lms')->table('users')->insertGetId([
        'tenant_id' => DB::connection('lms')->table('tenants')->value('id'),
        'name' => 'Route Binding Smoke', 'phone' => '+917999'.random_int(100000, 999999),
        'user_type' => 'student', 'created_at' => now(), 'updated_at' => now(),
    ]);

    try {
        $this->actingAs($user)
            ->patch("/live-batches/{$batch->id}/students/{$lmsUserId}/status", ['status' => 'paused'])
            ->assertRedirect()->assertSessionHas('success');
    } finally {
        DB::connection('lms')->table('users')->where('id', $lmsUserId)->delete();
    }

    expect($fake->calls)->toHaveCount(3)
        ->and($fake->calls[0][0])->toBe('batch:announce')
        ->and($fake->calls[1][0])->toBe('batch:add-student')
        ->and($fake->calls[1])->toContain('--credentials')
        ->and($fake->calls[2])->toBe(['batch:member-status', (string) $batch->id, (string) $lmsUserId, 'paused']);
})->skip(fn () => ! lmsDatabaseAvailable() || Batch::query()->where('status', 'active')->count() === 0, 'LMS database not reachable or has no active batch');

it('renders the class attendance list', function () {
    $this->actingAs(lmsTestUser())->get('/class-attendance')->assertOk()->assertSee('Class Attendance');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the mentors management page', function () {
    $this->actingAs(lmsTestUser())->get('/mentors')->assertOk()->assertSee('Mentor Pool');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the mock test results page', function () {
    $this->actingAs(lmsTestUser())->get('/mock-test-results')->assertOk()->assertSee('Mock Test Results');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the fee collections report with batch-wise payment rows', function () {
    $this->actingAs(lmsTestUser())->get('/fee-collections')->assertOk()
        ->assertSee('Fee Collections')
        ->assertSee('Needs Follow-up Call');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the grading queue with AI drafts and release actions', function () {
    $this->actingAs(lmsTestUser())->get('/grading')->assertOk()
        ->assertSee('Grading')
        ->assertSee('Awaiting Release');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the support tickets queue', function () {
    $this->actingAs(lmsTestUser())->get('/support-tickets')->assertOk()
        ->assertSee('Support Tickets')
        ->assertSee('SLA Breached');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the whitelabel branding manager', function () {
    $this->actingAs(lmsTestUser())->get('/whitelabel')->assertOk()
        ->assertSee('Whitelabel Branding')
        ->assertSee('Business name');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the visual leads dashboard flow', function () {
    $this->actingAs(lmsTestUser())->get('/leads-dashboard')->assertOk()
        ->assertSee('Job lead arrives')
        ->assertSee('HR Manager routes it')
        ->assertSee('HR follows up');
});

it('renders the visual revenue summary dashboard', function () {
    $this->actingAs(lmsTestUser())->get('/revenue-summary-dashboard')->assertOk()
        ->assertSee('Revenue Summary')
        ->assertSee('Course fees')
        ->assertSee('Collected');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('renders the homepage copy editor', function () {
    $this->actingAs(lmsTestUser())->get('/website-content')->assertOk()
        ->assertSee('Hero')
        ->assertSee('Manifesto');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('lists all editable website pages', function () {
    $this->actingAs(lmsTestUser())->get('/website-pages')->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Refund Policy');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('opens the rich-text page editor prefilled with current content', function () {
    $this->actingAs(lmsTestUser())->get('/website-pages/edit?path=/refund-policy')->assertOk()
        ->assertSee('Save');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');

it('lists per-page SEO entries', function () {
    $this->actingAs(lmsTestUser())->get('/website-seo')->assertOk()
        ->assertSee('Edit SEO');
})->skip(fn () => ! lmsDatabaseAvailable(), 'LMS database not reachable');
