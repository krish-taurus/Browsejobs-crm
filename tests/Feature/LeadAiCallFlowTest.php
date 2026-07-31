<?php

use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadStatus;
use App\Models\Lms\LmsLead;
use App\Services\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

function statusId(string $slug): int
{
    return LeadStatus::where('slug', $slug)->value('id');
}

function makeLeadWithAiCall(string $statusSlug, array $callAttributes): array
{
    $lead = Lead::create([
        'mobile' => '9876543210',
        'name' => 'Flow Test',
        'source' => 'test',
        'current_status_id' => statusId($statusSlug),
    ]);

    $call = LeadCall::create(array_merge([
        'lead_id' => $lead->id,
        'type' => 'ai',
        'provider' => 'caller_digital',
        'to_number' => $lead->mobile,
        'started_at' => now(),
    ], $callAttributes));

    return [$lead, $call];
}

it('seeds the ai_call_running status between New and Interested', function () {
    expect(LeadStatus::where('slug', 'ai_call_running')->exists())->toBeTrue()
        ->and(LeadStatus::where('slug', 'ai_call_running')->value('sort_order'))
        ->toBeLessThan(LeadStatus::where('slug', 'interested')->value('sort_order'));
});

it('moves an interested AI call outcome out of AI Call Running', function () {
    Notification::fake();
    [$lead, $call] = makeLeadWithAiCall('ai_call_running', [
        'status' => 'completed',
        'disposition' => 'interested',
    ]);

    app(LeadService::class)->handleAiCallOutcome($call->fresh('lead'));

    expect($lead->fresh()->current_status_id)->toBe(statusId('interested'));
});

it('falls back to follow-up when a completed AI call has no clear outcome', function () {
    Notification::fake();
    [$lead, $call] = makeLeadWithAiCall('ai_call_running', [
        'status' => 'completed',
        'disposition' => null,
    ]);

    app(LeadService::class)->handleAiCallOutcome($call->fresh('lead'));

    expect($lead->fresh()->current_status_id)->toBe(statusId('follow_up'));
});

it('returns a lead to New when the AI call never connects', function () {
    [$lead, $call] = makeLeadWithAiCall('ai_call_running', [
        'status' => 'no_answer',
    ]);

    app(LeadService::class)->handleAiCallFailure($call->fresh('lead'));

    $lead = $lead->fresh();
    expect($lead->current_status_id)->toBe(statusId('new'))
        ->and($lead->statusHistory()->first()->remarks)->toContain('no answer');
});

it('does not touch a lead a human already moved when the AI call fails', function () {
    [$lead, $call] = makeLeadWithAiCall('interested', [
        'status' => 'failed',
    ]);

    app(LeadService::class)->handleAiCallFailure($call->fresh('lead'));

    expect($lead->fresh()->current_status_id)->toBe(statusId('interested'));
});

// ---- Completed candidates are never auto-dialled again ----

it('detects a completed AI call to the same number in any format', function () {
    makeLeadWithAiCall('interested', [
        'status' => 'completed',
        'to_number' => '+91 98123-45678',
    ]);

    $repeat = Lead::create([
        'mobile' => '9812345678',
        'name' => 'Repeat Candidate',
        'source' => 'lms',
        'current_status_id' => statusId('new'),
    ]);

    $notCalled = Lead::create([
        'mobile' => '9700000001',
        'name' => 'Fresh Candidate',
        'source' => 'lms',
        'current_status_id' => statusId('new'),
    ]);

    $service = app(LeadService::class);

    expect($service->candidateAlreadyAiCalled($repeat))->toBeTrue()
        ->and($service->candidateAlreadyAiCalled($notCalled))->toBeFalse();
});

it('auto-calls fresh leads but skips numbers that already completed an AI call', function () {
    Notification::fake();

    config()->set('services.caller_digital', array_merge(config('services.caller_digital') ?? [], [
        'auto_call' => true,
        'email' => 'test@caller.test',
        'password' => 'secret',
        'api_key' => 'key',
        'agent_id' => 'agent-1',
        'base_url' => 'https://caller.test',
        'daily_cap' => 0,
    ]));

    Http::fake([
        'caller.test/v1/dev/auth/login' => Http::response(['tokens' => ['access_token' => 'token']]),
        'caller.test/v1/dev/campaigns/*/start' => Http::response([]),
        'caller.test/v1/dev/campaigns' => Http::response(['id' => 'CAMP-1', 'total_calls' => 1]),
    ]);

    // This candidate answered the AI agent on an earlier lead.
    makeLeadWithAiCall('interested', [
        'status' => 'completed',
        'to_number' => '+919812345678',
    ]);

    $repeat = Lead::create([
        'mobile' => '09812345678',
        'name' => 'Repeat Candidate',
        'source' => 'lms',
        'current_status_id' => statusId('new'),
    ]);

    $fresh = Lead::create([
        'mobile' => '9700000001',
        'name' => 'Fresh Candidate',
        'source' => 'lms',
        'current_status_id' => statusId('new'),
    ]);

    $this->artisan('leads:auto-call')->assertSuccessful();

    expect($repeat->fresh()->calls()->where('type', 'ai')->exists())->toBeFalse()
        ->and($repeat->fresh()->current_status_id)->toBe(statusId('new'))
        ->and($fresh->fresh()->calls()->where('type', 'ai')->exists())->toBeTrue()
        ->and($fresh->fresh()->current_status_id)->toBe(statusId('ai_call_running'));
});

