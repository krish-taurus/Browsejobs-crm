<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\CallerDigitalService;
use App\Services\LeadService;
use Illuminate\Console\Command;

/**
 * Safety net for auto AI calling: every run, find leads still in "New" status
 * that have never had an AI call (missed job, downtime, imported leads, etc.)
 * and trigger the call. Respects CALLER_DIGITAL_AUTO_CALL and the daily cap.
 */
class AutoCallNewLeads extends Command
{
    protected $signature = 'leads:auto-call {--limit=10 : Max calls to place per run}';

    protected $description = 'Place AI calls for New-status leads that have no AI call yet.';

    /** Give up on a lead after this many API-level failures (rate limits, outages). */
    private const MAX_API_RETRIES = 3;

    public function handle(LeadService $leads, CallerDigitalService $caller): int
    {
        if (! config('services.caller_digital.auto_call')) {
            $this->info('CALLER_DIGITAL_AUTO_CALL is off — skipping.');

            return self::SUCCESS;
        }

        if (! $caller->isConfigured()) {
            $this->info('Caller.Digital is not configured — skipping.');

            return self::SUCCESS;
        }

        // Eligible: New-status leads with no AI call at all, OR whose only AI
        // calls died before reaching the provider (no campaign id — e.g. the
        // API rate-limited the request). Anything that actually dialled once
        // (got a campaign id, or ended completed/no_answer/busy) is excluded.
        $pending = Lead::whereHas('status', fn ($q) => $q->where('slug', 'new'))
            ->whereDoesntHave('calls', function ($q) {
                $q->where('type', 'ai')->where(function ($q) {
                    $q->whereNotNull('external_campaign_id')
                        ->orWhere('status', '!=', 'failed');
                });
            })
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No New-status leads waiting for an AI call.');

            return self::SUCCESS;
        }

        foreach ($pending as $lead) {
            if ($leads->aiCallCapReached()) {
                $this->warn('Daily AI-call cap reached — remaining leads will be tried tomorrow.');
                break;
            }

            if ($leads->candidateAlreadyAiCalled($lead)) {
                $this->line("Lead #{$lead->id} ({$lead->displayName()}): skipped — this number already completed an AI call.");

                continue;
            }

            // Retry API-level failures at most 3 times, at least 10 minutes apart.
            $failedAttempts = $lead->calls()->where('type', 'ai')->count();

            if ($failedAttempts >= self::MAX_API_RETRIES) {
                $this->line("Lead #{$lead->id} ({$lead->displayName()}): skipped — {$failedAttempts} failed call attempts, needs a human.");

                continue;
            }

            if ($failedAttempts > 0 && $lead->calls()->where('type', 'ai')->max('started_at') > now()->subMinutes(10)) {
                continue; // too soon to retry — next scheduled run will pick it up
            }

            $call = $leads->triggerAiCall($lead, null);
            $this->info("Lead #{$lead->id} ({$lead->displayName()}): ".($call?->status ?? 'skipped'));
        }

        return self::SUCCESS;
    }
}
