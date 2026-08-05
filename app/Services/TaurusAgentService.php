<?php

namespace App\Services;

/**
 * The conversational half of Taurus Neural Ops.
 *
 * The original integration kit shipped a standalone PHP bridge on Hostinger
 * that held an Anthropic key in a constant and re-fetched the dashboard over
 * HTTP. None of that is needed inside the CRM: the provider config already
 * exists in config/services.php ('ai_analysis'), the key stays in .env on the
 * server, and the snapshot is already in memory. So this is a thin wrapper —
 * build a prompt, hand it to the app's existing AI client, return the text.
 */
class TaurusAgentService
{
    public function __construct(
        private readonly AiLeadAnalysisService $ai,
        private readonly TaurusSnapshotService $snapshot,
    ) {}

    /**
     * Is any AI provider configured? Drives whether the ask bar is offered at
     * all, following the app's existing graceful no-op pattern.
     */
    public function isConfigured(): bool
    {
        return $this->ai->configuredProviders() !== [];
    }

    /**
     * Answer a founder question against the live snapshot.
     *
     * @return array{reply: string, provider: string, model: string}
     */
    public function ask(string $question, ?array $snapshot = null): array
    {
        $snapshot ??= $this->snapshot->all();

        $result = $this->ai->generate(
            $this->systemPrompt(),
            "Live snapshot (JSON):\n".json_encode($snapshot, JSON_UNESCAPED_UNICODE)
                ."\n\nQuestion: ".$question
        );

        return [
            'reply' => trim($result['text']),
            'provider' => $result['provider'],
            'model' => $result['model'],
        ];
    }

    /**
     * The written brief that greets the founder on page load — same data, no
     * question asked.
     */
    public function brief(?array $snapshot = null): string
    {
        $snapshot ??= $this->snapshot->all();

        $result = $this->ai->generate(
            $this->systemPrompt(),
            "Live snapshot (JSON):\n".json_encode($snapshot, JSON_UNESCAPED_UNICODE)
                ."\n\nGive the opening briefing: what matters right now across pipeline, money, "
                .'conversion and people, and the single thing most worth attention today. '
                .'Six sentences at most.'
        );

        return trim($result['text']);
    }

    /**
     * Expense sentinel panel — a spending read, not a general answer.
     */
    public function analyseExpenses(?array $snapshot = null): string
    {
        $snapshot ??= $this->snapshot->all();

        $result = $this->ai->generate(
            $this->systemPrompt(),
            "Subscriptions and finance (JSON):\n"
                .json_encode(['subscriptions' => $snapshot['subscriptions'], 'finance' => $snapshot['finance']], JSON_UNESCAPED_UNICODE)
                ."\n\nGive a spending read in at most 90 words: what to cancel now with the monthly "
                .'and annual saving, what to downgrade, what to keep, and one habit that would keep '
                .'burn under control. If there are no subscriptions recorded, say exactly that and stop.'
        );

        return trim($result['text']);
    }

    /**
     * Team signal panel — evidence-led, and explicitly not a verdict on anyone.
     */
    public function analyseTeam(?array $snapshot = null): string
    {
        $snapshot ??= $this->snapshot->all();

        $result = $this->ai->generate(
            $this->systemPrompt(),
            "Team targets vs live actuals (JSON):\n"
                .json_encode(['team' => $snapshot['team']], JSON_UNESCAPED_UNICODE)
                ."\n\nIn at most 100 words: who is clearly delivering and on what evidence, who needs "
                .'a structured conversation and with which specific numbers, and what support or '
                .'target reset to try first. Never recommend an exit or disciplinary step — you do '
                .'not have attendance, workload, tenure or context, and these are month-to-date '
                .'partial numbers. If no targets are set, say exactly that and stop.'
        );

        return trim($result['text']);
    }

    private function systemPrompt(): string
    {
        $company = config('taurus.agent.company');

        return <<<PROMPT
        You are Taurus, the operations analyst for {$company}.

        You are given a live JSON snapshot of the business, drawn straight from the CRM and
        LMS databases. Answer only from that snapshot. If a number needed to answer is not in
        it, say which one is missing rather than estimating — a confident wrong figure here
        costs more than an admitted gap.

        Conventions in the data:
        - Money is Indian rupees. Fields ending in Paise are integer paise; revMTD, expMTD,
          netMTD and similar are already in lakhs (1 lakh = 100,000 rupees).
        - MTD means month to date, so month-on-month comparisons against a full past month
          are unfair unless you say so.
        - Nulls and "—" mean not recorded, which is not the same as zero.
        - team[] holds month-to-date progress against monthly targets; someone at 40% on the
          10th is not behind.

        Write plainly for a founder reading between meetings: direct, numeric, no markdown,
        no preamble. Lead with the answer. Where you name a risk, name the number behind it.
        PROMPT;
    }
}