// ---- Interested leads are handed to the LMS masterclass funnel ----

it('pushes an interested lead into the LMS masterclass funnel exactly once', function () {
    Notification::fake();

    $phone = '7999'.random_int(100000, 999999); // unique so dedupe cannot trip on demo data

    $lead = Lead::create([
        'mobile' => $phone,
        'name' => 'Handoff Test',
        'source' => 'test',
        'campaign_name' => 'data-engineering',
        'current_status_id' => statusId('interested'),
    ]);

    try {
        app(LeadService::class)->pushInterestedToLms($lead);

        $lmsLead = LmsLead::query()->where('phone', $phone)->first();

        expect($lmsLead)->not->toBeNull()
            ->and($lmsLead->lead_type)->toBe('masterclass')
            ->and($lmsLead->utm_source)->toBe('crm')
            ->and($lmsLead->course_slug)->toBe('data-engineering');

        // Second push (e.g. HR re-saves the status) must not duplicate.
        app(LeadService::class)->pushInterestedToLms($lead);
        expect(LmsLead::query()->where('phone', $phone)->count())->toBe(1);
    } finally {
        LmsLead::query()->where('phone', $phone)->delete(); // keep the LMS dev DB clean
    }
})->skip(fn () => ! lmsLeadsDatabaseAvailable(), 'LMS database not reachable');

it("prefers HR's interested-course choice and backfills the course after handoff", function () {
    Notification::fake();

    $phone = '7998'.random_int(100000, 999999);

    // Ad lead: campaign name is marketing junk, but HR picked the real course.
    $lead = Lead::create([
        'mobile' => $phone,
        'name' => 'Course Pick Test',
        'source' => 'manual',
        'campaign_name' => 'JulyAds-Blast-42',
        'interested_course_slug' => 'devops-cloud',
        'current_status_id' => statusId('interested'),
    ]);

    try {
        app(LeadService::class)->pushInterestedToLms($lead);

        expect(LmsLead::query()->where('phone', $phone)->value('course_slug'))->toBe('devops-cloud');

        // Backfill: lead handed off with NO course, HR sets it afterwards.
        LmsLead::query()->where('phone', $phone)->update(['course_slug' => null]);
        $lead->update(['interested_course_slug' => 'data-analytics']);

        app(LeadService::class)->pushInterestedToLms($lead->fresh());

        expect(LmsLead::query()->where('phone', $phone)->count())->toBe(1)
            ->and(LmsLead::query()->where('phone', $phone)->value('course_slug'))->toBe('data-analytics');
    } finally {
        LmsLead::query()->where('phone', $phone)->delete();
    }
})->skip(fn () => ! lmsLeadsDatabaseAvailable(), 'LMS database not reachable');

it('detects the interested course from the AI call transcript automatically', function () {
    Notification::fake();

    $phone = '7997'.random_int(100000, 999999);

    $lead = Lead::create([
        'mobile' => $phone,
        'name' => 'Transcript Course Test',
        'source' => 'manual',
        'current_status_id' => statusId('ai_call_running'),
    ]);

    $call = LeadCall::create([
        'lead_id' => $lead->id,
        'type' => 'ai',
        'status' => 'completed',
        'disposition' => 'interested',
        'to_number' => $phone,
        'transcript' => "AGENT: Which course would you like to join?\nUSER: I want to learn Data Engineering please.",
        'started_at' => now(),
    ]);

    try {
        app(LeadService::class)->handleAiCallOutcome($call->fresh('lead'));

        expect($lead->fresh()->interested_course_slug)->toBe('data-engineering')
            ->and(LmsLead::query()->where('phone', $phone)->value('course_slug'))->toBe('data-engineering');
    } finally {
        LmsLead::query()->where('phone', $phone)->delete();
    }
})->skip(fn () => ! lmsLeadsDatabaseAvailable(), 'LMS database not reachable');

// ---- Rate-limit handling and retry of API-level failures ----

function fakeCallerDigitalConfig(): void
{
    config()->set('services.caller_digital', array_merge(config('services.caller_digital') ?? [], [
        'auto_call' => true,
        'email' => 'test@caller.test',
        'password' => 'secret',
        'api_key' => 'key',
        'agent_id' => 'agent-1',
        'base_url' => 'https://caller.test',
        'daily_cap' => 0,
    ]));
}

