<?php

namespace App\Http\Controllers;

use App\Models\LeadCall;
use App\Services\CallerDigitalService;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CallerDigitalWebhookController extends Controller
{
    /**
     * Receives post-call results from Caller.Digital and updates the matching lead call.
     *
     * Configure your Caller.Digital webhook URL to point here, and (optionally) set
     * CALLER_DIGITAL_WEBHOOK_SECRET and append ?secret=... so we can verify the source.
     */
    public function handle(Request $request, CallerDigitalService $caller, LeadService $leads): JsonResponse
    {
        $secret = config('services.caller_digital.webhook_secret');
        if (filled($secret) && ! hash_equals($secret, (string) $request->query('secret'))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        $campaignId = $data['campaign_id'] ?? null;
        $callId = $data['call_id'] ?? null;

        // Find the AI call we created for this campaign (one call per campaign in our flow).
        $call = LeadCall::query()
            ->where('type', 'ai')
            ->where(function ($q) use ($campaignId, $callId) {
                if ($campaignId) {
                    $q->orWhere('external_campaign_id', $campaignId);
                }
                if ($callId) {
                    $q->orWhere('external_call_id', $callId);
                }
            })
            ->latest()
            ->first();

        if (! $call) {
            return response()->json(['message' => 'No matching call'], 202);
        }

        $duration = $data['duration_s'] ?? null;
        $status = $this->mapStatus($data, $duration);

        $call->fill([
            'external_call_id' => $callId ?: $call->external_call_id,
            'status' => $status,
            'disposition' => $data['disposition'] ?? $call->disposition,
            'sentiment' => $data['user_sentiment'] ?? $call->sentiment,
            'duration_seconds' => $duration !== null ? (int) $duration : $call->duration_seconds,
            'recording_url' => $data['recording_url'] ?? $call->recording_url,
            'started_at' => $this->parseTime($data['started_at'] ?? null) ?? $call->started_at,
            'ended_at' => $this->parseTime($data['ended_at'] ?? null) ?? now(),
            'meta' => array_merge($call->meta ?? [], ['webhook' => $data]),
        ]);

        // Best-effort transcript pull.
        if ($callId && $caller->isConfigured()) {
            try {
                $transcript = $caller->getTranscript($callId);
                $call->transcript = $this->flattenTranscript($transcript);
            } catch (\Throwable $e) {
                // transcript may not be ready yet — leave as-is
            }
        }

        $call->save();

        // Route the outcome: good/interested leads move to a caller-ready status and HR is alerted.
        // Calls that never connected drop the lead from "AI Call Running" back to New.
        if ($call->status === 'completed') {
            $leads->handleAiCallOutcome($call->fresh('lead'));
        } elseif (in_array($call->status, ['failed', 'no_answer', 'busy'], true)) {
            $leads->handleAiCallFailure($call->fresh('lead'));
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapStatus(array $data, mixed $duration): string
    {
        $endReason = $data['end_reason'] ?? null;

        if (in_array($endReason, ['no_answer', 'busy', 'failed'], true)) {
            return $endReason;
        }

        if (filled($data['disposition'] ?? null) || (int) $duration > 0) {
            return 'completed';
        }

        return 'failed';
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

    private function parseTime(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
