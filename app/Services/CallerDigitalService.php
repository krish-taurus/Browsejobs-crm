<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Caller.Digital Campaigns API — triggers outbound AI voice calls.
 *
 * Flow for a single lead:
 *   1. POST /v1/dev/campaigns  with one contact  → campaign in "draft"
 *   2. POST /v1/dev/campaigns/{id}/start         → dialing begins
 *   3. Results arrive on our webhook (per call) and/or via GET /v1/dev/calls/{id}.
 *
 * Auth: log in once (POST /v1/dev/auth/login) to get a 24h bearer token; every request also
 * sends the X-API-Key header. The token is cached and reused.
 *
 * When credentials are absent, every call is a logged no-op — the rest of the app keeps working.
 */
class CallerDigitalService
{
    private const TOKEN_CACHE_KEY = 'caller_digital.access_token';

    public function isConfigured(): bool
    {
        return filled(config('services.caller_digital.email'))
            && filled(config('services.caller_digital.password'))
            && filled(config('services.caller_digital.api_key'))
            && filled(config('services.caller_digital.agent_id'));
    }

    /**
     * Create a one-contact campaign for a lead and immediately start dialing.
     *
     * @param  array<string, string>  $metadata  Extra context passed to the agent (values must be strings).
     * @return array{campaign_id: string, status: string, total_calls: int}
     */
    public function triggerLeadCall(string $phoneNumber, string $campaignName, array $metadata = [], ?string $language = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Caller.Digital is not configured. Set CALLER_DIGITAL_* in .env.');
        }

        $language ??= config('services.caller_digital.default_language', 'english');

        $payload = [
            'name' => $campaignName,
            'agent_id' => config('services.caller_digital.agent_id'),
            'concurrency' => 1,
            'contacts' => [[
                'phone_number' => $this->toE164($phoneNumber),
                'metadata' => array_map(fn ($v) => (string) $v, array_merge($metadata, [
                    'agent_language' => $language,
                ])),
            ]],
        ];

        if (filled(config('services.caller_digital.from_number'))) {
            $payload['from_number'] = config('services.caller_digital.from_number');
        }

        // 1. Create the campaign (draft).
        $create = $this->request('post', '/v1/dev/campaigns', $payload);
        $campaignId = $create['id'] ?? null;

        if (! $campaignId) {
            throw new RuntimeException('Caller.Digital did not return a campaign id.');
        }

        // 2. Start dialing.
        $this->request('post', "/v1/dev/campaigns/{$campaignId}/start");

        return [
            'campaign_id' => $campaignId,
            'status' => 'running',
            'total_calls' => (int) ($create['total_calls'] ?? 1),
        ];
    }

    /**
     * Fetch a campaign with its call records (used for polling / reconciliation).
     *
     * @return array<string, mixed>
     */
    public function getCampaign(string $campaignId): array
    {
        return $this->request('get', "/v1/dev/campaigns/{$campaignId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function getTranscript(string $callId): array
    {
        return $this->request('get', "/v1/dev/calls/{$callId}/transcript");
    }

    // ---------- internals ----------

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $url = rtrim(config('services.caller_digital.base_url'), '/').$path;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken(),
            'X-API-Key' => config('services.caller_digital.api_key'),
        ])->{$method}($url, $body);

        // A 401 likely means the cached token expired — refresh once and retry.
        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken(),
                'X-API-Key' => config('services.caller_digital.api_key'),
            ])->{$method}($url, $body);
        }

        // Rate-limited (bursts of imported leads dial back-to-back): wait the
        // interval the API asks for, then retry once instead of failing the call.
        if ($response->status() === 429) {
            $retryAfter = (int) (data_get($response->json(), 'detail.retry_after')
                ?? $response->header('Retry-After')
                ?? 3);
            Sleep::for(min(10, max(1, $retryAfter)))->seconds();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->accessToken(),
                'X-API-Key' => config('services.caller_digital.api_key'),
            ])->{$method}($url, $body);
        }

        if (! $response->successful()) {
            Log::warning('Caller.Digital request failed.', ['path' => $path, 'status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException("Caller.Digital {$path} failed ({$response->status()}): ".$response->body());
        }

        return $response->json() ?? [];
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addHours(23), function () {
            $response = Http::post(rtrim(config('services.caller_digital.base_url'), '/').'/v1/dev/auth/login', [
                'email' => config('services.caller_digital.email'),
                'password' => config('services.caller_digital.password'),
                'api_key' => config('services.caller_digital.api_key'),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Caller.Digital login failed: '.$response->body());
            }

            $token = data_get($response->json(), 'tokens.access_token');

            if (! $token) {
                throw new RuntimeException('Caller.Digital login returned no access token.');
            }

            return $token;
        });
    }

    private function toE164(string $number): string
    {
        $digits = preg_replace('/[^\d]/', '', $number);

        if (strlen($digits) === 10) {
            $digits = '91'.$digits; // default India country code
        }

        return '+'.$digits;
    }
}
