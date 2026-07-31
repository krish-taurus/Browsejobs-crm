<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * The controller's avg-response query is raw MySQL (TIMESTAMPDIFF), so the page
 * cannot be hit under the SQLite test database. This renders the Blade template
 * directly with representative rows, which is what the redesign changed.
 */
it('renders the redesigned HR performance template', function () {
    $row = function (string $name, string $role, array $m) {
        return (object) array_merge([
            'user' => (object) ['id' => 1, 'full_name' => $name, 'first_name' => $name,
                'role' => (object) ['role_name' => $role]],
        ], $m);
    };

    $rows = collect([
        $row('Madusmitha Choudhary', 'HR - Team Lead', ['assigned' => 4, 'followed_up' => 4, 'pending' => 0,
            'overdue' => 0, 'calls' => 4, 'interested' => 1, 'joined' => 2,
            'avg_response_minutes' => 132, 'last_activity' => Carbon::now()->subHours(23)]),
        $row('Barika Shirisha', 'HR', ['assigned' => 3, 'followed_up' => 2, 'pending' => 1,
            'overdue' => 1, 'calls' => 2, 'interested' => 1, 'joined' => 2,
            'avg_response_minutes' => 1452, 'last_activity' => Carbon::now()->subDays(2)]),
        // No activity at all — the null branches of every column.
        $row('Never Touched', 'HR', ['assigned' => 0, 'followed_up' => 0, 'pending' => 0,
            'overdue' => 0, 'calls' => 0, 'interested' => 0, 'joined' => 0,
            'avg_response_minutes' => null, 'last_activity' => null]),
    ]);

    $html = view('leads.performance', ['rows' => $rows, 'from' => null, 'to' => null])->render();

    expect($html)->toContain('Team leaderboard')
        ->and($html)->toContain('Madusmitha Choudhary')
        ->and($html)->toContain('perf-table')
        ->and($html)->toContain('No activity yet')     // null last_activity
        ->and($html)->toContain('2.2 h')               // 132 min formatted
        ->and($html)->toContain('24.2 h')              // 1452 min formatted
        ->and($html)->toContain('MC')                  // avatar initials
        ->and($html)->not->toContain('from now');      // never a future date
});
