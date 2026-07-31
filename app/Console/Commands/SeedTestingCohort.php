<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * End-to-end demo cohort: 50 leads (every name ends "Testing") walked through
 * the whole business — HR assignment, AI + manual calls, the status funnel,
 * enrolment into a dedicated test batch, fee plans/instalments/payments,
 * live classes with attendance, assignments with grades, mock interviews and
 * certificates — so every CRM dashboard can be checked against known numbers.
 *
 * SAFETY: this writes rows directly and never goes through LeadService, so no
 * queued job, AI phone call, staff WhatsApp or email is ever dispatched. No
 * lead is left in "New", which is the only status leads:auto-call picks up.
 *
 * Everything it creates is tagged and removable with --rollback.
 */
class SeedTestingCohort extends Command
{
    protected $signature = 'demo:testing-cohort
        {--rollback : Delete everything this command created and stop}
        {--leads=50 : How many leads to create}';

    protected $description = 'Seed (or remove) a full end-to-end demo cohort of "Testing" leads.';

    /** Every name ends with this, and it is how rollback finds its own rows. */
    private const NAME_SUFFIX = ' Testing';

    private const EMAIL_DOMAIN = '@cohort-testing.local';

    /** Batch numbers this command owns. */
    private const BATCH_PREFIX = 'TEST-';

    /**
     * Every seeded Pulse/Content Hub link carries this query marker, so
     * rollback can find its own rows without touching anything a human
     * curated by hand.
     */
    private const LINK_MARKER = 'src=testing-cohort';

    /**
     * Values for columns the LMS casts to a PHP enum. Writing anything else
     * puts a row in the database that explodes with a ValueError the moment the
     * LMS loads it ("released is not a valid backing value for GradeStatus").
     * SeedTestingCohortEnumsTest checks every one of these against the enum
     * files in apps/api/app/Enums, so a renamed case fails the build instead of
     * a page. Keep them in step.
     */
    private const ENUM_VALUES = [
        'BatchType' => 'paid',                    // batches.type
        'BatchMemberStatus' => 'enrolled',        // batch_members.status
        'EnrolmentType' => 'live',                // batch_members.enrolment_type
        'FeePlanType' => 'emi',                   // fee_plans.type (instalments)
        'FeePlanTypeSingle' => 'single',          // fee_plans.type (paid up front)
        'FeePlanStatus' => 'active',              // fee_plans.status
        'FeePlanStatusPaid' => 'paid',            // fee_plans.status once settled
        'InstalmentStatus' => 'pending',          // instalments.status
        'InstalmentStatusPaid' => 'paid',         // instalments.status
        'PaymentStatus' => 'captured',            // payments.status
        'LiveSessionStatus' => 'scheduled',       // live_sessions.status
        'LiveSessionStatusEnded' => 'ended',      // live_sessions.status
        'AssignmentSubmissionStatus' => 'submitted', // assignment_submissions.status
        'GradeStatus' => 'draft',                 // assignment_grades.status — trainer only
        'GradeStatusApproved' => 'approved',      // assignment_grades.status — released to student
        'AccessBlockLevel' => 'hard',             // access_blocks.level
    ];

    private int $tenantId = 1;

    public function handle(): int
    {
        $this->tenantId = (int) (DB::connection('lms')->table('tenants')->min('id') ?? 1);

        if ($this->option('rollback')) {
            return $this->rollback();
        }

        $this->assertNoOutboundWillFire();

        $count = max(1, (int) $this->option('leads'));

        // Callers only — an HR Manager supervises the board, they are never
        // assigned leads, so seeding any onto them would skew the numbers.
        $hrUsers = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereIn('roles.role_code', ['HR', 'HR_TEAM_LEAD', 'HR_ADMIN'])
            ->where('users.is_active', true)
            ->orderBy('users.id')
            ->pluck('users.id')
            ->all();

        if ($hrUsers === []) {
            $this->error('No active lead-calling HR users to assign leads to.');

            return self::FAILURE;
        }

        $statuses = DB::table('lead_statuses')->pluck('id', 'slug');
        foreach (['new', 'interested', 'follow_up', 'not_interested', 'invalid_number', 'joined', 'lost'] as $slug) {
            if (! isset($statuses[$slug])) {
                $this->error("Missing lead status '{$slug}'.");

                return self::FAILURE;
            }
        }

        $this->info("Seeding {$count} leads + full lifecycle (no outbound calls or messages)…");

        $leads = $this->seedLeads($count, $hrUsers, $statuses);
        $this->line('  leads created: '.count($leads));

        $joined = array_values(array_filter($leads, fn ($l) => $l['outcome'] === 'joined'));
        $this->line('  joined (become students): '.count($joined));

        $this->seedLmsLifecycle($joined);

        $this->newLine();
        $this->info('Done. Run with --rollback to remove every row this created.');
        $this->summary();

        return self::SUCCESS;
    }

