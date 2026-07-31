<?php

namespace App\Console\Commands;

use App\Models\LeadCall;
use App\Services\CallerDigitalService;
use App\Services\LeadService;
use Illuminate\Console\Command;

class SyncCallerDigitalCalls extends Command
{
    protected $signature = 'calls:sync';

    protected $description = 'Poll Caller.Digital for in-flight AI calls and update their results (fallback when the webhook cannot reach this server).';

    public function handle(CallerDigitalService $caller, LeadService $leads): int
    {
        if (! $caller->isConfigured()) {
            $this->info('Caller.Digital not configured — nothing to sync.');

            return self::SUCCESS;
        }

        $pending = LeadCall::where('type', 'ai')
            ->whereNotNull('external_campaign_id')
            ->whereIn('status', ['queued', 'ringing', 'in_progress'])
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No in-flight AI calls to sync.');

            return self::SUCCESS;
        }

        foreach ($pending as $call) {
            try {
                $campaign = $caller->getCampaign($call->external_campaign_id);
                $record = collect($campaign['calls'] ?? [])->first();

                if (! $record) {
                    continue;
                }

                $status = $this->mapStatus($record);
                $callId = $record['call_id'] ?? ($record['id'] ?? null);

                $call->fill([
                    'external_call_id' => $callId ?: $call->external_call_id,
                    'status' => $status,
                    'disposition' => $record['disposition'] ?? $call->disposition,
                    'sentiment' => $record['user_sentiment'] ?? $call->sentiment,
                    'duration_seconds' => isset($record['duration_s']) ? (int) $record['duration_s'] : $call->duration_seconds,
                    'ended_at' => in_array($status, ['completed', 'failed', 'no_answer', 'busy'], true) ? ($call->ended_at ?? now()) : $call->ended_at,
                    'meta' => array_merge($call->meta ?? [], ['poll' => $record]),
                ]);

                if ($callId && blank($call->transcript)) {
                    try {
                        $transcript = $caller->getTranscript($callId);
                        $call->transcript = $this->flattenTranscript($transcript);
                    } catch (\Throwable $e) {
                        // transcript not ready yet
                    }
                }

                $call->save();

                if ($call->status === 'completed') {
                    $leads->handleAiCallOutcome($call->fresh('lead'));
                } elseif (in_array($call->status, ['failed', 'no_answer', 'busy'], true)) {
                    $leads->handleAiCallFailure($call->fresh('lead'));
                }

                $this->line("Synced call #{$call->id}: {$call->status}");
            } catch (\Throwable $e) {
                $this->error("Call #{$call->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function mapStatus(array $record): string
    {
        $status = $record['status'] ?? null;
        $endReason = $record['end_reason'] ?? null;

        if ($status === 'failed' && in_array($endReason, ['no_answer', 'busy'], true)) {
            return $endReason;
        }

        return match ($status) {
            'completed' => 'completed',
            'failed' => 'failed',
            'in_progress', 'dialing', 'ringing' => 'ringing',
            default => 'queued',
        };
    }

    /**
     * @param  array<string, mixed>  $transcript
     */
    private function flattenTranscript(array $transcript): ?string
    {
        $turns = $transcript['turns'] ?? [];

        if (empty($turns)) {
            return null;
        }

        return collect($turns)
            ->map(fn ($t) => strtoupper($t['role'] ?? '?').': '.($t['text'] ?? ''))
            ->implode("\n");
    }
}
