<?php

use App\Models\Lms\Celebration;
use App\Models\Lms\ContentHubItem;
use App\Models\Lms\PulseItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The student Pulse page is fed entirely by curated rows. These tests use the
 * live LMS database (like the other LMS-backed pages) and clean up after
 * themselves, so they only run where that database is reachable.
 */
function pulseDbAvailable(): bool
{
    try {
        DB::connection('lms')->select('select 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function pulseUser(): User
{
    $role = Role::firstOrCreate(['role_code' => 'SUPER_ADMIN'], ['role_name' => 'Super Admin', 'is_active' => true]);
    $perm = Permission::firstOrCreate(['slug' => 'view_students'], ['name' => 'view_students', 'module' => 'Test']);
    $role->permissions()->syncWithoutDetaching([$perm->id]);

    return User::create([
        'employee_id' => 'EMP-PULSE', 'first_name' => 'Pulse', 'full_name' => 'Pulse Admin',
        'email' => 'pulse@test.local', 'password' => 'password', 'role_id' => $role->id,
    ]);
}

it('explains why each student Pulse section is empty', function () {
    $this->actingAs(pulseUser())
        ->get('/student-pulse')
        ->assertOk()
        ->assertSee('Market Pulse')
        ->assertSee('Wins on your course')
        ->assertSee('Content Hub');
})->skip(fn () => ! pulseDbAvailable(), 'LMS database not reachable');

it('curates a Market Pulse source', function () {
    $created = null;

    try {
        $this->actingAs(pulseUser())
            ->post('/student-pulse/items', [
                'title' => 'Pulse test headline',
                'url' => 'https://example.invalid/news',
                'source_name' => 'Test Wire',
            ])
            ->assertRedirect()->assertSessionHas('success');

        $created = PulseItem::query()->where('title', 'Pulse test headline')->first();

        expect($created)->not->toBeNull()
            ->and($created->is_active)->toBeTrue()
            ->and($created->published_on->isToday())->toBeTrue();
    } finally {
        $created?->forceDelete();
    }
})->skip(fn () => ! pulseDbAvailable(), 'LMS database not reachable');

it('refuses to build a digest with no fresh sources rather than inventing news', function () {
    // Park every in-window source so the builder genuinely has nothing.
    $parked = PulseItem::query()->where('is_active', true)
        ->whereDate('published_on', '>=', now()->subDays(3))->pluck('id');
    PulseItem::query()->whereIn('id', $parked)->update(['is_active' => false]);

    try {
        $this->actingAs(pulseUser())
            ->post('/student-pulse/generate')
            ->assertRedirect()->assertSessionHas('error');
    } finally {
        PulseItem::query()->whereIn('id', $parked)->update(['is_active' => true]);
    }
})->skip(fn () => ! pulseDbAvailable(), 'LMS database not reachable');

it('publishes a Content Hub release', function () {
    $created = null;

    try {
        $this->actingAs(pulseUser())
            ->post('/student-pulse/content', [
                'kind' => 'podcast',
                'title' => 'Pulse test episode',
                'url' => 'https://example.invalid/ep1',
            ])
            ->assertRedirect()->assertSessionHas('success');

        $created = ContentHubItem::query()->where('title', 'Pulse test episode')->first();
        expect($created)->not->toBeNull()->and($created->kind)->toBe('podcast');
    } finally {
        $created?->forceDelete();
    }
})->skip(fn () => ! pulseDbAvailable(), 'LMS database not reachable');

it('rejects a content kind the LMS does not accept', function () {
    $this->actingAs(pulseUser())
        ->from('/student-pulse')
        ->post('/student-pulse/content', [
            'kind' => 'tiktok', 'title' => 'Nope', 'url' => 'https://example.invalid/x',
        ])
        ->assertSessionHasErrors('kind');
})->skip(fn () => ! pulseDbAvailable(), 'LMS database not reachable');

it('never puts a win on the wall without consent', function () {
    $studentId = (int) DB::connection('lms')->table('users')->where('user_type', 'student')->value('id');

    $this->actingAs(pulseUser())
        ->from('/student-pulse')
        ->post('/student-pulse/celebrations', [
            'student_id' => $studentId,
            'display_mode' => 'named',
            'role_title' => 'Data Engineer',
            // consent deliberately omitted
        ])
        ->assertSessionHasErrors('consent');

    expect(Celebration::query()->where('role_title', 'Data Engineer')->whereNull('published_at')->where('created_at', '>=', now()->subMinute())->count())->toBe(0);
})->skip(fn () => ! pulseDbAvailable(), 'LMS database not reachable');

it('adds a win unpublished, then publishes it', function () {
    $studentId = (int) DB::connection('lms')->table('users')->where('user_type', 'student')->value('id');
    $admin = pulseUser();
    $created = null;

    try {
        $this->actingAs($admin)
            ->post('/student-pulse/celebrations', [
                'student_id' => $studentId,
                'display_mode' => 'anonymous',
                'anonymous_label' => 'A test student',
                'role_title' => 'Pulse Test Role',
                'company' => 'Testing Ltd',
                'consent' => '1',
            ])
            ->assertRedirect()->assertSessionHas('success');

        $created = Celebration::query()->where('role_title', 'Pulse Test Role')->first();

        // A win is never live the moment it is added — publishing is a
        // separate, deliberate step.
        expect($created)->not->toBeNull()
            ->and($created->published_at)->toBeNull()
            ->and($created->consented_at)->not->toBeNull()
            ->and($created->displayName())->toBe('A test student');

        $this->actingAs($admin)
            ->post("/student-pulse/celebrations/{$created->id}/publish")
            ->assertRedirect();

        expect($created->fresh()->published_at)->not->toBeNull();
    } finally {
        $created?->forceDelete();
    }
})->skip(fn () => ! pulseDbAvailable(), 'LMS database not reachable');