    /**
     * Hard stop if anything in this process could dial or message. We bypass
     * LeadService entirely, but a future edit must not quietly regain the
     * ability to fan out 50 live calls.
     */
    private function assertNoOutboundWillFire(): void
    {
        if (DB::table('jobs')->count() > 0) {
            $this->warn('Note: the queue already has jobs pending; this command adds none.');
        }
    }

    /**
     * The CRM funnel. Distribution is deliberately uneven so the dashboards
     * show real spread rather than 50 identical rows:
     *   invalid number / not interested / follow-up / interested / joined,
     * plus a slice deliberately left un-followed-up to light up "Overdue".
     *
     * @param  array<int, int>  $hrUsers
     * @return array<int, array{id: int, name: string, outcome: string, course: string}>
     */
    private function seedLeads(int $count, array $hrUsers, $statuses): array
    {
        $firstNames = ['Aarav', 'Diya', 'Vihaan', 'Ananya', 'Arjun', 'Ishita', 'Kabir', 'Meera', 'Rohan', 'Sana',
            'Aditya', 'Nisha', 'Karthik', 'Priya', 'Rahul', 'Sneha', 'Vikram', 'Tara', 'Manish', 'Kavya',
            'Imran', 'Fatima', 'Nikhil', 'Riya', 'Sameer'];

        $courses = ['data-engineering', 'devops-cloud', 'python-backend', 'data-analytics'];
        $sources = ['Website', 'Meta Ads', 'Google Ads', 'Referral', 'Masterclass'];

        // Funnel shape, in order of assignment.
        $plan = array_merge(
            array_fill(0, 5, 'invalid_number'),
            array_fill(0, 7, 'not_interested'),
            array_fill(0, 6, 'follow_up'),
            array_fill(0, 8, 'interested'),
            array_fill(0, 24, 'joined'),
        );
        // Stretch or trim to the requested count.
        while (count($plan) < $count) {
            $plan[] = 'joined';
        }
        $plan = array_slice($plan, 0, $count);

        $leads = [];

        DB::transaction(function () use ($count, $hrUsers, $statuses, $firstNames, $courses, $sources, $plan, &$leads) {
            foreach (range(0, $count - 1) as $i) {
                $outcome = $plan[$i];
                $name = $firstNames[$i % count($firstNames)].' '.chr(65 + intdiv($i, count($firstNames))).self::NAME_SUFFIX;
                $course = $courses[$i % count($courses)];

                // Spread creation over the last 21 days so date filters have range.
                $createdAt = Carbon::now()->subDays(21 - intdiv($i * 21, $count))->setTime(9 + ($i % 9), ($i * 7) % 60);
                $assignedAt = (clone $createdAt)->addMinutes(3 + ($i % 25));
                $hrId = $hrUsers[$i % count($hrUsers)];

                $leadId = DB::table('leads')->insertGetId([
                    'name' => $name,
                    'mobile' => '9'.str_pad((string) (100000000 + $i), 9, '0', STR_PAD_LEFT),
                    'email' => strtolower(str_replace(' ', '.', $name)).self::EMAIL_DOMAIN,
                    'source' => $sources[$i % count($sources)],
                    'campaign_name' => 'Testing Cohort',
                    'interested_course_slug' => $course,
                    'added_by_user_id' => 1,
                    'current_status_id' => $statuses['new'],
                    'assigned_to_user_id' => $hrId,
                    'assigned_by_user_id' => 1,
                    'assigned_at' => $assignedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                DB::table('lead_assignments')->insert([
                    'lead_id' => $leadId, 'assigned_to_user_id' => $hrId, 'assigned_by_user_id' => 1,
                    'assigned_at' => $assignedAt, 'created_at' => $assignedAt, 'updated_at' => $assignedAt,
                ]);

                // Step 1 — the automatic AI call, recorded exactly as the
                // webhook would have written it (never actually dialled).
                $this->recordAiCall($leadId, $outcome, $createdAt);

                // Step 2 — status history. "New" is always the first entry.
                $this->recordStatus($leadId, $statuses['new'], null, $createdAt, 'Lead captured');

                // 1 lead in 6 is left untouched after assignment: no call, no
                // status move. That is what "Pending" and "Overdue" measure.
                $neglected = $i % 6 === 0 && $outcome !== 'joined';

                if (! $neglected) {
                    $this->walkFunnel($leadId, $hrId, $outcome, $statuses, $assignedAt, $i);
                }

                $leads[] = ['id' => $leadId, 'name' => $name, 'outcome' => $neglected ? 'new' : $outcome, 'course' => $course];
            }
        });

        // Nothing may be left in "New" — that is the only status the live
        // leads:auto-call safety net dials.
        $this->parkNeglectedLeads($statuses);

        return $leads;
    }

    /** Move the untouched leads out of "New" so no live auto-call can pick them up. */
    private function parkNeglectedLeads($statuses): void
    {
        $parked = DB::table('leads')
            ->where('name', 'like', '%'.self::NAME_SUFFIX)
            ->where('current_status_id', $statuses['new'])
            ->update(['current_status_id' => $statuses['follow_up']]);

        if ($parked > 0) {
            $this->line("  parked {$parked} un-actioned leads in Follow-up (never left in New — auto-call safety)");
        }
    }

    /** The HR's own actions after assignment: manual call, then a status move. */
    private function walkFunnel(int $leadId, int $hrId, string $outcome, $statuses, Carbon $assignedAt, int $i): void
    {
        // Response time varies from 6 minutes to ~2 days so the "avg response"
        // and "overdue" columns have a real spread. Clamped to the past: a
        // recently-created lead plus a 2-day delay would otherwise land in the
        // future and the board would read "last activity: 1 day from now".
        $respondedAt = (clone $assignedAt)->addMinutes([6, 18, 45, 140, 320, 1500, 2900][$i % 7]);
        if ($respondedAt->isFuture()) {
            $respondedAt = Carbon::now()->subMinutes(5 + ($i % 90));
        }

        DB::table('lead_calls')->insert([
            'lead_id' => $leadId,
            'type' => 'manual',
            'status' => 'completed',
            'disposition' => $outcome === 'joined' ? 'enrolled' : $outcome,
            'duration_seconds' => 120 + ($i * 13) % 600,
            'to_number' => '9'.str_pad((string) (100000000 + $i), 9, '0', STR_PAD_LEFT),
            'initiated_by_user_id' => $hrId,
            'started_at' => $respondedAt,
            'ended_at' => (clone $respondedAt)->addMinutes(5),
            'created_at' => $respondedAt,
            'updated_at' => $respondedAt,
        ]);

        $path = match ($outcome) {
            'joined' => ['interested', 'joined'],
            'interested' => ['interested'],
            'follow_up' => ['interested', 'follow_up'],
            default => [$outcome],
        };

        // Each later step in the path lands 20h after the previous one, but
        // never in the future — a lead assigned this morning must not show a
        // "Joined" event dated tomorrow.
        $at = clone $respondedAt;
        foreach ($path as $slug) {
            if ($at->isFuture()) {
                $at = Carbon::now()->subMinutes(2);
            }
            $this->recordStatus($leadId, $statuses[$slug], $hrId, $at, 'Updated by HR after call');
            $at = (clone $at)->addHours(20);
        }

        DB::table('leads')->where('id', $leadId)->update([
            'current_status_id' => $statuses[end($path)],
            'masterclass_link_sent_at' => in_array($outcome, ['interested', 'follow_up', 'joined'], true) ? $respondedAt : null,
            'updated_at' => $at,
        ]);
    }

    private function recordAiCall(int $leadId, string $outcome, Carbon $createdAt): void
    {
        $startedAt = (clone $createdAt)->addMinutes(2);

        [$status, $disposition, $sentiment, $seconds] = match ($outcome) {
            'invalid_number' => ['failed', 'invalid_number', null, 0],
            'not_interested' => ['completed', 'not_interested', 'negative', 48],
            'joined' => ['completed', 'interested', 'positive', 214],
            'interested' => ['completed', 'interested', 'positive', 168],
            default => ['no_answer', 'no_answer', null, 0],
        };

        DB::table('lead_calls')->insert([
            'lead_id' => $leadId,
            'type' => 'ai',
            'provider' => 'caller_digital',
            'external_campaign_id' => 'TESTCOHORT-'.$leadId,
            'status' => $status,
            'disposition' => $disposition,
            'sentiment' => $sentiment,
            'duration_seconds' => $seconds,
            'transcript' => $seconds > 0 ? 'Simulated transcript for the testing cohort — no call was placed.' : null,
            'language' => 'english',
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->addSeconds(max($seconds, 1)),
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);
    }

    private function recordStatus(int $leadId, int $statusId, ?int $userId, Carbon $at, string $remarks): void
    {
        DB::table('lead_status_history')->insert([
            'lead_id' => $leadId,
            'status_id' => $statusId,
            'changed_by_user_id' => $userId,
            'remarks' => $remarks,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /**
     * Everything after "Joined": student account, batch, money, classes,
     * attendance, assignments, mocks and certificates.
     *
     * @param  array<int, array{id: int, name: string, outcome: string, course: string}>  $joined
     */
    private function seedLmsLifecycle(array $joined): void
    {
        if ($joined === []) {
            return;
        }

        $lms = DB::connection('lms');

        $lms->transaction(function () use ($lms, $joined) {
            $courseId = (int) $lms->table('courses')->where('slug', 'data-engineering')->value('id');
            $trainerId = (int) ($lms->table('users')->where('user_type', 'staff')->orderBy('id')->value('id') ?? 1);

            // One dedicated batch, already running: started 5 weeks ago, ends
            // in 7 weeks — so past classes have attendance and future ones are
            // still scheduled.
            $startsOn = Carbon::today()->subWeeks(5);
            $batchId = $lms->table('batches')->insertGetId([
                'tenant_id' => $this->tenantId,
                'course_id' => $courseId,
                'number' => self::BATCH_PREFIX.'DE-'.Carbon::now()->format('Ym'),
                // Must match what the app understands: Live Batches only lists
                // type paid|bootcamp, and counts status 'active'.
                'type' => self::ENUM_VALUES['BatchType'],
                'capacity' => 60,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => Carbon::today()->addWeeks(7)->toDateString(),
                'status' => 'active',
                'trainer_id' => $trainerId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->line("  batch created: #{$batchId} (".self::BATCH_PREFIX.'DE-'.Carbon::now()->format('Ym').')');

            $sessionIds = $this->seedSessions($lms, $batchId, $trainerId, $startsOn);
            $this->line('  live classes: '.count($sessionIds).' (past = held, future = scheduled)');

            $assignmentId = $this->seedAssignment($lms);

            $studentIds = [];
            foreach ($joined as $i => $lead) {
                $studentId = $this->seedStudent($lms, $lead, $i);
                $studentIds[] = $studentId;

                // 'enrolled' is what the Enrolled Students counter and the
                // roster filter on; 'live' is the enrolment type real members use.
                $lms->table('batch_members')->insert([
                    'tenant_id' => $this->tenantId, 'batch_id' => $batchId, 'user_id' => $studentId,
                    'status' => self::ENUM_VALUES['BatchMemberStatus'], 'enrolment_type' => self::ENUM_VALUES['EnrolmentType'],
                    'enrolled_at' => $startsOn, 'created_at' => $startsOn, 'updated_at' => $startsOn,
                ]);

                DB::table('leads')->where('id', $lead['id'])->update([
                    'allocated_batch_number' => self::BATCH_PREFIX.'DE-'.Carbon::now()->format('Ym'),
                ]);

                // The CRM's own Students page reads the local `students` table
                // (normally synced from TaurusAI), NOT the LMS — so the cohort
                // has to land there too or it only shows on the batch roster.
                $this->seedCrmStudent($lead, $i);

                $this->seedMoney($lms, $studentId, $batchId, $i, $startsOn);
                $this->seedAttendance($lms, $sessionIds, $studentId, $i);
                $this->seedCoursework($lms, $assignmentId, $studentId, $trainerId, $i);
            }

            $this->line('  students enrolled: '.count($studentIds));
            $this->seedCertificates($lms, $studentIds, $courseId);
            $this->seedPulse($lms, $studentIds);
        });
    }

    /** @return array<int, array{id: int, start: Carbon, past: bool}> */
    private function seedSessions($lms, int $batchId, int $trainerId, Carbon $startsOn): array
    {
        $sessions = [];
        // 16 classes, twice a week from the start date.
        foreach (range(0, 15) as $n) {
            $start = (clone $startsOn)->addDays($n * 3)->setTime(19, 0);
            $past = $start->isPast();

            $sessions[] = [
                'id' => $lms->table('live_sessions')->insertGetId([
                    'tenant_id' => $this->tenantId,
                    'batch_id' => $batchId,
                    'kind' => 'class',
                    'host_user_id' => $trainerId,
                    'title' => 'Class '.($n + 1).' — Testing Cohort',
                    'scheduled_start' => $start,
                    'scheduled_end' => (clone $start)->addHours(2),
                    'status' => $past ? 'ended' : 'scheduled',
                    'wrapped_up_at' => $past ? (clone $start)->addHours(2) : null,
                    'created_at' => $startsOn, 'updated_at' => $startsOn,
                ]),
                'start' => $start,
                'past' => $past,
            ];
        }

        return $sessions;
    }

    /** Mirror of the joined lead in the CRM's own students table. */
    private function seedCrmStudent(array $lead, int $i): void
    {
        $parts = explode(' ', $lead['name']);

        DB::table('students')->insert([
            'external_id' => 'TESTCOHORT-'.$i,
            'first_name' => $parts[0],
            'last_name' => trim(implode(' ', array_slice($parts, 1))),
            'full_name' => $lead['name'],
            'email' => strtolower(str_replace(' ', '.', $lead['name'])).self::EMAIL_DOMAIN,
            'phone_number' => '9'.str_pad((string) (100000000 + $i), 9, '0', STR_PAD_LEFT),
            'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedStudent($lms, array $lead, int $i): int
    {
        return $lms->table('users')->insertGetId([
            'tenant_id' => $this->tenantId,
            'name' => $lead['name'],
            'email' => strtolower(str_replace(' ', '.', $lead['name'])).self::EMAIL_DOMAIN,
            'phone' => '9'.str_pad((string) (100000000 + $i), 9, '0', STR_PAD_LEFT),
            'user_type' => 'student',
            'is_active' => 1,
            'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Fee plan + instalments + payments. Four money states so the Fee
     * Collection report shows every bucket: fully paid, on track, due soon,
     * and overdue (with a hard access block, as the real dunning does).
     */
    private function seedMoney($lms, int $studentId, int $batchId, int $i, Carbon $startsOn): void
    {
        $total = 3000000; // ₹30,000 in paise
        $bucket = $i % 4;  // 0 paid in full, 1 on track, 2 due soon, 3 overdue

        $planId = $lms->table('fee_plans')->insertGetId([
            'tenant_id' => $this->tenantId,
            'user_id' => $studentId,
            'batch_id' => $batchId,
            // FeePlanType is single|emi — there is no "full".
            'type' => $bucket === 0 ? self::ENUM_VALUES['FeePlanTypeSingle'] : self::ENUM_VALUES['FeePlanType'],
            'total_paise' => $total,
            'discount_paise' => 0,
            'gst_rate_bps' => 1800,
            'currency' => 'INR',
            'status' => $bucket === 0 ? self::ENUM_VALUES['FeePlanStatusPaid'] : self::ENUM_VALUES['FeePlanStatus'],
            'created_by' => 1,
            'created_at' => $startsOn, 'updated_at' => $startsOn,
        ]);

        $instalments = $bucket === 0
            ? [[$total, $startsOn, true]]
            : [
                [1000000, (clone $startsOn), true],
                [1000000, (clone $startsOn)->addDays(30), $bucket !== 3],
                [1000000, (clone $startsOn)->addDays(60), false],
            ];

        foreach ($instalments as $seq => [$amount, $dueOn, $paid]) {
            // The overdue bucket's 2nd instalment is pushed into the past.
            $due = $bucket === 3 && $seq === 1 ? Carbon::today()->subDays(12) : $dueOn;

            $instalmentId = $lms->table('instalments')->insertGetId([
                'tenant_id' => $this->tenantId,
                'fee_plan_id' => $planId,
                'seq' => $seq + 1,
                'amount_paise' => $amount,
                'due_on' => $due->toDateString(),
                'status' => $paid ? 'paid' : 'pending',
                'paid_at' => $paid ? $due : null,
                'created_at' => $startsOn, 'updated_at' => $startsOn,
            ]);

            if ($paid) {
                $lms->table('payments')->insert([
                    'tenant_id' => $this->tenantId,
                    'fee_plan_id' => $planId,
                    'instalment_id' => $instalmentId,
                    'razorpay_order_id' => 'order_TEST'.$instalmentId,
                    'razorpay_payment_id' => 'pay_TEST'.$instalmentId,
                    'amount_paise' => $amount,
                    'status' => self::ENUM_VALUES['PaymentStatus'],
                    'method' => ['upi', 'card', 'netbanking'][$seq % 3],
                    'captured_at' => $due,
                    'created_at' => $due, 'updated_at' => $due,
                ]);
            }
        }

        // Overdue students get the hard block the real dunning flow applies.
        if ($bucket === 3) {
            $lms->table('access_blocks')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $studentId,
                'batch_id' => $batchId,
                'fee_plan_id' => $planId,
                'level' => self::ENUM_VALUES['AccessBlockLevel'],
                'reason' => 'Instalment 2 overdue by 12 days',
                'blocked_at' => Carbon::today()->subDays(5),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** @param array<int, array{id: int, start: Carbon, past: bool}> $sessions */
    private function seedAttendance($lms, array $sessions, int $studentId, int $i): void
    {
        // Attendance rates from 100% down to ~55% so the report has laggards.
        $rate = [100, 95, 88, 74, 61][$i % 5];

        foreach ($sessions as $n => $session) {
            if (! $session['past']) {
                continue;
            }
            // Deterministic "did they attend" spread matching the rate.
            if ((($n * 37 + $i * 11) % 100) >= $rate) {
                continue;
            }

            $joinedAt = (clone $session['start'])->addMinutes(($n + $i) % 12);
            $pct = max(40, $rate - (($n * 3) % 20));

            $lms->table('attendance')->insert([
                'tenant_id' => $this->tenantId,
                'live_session_id' => $session['id'],
                'user_id' => $studentId,
                'first_joined_at' => $joinedAt,
                'last_left_at' => (clone $session['start'])->addHours(2),
                'total_seconds' => (int) (7200 * $pct / 100),
                'attended_pct' => $pct,
                'is_late' => $joinedAt->diffInMinutes($session['start']) > 5 ? 1 : 0,
                'created_at' => $session['start'], 'updated_at' => $session['start'],
            ]);
        }
    }

    private function seedAssignment($lms): int
    {
        $lessonId = (int) ($lms->table('lessons')->orderBy('id')->value('id') ?? 0);

        return $lms->table('assignments')->insertGetId([
            'tenant_id' => $this->tenantId,
            'lesson_id' => $lessonId ?: null,
            'title' => 'Capstone pipeline — Testing Cohort',
            'instructions' => 'Build an end-to-end ingestion pipeline and submit the repo link.',
            'max_points' => 100,
            'allow_link' => 1,
            'allow_file' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Submission + grade + a mock interview score for each student. */
    private function seedCoursework($lms, int $assignmentId, int $studentId, int $trainerId, int $i): void
    {
        // 1 in 6 never submits, so the grading queue shows genuine gaps.
        if ($i % 6 === 5) {
            return;
        }

        $submittedAt = Carbon::now()->subDays(9 - ($i % 7));

        $submissionId = $lms->table('assignment_submissions')->insertGetId([
            'tenant_id' => $this->tenantId,
            'assignment_id' => $assignmentId,
            'user_id' => $studentId,
            'body' => 'Testing cohort submission.',
            'link' => 'https://example.invalid/testing-cohort/'.$studentId,
            'status' => self::ENUM_VALUES['AssignmentSubmissionStatus'],
            'submitted_at' => $submittedAt,
            'created_at' => $submittedAt, 'updated_at' => $submittedAt,
        ]);

        // 1 in 4 is left ungraded — that is the trainer's pending queue.
        if ($i % 4 === 3) {
            return;
        }

        $score = [92, 78, 65, 84, 71, 58][$i % 6];

        // A grade is Draft (trainer-only) until it is Approved, which is what
        // the Grading page calls "released". Roughly a third stay in draft so
        // the "Awaiting Release" figure is real and the Release button has
        // something to act on.
        $released = $i % 3 !== 0;

        $lms->table('assignment_grades')->insert([
            'tenant_id' => $this->tenantId,
            'submission_id' => $submissionId,
            'status' => $released ? self::ENUM_VALUES['GradeStatusApproved'] : self::ENUM_VALUES['GradeStatus'],
            'score' => $score,
            'max_points' => 100,
            'feedback' => 'Testing cohort — auto-generated feedback.',
            'graded_by' => $trainerId,
            'approved_at' => $released ? (clone $submittedAt)->addDays(2) : null,
            'created_at' => $submittedAt, 'updated_at' => $submittedAt,
        ]);
    }

    /** Certificates for the students who both paid up and attended well. */
    private function seedCertificates($lms, array $studentIds, int $courseId): void
    {
        $issued = 0;
        foreach ($studentIds as $i => $studentId) {
            if ($i % 4 !== 0) {   // the fully-paid bucket
                continue;
            }

            $lms->table('certificates')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $studentId,
                'course_id' => $courseId,
                'code' => 'TESTCERT-'.$studentId,
                'number' => 'TEST/'.Carbon::now()->format('Y').'/'.str_pad((string) $studentId, 5, '0', STR_PAD_LEFT),
                'title' => 'Data Engineering — Testing Cohort',
                'issued_at' => Carbon::today()->subDays(3),
                'status' => 'issued',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $issued++;
        }

        $this->line("  certificates issued: {$issued}");
    }

    /**
     * The student Pulse page: curated Market Pulse sources + the digest built
     * from them, celebration-wall wins, and Content Hub releases. Without
     * these three the page renders three empty states, which is exactly what
     * it does on a fresh install.
     *
     * @param  array<int, int>  $studentIds
     */
    private function seedPulse($lms, array $studentIds): void
    {
        $sources = [
            ['Databricks buys a streaming startup to close the real-time gap', 'The Register', 'data-engineering', 'Streaming is now table stakes in DE interviews.', 0],
            ['Kubernetes 1.34 makes sidecar containers stable', 'InfoWorld', 'devops-cloud', 'Expect questions on sidecar lifecycle.', 0],
            ['India adds 41,000 analytics roles in Q3', 'Economic Times', 'data-analytics', 'Hiring is up in Bengaluru and Pune especially.', 1],
            ['FastAPI overtakes Flask in new backend projects', 'JetBrains survey', 'python-backend', 'Async-first is the default now.', 1],
            ['dbt Labs ships a native semantic layer', 'Datanami', 'data-engineering', null, 2],
        ];

        $itemIds = [];
        foreach ($sources as $i => [$title, $source, $slug, $note, $daysAgo]) {
            $itemIds[] = $lms->table('pulse_items')->insertGetId([
                'tenant_id' => $this->tenantId,
                'title' => $title,
                'url' => 'https://example.invalid/pulse/'.($i + 1).'?'.self::LINK_MARKER,
                'source_name' => $source,
                'course_slug' => $slug,
                'note' => $note,
                'published_on' => Carbon::today()->subDays($daysAgo)->toDateString(),
                'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->line('  market pulse sources: '.count($itemIds));

        // The digest itself. The LMS command narrates it with AI; if that is
        // unavailable we still want the page populated, so fall back to the
        // same source-attributed summary shape the LMS uses.
        $lms->table('pulse_digests')->updateOrInsert(
            ['tenant_id' => $this->tenantId, 'digest_date' => Carbon::today()->toDateString()],
            [
                'narrative' => 'Three themes for your batch today. Streaming and the semantic layer keep showing up in data-engineering interviews, so the real-time module is worth a second pass. On the platform side, stable sidecars in Kubernetes 1.34 change how you explain pod lifecycle. And analytics hiring in India is up sharply this quarter — good timing if you are close to interview-ready.',
                'item_ids' => json_encode($itemIds),
                'source' => 'seeded',
                'created_at' => now(), 'updated_at' => now(),
            ],
        );
        $this->line('  market pulse digest: built for today');

        // Celebration wall — real cohort students, consented, published.
        $wins = [
            ['Data Engineer', 'Nimbus Data', 'named'],
            ['DevOps Engineer', 'Vertex Cloud', 'named'],
            ['Analytics Engineer', 'Cobalt Systems', 'anonymous'],
        ];
        $published = 0;
        foreach ($wins as $i => [$role, $company, $mode]) {
            if (! isset($studentIds[$i])) {
                break;
            }
            $lms->table('celebrations')->insert([
                'tenant_id' => $this->tenantId,
                'student_id' => $studentIds[$i],
                'display_mode' => $mode,
                'anonymous_label' => $mode === 'anonymous' ? 'A Data Engineering student' : null,
                'role_title' => $role,
                'company' => $company,
                'consented_at' => now()->subDays(3 + $i),
                'published_at' => now()->subDays(2 + $i),
                'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $published++;
        }
        $this->line("  celebration wall: {$published} wins published");

        $releases = [
            ['podcast', 'Ep 12 — From bank teller to analytics engineer', 0],
            ['youtube', 'SQL window functions, the interview version', 2],
            ['instagram', 'Batch DE-2026-07 placement day', 5],
        ];
        foreach ($releases as $i => [$kind, $title, $daysAgo]) {
            $lms->table('content_hub_items')->insert([
                'tenant_id' => $this->tenantId,
                'kind' => $kind,
                'title' => $title,
                'url' => 'https://example.invalid/content/'.($i + 1).'?'.self::LINK_MARKER,
                'source' => 'manual',
                'published_at' => now()->subDays($daysAgo),
                'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->line('  content hub: '.count($releases).' releases');
    }

    /** Remove every row this command created, in dependency order. */
    private function rollback(): int
    {
        $lms = DB::connection('lms');

        // Match on the name marker as well as the email: the LMS runs its own
        // queue against this database and has been observed rewriting a seeded
        // student's email, leaving an orphan row that then collided on the
        // tenant+phone unique index the next time this command ran.
        $studentIds = $lms->table('users')
            ->where('user_type', 'student')
            ->where(function ($q) {
                $q->where('email', 'like', '%'.self::EMAIL_DOMAIN)
                    ->orWhere('name', 'like', '%'.self::NAME_SUFFIX);
            })
            ->pluck('id');
        $batchIds = $lms->table('batches')->where('number', 'like', self::BATCH_PREFIX.'%')->pluck('id');
        $planIds = $lms->table('fee_plans')->whereIn('user_id', $studentIds)->pluck('id');
        $instalmentIds = $lms->table('instalments')->whereIn('fee_plan_id', $planIds)->pluck('id');
        $sessionIds = $lms->table('live_sessions')->whereIn('batch_id', $batchIds)->pluck('id');
        $assignmentIds = $lms->table('assignments')->where('title', 'like', '%Testing Cohort%')->pluck('id');
        $submissionIds = $lms->table('assignment_submissions')->whereIn('assignment_id', $assignmentIds)->pluck('id');

        // Pulse rows are found by the link marker (never by title), so anything
        // curated by hand from the CRM survives a rollback.
        $pulseItemIds = $lms->table('pulse_items')->where('url', 'like', '%'.self::LINK_MARKER.'%')->pluck('id');

        $lms->transaction(function () use ($lms, $studentIds, $batchIds, $planIds, $instalmentIds, $sessionIds, $assignmentIds, $submissionIds, $pulseItemIds) {
            // A digest is only ours if every source it cites is one of ours.
            foreach ($lms->table('pulse_digests')->get(['id', 'item_ids']) as $digest) {
                $cited = (array) json_decode((string) $digest->item_ids, true);
                if ($cited !== [] && count(array_diff($cited, $pulseItemIds->all())) === 0) {
                    $lms->table('pulse_digests')->where('id', $digest->id)->delete();
                }
            }
            $lms->table('pulse_items')->whereIn('id', $pulseItemIds)->delete();
            $lms->table('content_hub_items')->where('url', 'like', '%'.self::LINK_MARKER.'%')->delete();
            $lms->table('celebration_guidances')->whereIn('celebration_id',
                $lms->table('celebrations')->whereIn('student_id', $studentIds)->pluck('id'))->delete();
            $lms->table('celebrations')->whereIn('student_id', $studentIds)->delete();

            $lms->table('certificates')->whereIn('user_id', $studentIds)->delete();
            $lms->table('assignment_grades')->whereIn('submission_id', $submissionIds)->delete();
            $lms->table('assignment_submissions')->whereIn('id', $submissionIds)->delete();
            $lms->table('assignments')->whereIn('id', $assignmentIds)->delete();
            $lms->table('attendance')->whereIn('live_session_id', $sessionIds)->delete();
            $lms->table('live_sessions')->whereIn('id', $sessionIds)->delete();
            $lms->table('access_blocks')->whereIn('user_id', $studentIds)->delete();
            $lms->table('payments')->whereIn('instalment_id', $instalmentIds)->delete();
            $lms->table('instalments')->whereIn('id', $instalmentIds)->delete();
            $lms->table('fee_plans')->whereIn('id', $planIds)->delete();
            $lms->table('batch_members')->whereIn('user_id', $studentIds)->delete();
            $lms->table('batches')->whereIn('id', $batchIds)->delete();
            $lms->table('users')->whereIn('id', $studentIds)->delete();
        });

        $leadIds = DB::table('leads')->where('name', 'like', '%'.self::NAME_SUFFIX)->pluck('id');

        DB::transaction(function () use ($leadIds) {
            DB::table('students')->where('external_id', 'like', 'TESTCOHORT-%')->delete();
            DB::table('lead_status_history')->whereIn('lead_id', $leadIds)->delete();
            DB::table('lead_calls')->whereIn('lead_id', $leadIds)->delete();
            DB::table('lead_assignments')->whereIn('lead_id', $leadIds)->delete();
            DB::table('lead_notifications')->whereIn('lead_id', $leadIds)->delete();
            DB::table('lead_ai_analyses')->whereIn('lead_id', $leadIds)->delete();
            DB::table('leads')->whereIn('id', $leadIds)->delete();
        });

        $this->info('Rolled back: '.count($leadIds).' leads, '.count($studentIds).' students, '.count($batchIds).' batch(es).');

        return self::SUCCESS;
    }

    /** Print what the dashboards should now show. */
    private function summary(): void
    {
        $lms = DB::connection('lms');
        $leadIds = DB::table('leads')->where('name', 'like', '%'.self::NAME_SUFFIX)->pluck('id');
        $studentIds = $lms->table('users')->where('email', 'like', '%'.self::EMAIL_DOMAIN)->pluck('id');

        $byStatus = DB::table('leads')
            ->join('lead_statuses', 'lead_statuses.id', '=', 'leads.current_status_id')
            ->whereIn('leads.id', $leadIds)
            ->groupBy('lead_statuses.name')
            ->pluck(DB::raw('count(*)'), 'lead_statuses.name');

        $this->newLine();
        $this->table(['Funnel', 'Leads'], collect($byStatus)->map(fn ($c, $n) => [$n, $c])->values()->all());

        $paid = $lms->table('payments')
            ->whereIn('fee_plan_id', $lms->table('fee_plans')->whereIn('user_id', $studentIds)->pluck('id'))
            ->where('status', 'captured')->sum('amount_paise');

        $this->table(['Metric', 'Value'], [
            ['Leads created', count($leadIds)],
            ['Manual calls logged', DB::table('lead_calls')->whereIn('lead_id', $leadIds)->where('type', 'manual')->count()],
            ['AI calls recorded (simulated)', DB::table('lead_calls')->whereIn('lead_id', $leadIds)->where('type', 'ai')->count()],
            ['Students enrolled (LMS roster)', count($studentIds)],
            ['Students on the CRM Students page', DB::table('students')->where('external_id', 'like', 'TESTCOHORT-%')->count()],
            ['Attendance rows', $lms->table('attendance')->whereIn('user_id', $studentIds)->count()],
            ['Payments captured', '₹'.number_format($paid / 100)],
        ]);
    }
}
