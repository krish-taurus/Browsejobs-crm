<?php

/*
|--------------------------------------------------------------------------
| Taurus Neural Ops — data mapping
|--------------------------------------------------------------------------
| The founder-only command centre at /taurus. Unlike the original standalone
| kit, nothing here is authenticated by a static bearer token: the page and
| its endpoints sit behind the normal session guard plus the `super-admin`
| middleware, and the AI calls reuse the app's existing provider config in
| `config/services.php` ('ai_analysis'), so no API key ever reaches a browser.
|
| This file holds the judgement calls — which lead statuses count as which
| funnel stage, which expense categories are ad spend, who owns which number.
| Change them here rather than in the service.
|
| After editing: php artisan config:clear
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Funnel stages
    |--------------------------------------------------------------------------
    | Slugs from the `lead_statuses` table (created in the create_lead_statuses
    | migration: new, interested, follow_up, not_interested, invalid_number,
    | joined, lost). Stages are cumulative — a lead that reached "joined" also
    | counts as having been engaged — so each list contains the ones after it.
    |
    | The "masterclass" stage is not a status: it is `masterclass_link_sent_at`
    | on the lead, which is this CRM's equivalent of a demo being booked.
    */
    'funnel' => [
        'engaged' => ['interested', 'follow_up', 'joined'],
        'won' => ['joined'],
        'dead' => ['lost', 'not_interested', 'invalid_number'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ad spend
    |--------------------------------------------------------------------------
    | `expenses.category` is free text, so match case-insensitively on these
    | fragments to separate paid-marketing burn from everything else. Cost per
    | lead is that spend divided by leads created in the same window.
    */
    'ad_spend_categories' => ['ads', 'advertis', 'marketing', 'meta', 'google ads', 'campaign'],

    /*
    |--------------------------------------------------------------------------
    | Expense sentinel thresholds (days since a subscription was last used)
    |--------------------------------------------------------------------------
    */
    'sentinel' => [
        'waste_after_days' => 30,
        'watch_after_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Team signal thresholds (share of monthly target achieved)
    |--------------------------------------------------------------------------
    */
    'team_signal' => [
        'keep_at' => 0.9,
        'track_at' => 0.7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics a monthly target can be set against
    |--------------------------------------------------------------------------
    | Key => label. `actual` for each is computed live in TaurusSnapshotService
    | (see actualFor()); adding a key here without teaching that method how to
    | measure it will show the target with an actual of zero.
    |
    | Every metric here is attributable to one CRM user by a foreign key. That
    | rules out placement and revenue numbers: interview rounds, offers and
    | payments live in the LMS with no link back to the staff member who drove
    | them, so a per-person target on those would be a guess. They appear as
    | company-wide cards instead.
    */
    'target_metrics' => [
        'leads_added' => 'Leads added',
        'leads_converted' => 'Leads converted (joined)',
        'calls_made' => 'Calls made',
        'tasks_completed' => 'Tasks completed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Named owners shown on the metric cards
    |--------------------------------------------------------------------------
    | Each is a `users.id`. Left null, the card shows "Unassigned" rather than
    | inventing a name. Set them in .env once the owners are decided.
    */
    'owners' => [
        'revenue' => env('TAURUS_OWNER_REVENUE'),
        'expenses' => env('TAURUS_OWNER_EXPENSES'),
        'conversion' => env('TAURUS_OWNER_CONVERSION'),
        'attendance' => env('TAURUS_OWNER_ATTENDANCE'),
        'ads' => env('TAURUS_OWNER_ADS'),
        'mocks' => env('TAURUS_OWNER_MOCKS'),
        'interviews' => env('TAURUS_OWNER_INTERVIEWS'),
        'offers' => env('TAURUS_OWNER_OFFERS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent
    |--------------------------------------------------------------------------
    | Business context handed to the model with every snapshot, plus how long
    | a snapshot may be reused between the page load and a follow-up question.
    */
    'agent' => [
        'company' => env('TAURUS_COMPANY', 'browsejobs.ai — IT training & placement, pay-after-placement, Bengaluru'),
        'snapshot_cache_seconds' => 60,
    ],
];
