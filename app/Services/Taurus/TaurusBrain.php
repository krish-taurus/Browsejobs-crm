<?php

namespace App\Services\Taurus;

use App\Models\TaurusAction;
use Illuminate\Support\Facades\Http;

/**
 * The conversational agent loop behind the Taurus console.
 *
 * Runs the standard Anthropic tool-use cycle over HTTP, matching how the rest
 * of this app talks to model providers (see AiLeadAnalysisService) rather than
 * pulling in an SDK:
 *
 *   send messages + tools
 *     └─ stop_reason "tool_use"  → run each tool, append ONE user message
 *        containing every tool_result, send again
 *     └─ stop_reason "end_turn"  → return the text
 *
 * Read tools execute inside the loop. Write tools do not execute at all —
 * TaurusToolkit records them as pending approvals and tells the model so, and
 * this class surfaces those to the caller for a confirmation card. The model
 * cannot send a message or create a task on its own, by construction.
 */
class TaurusBrain
{
    /** Hard stop on the loop, so a confused model can't spend money forever. */
    private const MAX_TURNS = 8;

    public function __construct(private readonly TaurusToolkit $toolkit) {}

    /**
     * Hold one exchange.
     *
     * @param  array<int, array{role: string, content: mixed}>  $history  Prior turns, oldest first.
     * @return array{
     *     reply: string,
     *     history: array<int, array{role: string, content: mixed}>,
     *     pending_action_ids: array<int, int>,
     *     tools_used: array<int, string>,
     *     provider: string,
     *     model: string
     * }
     */
    public function converse(string $question, array $history, string $systemPrompt): array
    {
        $config = $this->anthropicConfig();

        $messages = [...$history, ['role' => 'user', 'content' => $question]];
        $toolsUsed = [];
        $pendingActionIds = [];

        for ($turn = 0; $turn < self::MAX_TURNS; $turn++) {
            $response = $this->send($config, $systemPrompt, $messages);

            $content = $response['content'] ?? [];
            $messages[] = ['role' => 'assistant', 'content' => $content];

            if (($response['stop_reason'] ?? null) !== 'tool_use') {
                return [
                    'reply' => $this->textOf($content) ?: 'I had nothing to add.',
                    'history' => $messages,
                    'pending_action_ids' => $pendingActionIds,
                    'tools_used' => $toolsUsed,
                    'provider' => 'anthropic',
                    'model' => $config['model'],
                ];
            }

            // Every tool_result for this turn goes back in ONE user message —
            // splitting them teaches the model to stop calling tools in parallel.
            $results = [];

            foreach ($content as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }

                $toolsUsed[] = $block['name'];
                $outcome = $this->toolkit->call($block['name'], (array) ($block['input'] ?? []));

                if ($outcome['action_id'] !== null) {
                    $pendingActionIds[] = $outcome['action_id'];
                }

                $results[] = array_filter([
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => $outcome['content'],
                    'is_error' => $outcome['is_error'] ?: null,
                ], fn ($v) => $v !== null);
            }

            $messages[] = ['role' => 'user', 'content' => $results];
        }

        return [
            'reply' => 'I went round in circles on that one and stopped myself. Try asking it more narrowly.',
            'history' => $messages,
            'pending_action_ids' => $pendingActionIds,
            'tools_used' => $toolsUsed,
            'provider' => 'anthropic',
            'model' => $config['model'],
        ];
    }

    /**
     * One-shot completion with no tools — used by the panel analyses and the
     * opening brief, where the snapshot is already in hand.
     */
    public function complete(string $systemPrompt, string $userContent): string
    {
        $config = $this->anthropicConfig();

        $response = $this->send($config, $systemPrompt, [
            ['role' => 'user', 'content' => $userContent],
        ], withTools: false);

        return trim($this->textOf($response['content'] ?? []));
    }

    /**
     * The pending proposals raised during a conversation, ready to render as
     * confirmation cards.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function pendingActions(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return TaurusAction::whereIn('id', $ids)
            ->where('status', TaurusAction::STATUS_PENDING)
            ->get()
            ->map(fn (TaurusAction $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'summary' => $a->summary,
                'payload' => $a->payload,
            ])->all();
    }

    /**
     * @param  array{api_key: string, model: string, base_url: string}  $config
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return array<string, mixed>
     */
    private function send(array $config, string $systemPrompt, array $messages, bool $withTools = true): array
    {
        $body = [
            'model' => $config['model'],
            // Non-streaming, so kept well under the SDK/HTTP timeout ceiling.
            'max_tokens' => 8192,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        if ($withTools) {
            $body['tools'] = $this->toolkit->definitions();
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'x-api-key' => $config['api_key'],
                'anthropic-version' => '2023-06-01',
            ])
            ->post(rtrim($config['base_url'], '/').'/v1/messages', $body);

        if ($response->failed()) {
            throw new \RuntimeException('Claude API error: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
        }

        if ($response->json('stop_reason') === 'refusal') {
            throw new \RuntimeException('Claude declined to answer that.');
        }

        return $response->json();
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     */
    private function textOf(array $content): string
    {
        return collect($content)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    /**
     * Tool use needs Anthropic specifically — the OpenAI-compatible providers
     * this app also supports use a different tool schema, and silently falling
     * back to one of them would produce an agent with no hands.
     *
     * @return array{api_key: string, model: string, base_url: string}
     */
    private function anthropicConfig(): array
    {
        $config = config('services.ai_analysis.anthropic');

        if (! $config || blank($config['api_key'])) {
            throw new \RuntimeException(
                'Taurus needs ANTHROPIC_API_KEY in .env — the assistant uses Claude tool calling, '
                .'which the other configured providers do not share.'
            );
        }

        return $config;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.ai_analysis.anthropic.api_key'));
    }
}