it('waits and retries once when Caller.Digital rate-limits the call', function () {
    Sleep::fake();
    fakeCallerDigitalConfig();

    Http::fake([
        'caller.test/v1/dev/auth/login' => Http::response(['tokens' => ['access_token' => 'token']]),
        'caller.test/v1/dev/campaigns/*/start' => Http::response([]),
        'caller.test/v1/dev/campaigns' => Http::sequence()
            ->push(['detail' => ['error' => 'rate_limited', 'retry_after' => 2]], 429)
            ->push(['id' => 'CAMP-RETRY', 'total_calls' => 1]),
    ]);

    $lead = Lead::create([
        'mobile' => '9700000002',
        'name' => 'Rate Limited',
        'source' => 'lms',
        'current_status_id' => statusId('new'),
    ]);

    $call = app(LeadService::class)->triggerAiCall($lead);

    expect($call->status)->toBe('ringing')
        ->and($call->external_campaign_id)->toBe('CAMP-RETRY');

    Sleep::assertSleptTimes(1);
});

it('retries leads whose AI call never reached the provider, up to 3 attempts', function () {
    Notification::fake();
    fakeCallerDigitalConfig();

    Http::fake([
        'caller.test/v1/dev/auth/login' => Http::response(['tokens' => ['access_token' => 'token']]),
        'caller.test/v1/dev/campaigns/*/start' => Http::response([]),
        'caller.test/v1/dev/campaigns' => Http::response(['id' => 'CAMP-2', 'total_calls' => 1]),
    ]);

    // Died at the API 20 minutes ago (rate limit — no campaign id): retry.
    $apiFailed = Lead::create(['mobile' => '9700000003', 'name' => 'Api Failed', 'source' => 'lms', 'current_status_id' => statusId('new')]);
    LeadCall::create(['lead_id' => $apiFailed->id, 'type' => 'ai', 'status' => 'failed', 'to_number' => $apiFailed->mobile, 'started_at' => now()->subMinutes(20)]);

    // Actually dialled and the candidate didn't pick up: do NOT redial.
    $noAnswer = Lead::create(['mobile' => '9700000004', 'name' => 'No Answer', 'source' => 'lms', 'current_status_id' => statusId('new')]);
    LeadCall::create(['lead_id' => $noAnswer->id, 'type' => 'ai', 'status' => 'no_answer', 'external_campaign_id' => 'CAMP-OLD', 'to_number' => $noAnswer->mobile, 'started_at' => now()->subMinutes(20)]);

    // Already burned 3 API attempts: give up, needs a human.
    $exhausted = Lead::create(['mobile' => '9700000005', 'name' => 'Exhausted', 'source' => 'lms', 'current_status_id' => statusId('new')]);
    foreach (range(1, 3) as $i) {
        LeadCall::create(['lead_id' => $exhausted->id, 'type' => 'ai', 'status' => 'failed', 'to_number' => $exhausted->mobile, 'started_at' => now()->subMinutes(20 + $i)]);
    }

    $this->artisan('leads:auto-call')->assertSuccessful();

    expect($apiFailed->calls()->where('type', 'ai')->count())->toBe(2)
        ->and($apiFailed->calls()->where('type', 'ai')->latest('id')->value('status'))->toBe('ringing')
        ->and($noAnswer->calls()->where('type', 'ai')->count())->toBe(1)
        ->and($exhausted->calls()->where('type', 'ai')->count())->toBe(3);
});

// ---- LMS lead import (needs the local LMS database) ----

function lmsLeadsDatabaseAvailable(): bool
{
    try {
        DB::connection('lms')->select('select 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

it('imports LMS leads into the CRM as New and never duplicates them', function () {
    Notification::fake();

    $expected = LmsLead::whereNull('merged_into_id')->count();

    $this->artisan('leads:sync-lms')->assertSuccessful();

    $imported = Lead::whereNotNull('lms_lead_id')->get();

    // Every unmerged LMS lead with a phone is now linked in the CRM…
    expect($imported->count())->toBeGreaterThan(0)
        ->and($imported->count())->toBeLessThanOrEqual($expected);

    // …imported ones start in New (the AI auto-call pipeline's entry point).
    foreach ($imported as $lead) {
        expect($lead->source)->toBe('lms')
            ->and($lead->current_status_id)->toBe(statusId('new'));
    }

    // Running again imports nothing new.
    $countAfterFirstRun = Lead::count();
    $this->artisan('leads:sync-lms')->assertSuccessful();
    expect(Lead::count())->toBe($countAfterFirstRun);
})->skip(fn () => ! lmsLeadsDatabaseAvailable(), 'LMS database not reachable');
