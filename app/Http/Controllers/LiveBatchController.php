<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\AssignmentSubmission;
use App\Models\Lms\Attendance;
use App\Models\Lms\Batch;
use App\Models\Lms\BatchMember;
use App\Models\Lms\Certificate;
use App\Models\Lms\Course;
use App\Models\Lms\FeePlan;
use App\Models\Lms\LmsUser;
use App\Models\Lms\MentorProfile;
use App\Models\Lms\MockInterview;
use App\Models\Lms\Module;
use App\Models\Lms\QuizAttempt;
use App\Models\Lms\StudentScore;
use App\Models\Lms\Topic;
use App\Models\Lms\TopicCompletion;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveBatchController extends Controller
{
    use ReadsLmsDatabase;

    /** Batch types shown on the Live Batches page (masterclasses have their own page). */
    private const TYPES = ['paid', 'bootcamp'];

    public function __construct(private LmsCommandRunner $lms) {}

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $query = Batch::query()
                ->with(['course', 'trainer'])
                ->withCount(['members as students_count'])
                ->whereIn('type', self::TYPES);

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('course_id')) {
                $query->where('course_id', $request->input('course_id'));
            }

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('course', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            }

            $batches = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

            $courses = Course::orderBy('name')->get(['id', 'name', 'slug', 'fee_paise']);

            $totalBatches = Batch::whereIn('type', self::TYPES)->count();
            $activeBatches = Batch::whereIn('type', self::TYPES)->where('status', 'active')->count();
            $enrolledStudents = BatchMember::whereIn('batch_id', Batch::whereIn('type', self::TYPES)->select('id'))
                ->where('status', 'enrolled')
                ->distinct('user_id')
                ->count('user_id');

            return view('live-batches.index', compact(
                'batches', 'courses', 'totalBatches', 'activeBatches', 'enrolledStudents'
            ));
        });
    }

    public function show(Batch $batch): View
    {
        return $this->renderLmsPage(function () use ($batch) {
            $batch->load(['course', 'trainer', 'members.user', 'liveSessions' => fn ($q) => $q->orderByDesc('scheduled_start')]);

            $userIds = $batch->members->pluck('user_id')->all();
            $sessionIds = $batch->liveSessions->pluck('id')->all();

            $attendanceStats = empty($userIds) || empty($sessionIds) ? collect() : Attendance::query()
                ->whereIn('live_session_id', $sessionIds)
                ->whereIn('user_id', $userIds)
                ->selectRaw('user_id, COUNT(*) as attended_sessions, ROUND(AVG(attended_pct)) as avg_pct')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $quizStats = empty($userIds) ? collect() : QuizAttempt::query()
                ->whereIn('user_id', $userIds)
                ->selectRaw('user_id, COUNT(*) as attempts, ROUND(AVG(score_pct)) as avg_score')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $submissionCounts = empty($userIds) ? collect() : AssignmentSubmission::query()
                ->whereIn('user_id', $userIds)
                ->selectRaw('user_id, COUNT(*) as total')
                ->groupBy('user_id')
                ->pluck('total', 'user_id');

            $mockStats = empty($userIds) ? collect() : MockInterview::query()
                ->whereIn('user_id', $userIds)
                ->selectRaw('user_id, COUNT(*) as total, ROUND(AVG(overall_score)) as avg_score')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $latestScores = empty($userIds) ? collect() : StudentScore::query()
                ->whereIn('user_id', $userIds)
                ->orderByDesc('computed_at')
                ->get()
                ->unique('user_id')
                ->keyBy('user_id');

            $feePlans = empty($userIds) ? collect() : FeePlan::query()
                ->where('batch_id', $batch->id)
                ->whereIn('user_id', $userIds)
                ->with('instalments')
                ->get()
                ->keyBy('user_id');

            // Active mentors for the "Add a Class → Mentoring" host picker.
            $mentors = MentorProfile::query()
                ->where('is_active', true)
                ->with('user:id,name')
                ->get();

            return view('live-batches.show', compact(
                'batch', 'attendanceStats', 'quizStats', 'submissionCounts',
                'mockStats', 'latestScores', 'feePlans', 'mentors'
            ));
        });
    }

    /**
     * Create a batch (any type) inside the LMS from the CRM — for manual
     * cohorts opened outside the automated weekend funnel.
     */
    public function storeBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_slug' => 'required|string|max:255',
            'type' => 'required|in:masterclass,bootcamp,paid,self_paced',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'capacity' => 'nullable|integer|min:1',
            'number' => 'nullable|string|max:50',
        ]);

        $args = ['batch:create', $validated['course_slug'], $validated['type']];
        if (! empty($validated['starts_on'])) {
            $args[] = '--starts='.$validated['starts_on'];
        }
        if (! empty($validated['ends_on'])) {
            $args[] = '--ends='.$validated['ends_on'];
        }
        if (! empty($validated['capacity'])) {
            $args[] = '--capacity='.$validated['capacity'];
        }
        if (! empty($validated['number'])) {
            $args[] = '--number='.$validated['number'];
        }

        return $this->flashResult($this->lms->run($args), 'Batch created.');
    }

    /** Update a batch's capacity, dates, status or trainer inside the LMS. */
    public function updateBatch(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'capacity' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,completed,cancelled',
            'type' => 'nullable|in:masterclass,bootcamp,paid,self_paced',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date',
        ]);

        $args = ['batch:update', (string) $batch->id];
        if ($request->filled('capacity')) {
            $args[] = '--capacity='.$validated['capacity'];
        }
        if (! empty($validated['status'])) {
            $args[] = '--status='.$validated['status'];
        }
        if (! empty($validated['type'])) {
            $args[] = '--type='.$validated['type'];
        }
        if (! empty($validated['starts_on'])) {
            $args[] = '--starts='.$validated['starts_on'];
        }
        if (! empty($validated['ends_on'])) {
            $args[] = '--ends='.$validated['ends_on'];
        }

        return $this->flashResult($this->lms->run($args), 'Batch updated.');
    }

    /**
     * Add a student to the batch — the LMS finds or creates the account and
     * (optionally) sends the batch number + login credentials.
     */
    public function addStudent(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|required_without:email',
            'email' => 'nullable|email|max:255|required_without:phone',
            'status' => 'required|in:reserved,payment_pending,enrolled',
            'credentials' => 'nullable|boolean',
        ]);

        $args = ['batch:add-student', (string) $batch->id, $validated['name'], '--status='.$validated['status']];
        if (! empty($validated['phone'])) {
            $args[] = '--phone='.$validated['phone'];
        }
        if (! empty($validated['email'])) {
            $args[] = '--email='.$validated['email'];
        }
        if ($request->boolean('credentials')) {
            $args[] = '--credentials';
        }

        return $this->flashResult($this->lms->run($args), 'Student added to the batch.');
    }

    /** Drop a student from the batch (kept as `dropped` in the LMS, audited). */
    public function removeStudent(Request $request, Batch $batch, LmsUser $user): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:255']);

        $args = ['batch:remove-student', (string) $batch->id, (string) $user->id];
        if (! empty($validated['reason'])) {
            $args[] = '--reason='.$validated['reason'];
        }

        return $this->flashResult($this->lms->run($args), 'Student dropped from the batch.');
    }

    /** Change a member's enrolment status (reserved → enrolled, paused, …). */
    public function memberStatus(Request $request, Batch $batch, LmsUser $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:reserved,payment_pending,enrolled,dropped,transferred,completed,paused',
        ]);

        return $this->flashResult(
            $this->lms->run(['batch:member-status', (string) $batch->id, (string) $user->id, $validated['status']]),
            'Membership status updated.'
        );
    }

    /**
     * Edit the student's profile (contact / active flag) or re-send their
     * OTP sign-in instructions — the LMS is passwordless, nothing to reset.
     */
    public function updateStudentProfile(Request $request, Batch $batch, LmsUser $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'active' => 'nullable|in:0,1',
            'send_login' => 'nullable|boolean',
        ]);

        $args = ['student:update', (string) $user->id];
        if (! empty($validated['name'])) {
            $args[] = '--name='.$validated['name'];
        }
        if ($request->filled('email')) {
            $args[] = '--email='.$validated['email'];
        }
        if ($request->filled('phone')) {
            $args[] = '--phone='.$validated['phone'];
        }
        if (isset($validated['active']) && $validated['active'] !== null) {
            $args[] = '--active='.$validated['active'];
        }
        if ($request->boolean('send_login')) {
            $args[] = '--send-login';
        }

        return $this->flashResult($this->lms->run($args), 'Student updated.');
    }

    /** Message every student of this batch on WhatsApp + email. */
    public function announce(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => 'nullable|string|max:150',
            'message' => 'required|string|max:1000',
        ]);

        $args = ['batch:announce', (string) $batch->id, $validated['message']];
        if (! empty($validated['subject'])) {
            $args[] = '--subject='.$validated['subject'];
        }

        return $this->flashResult($this->lms->run($args), 'Announcement sent to the batch.');
    }

    /**
     * Schedule the batch's whole class calendar in one pass: chosen weekdays
     * at a fixed time until an end date (or class count). The LMS creates each
     * class with Zoom + reminders, titles them from the syllabus, skips slots
     * already booked, and sends the batch ONE schedule summary message.
     */
    public function scheduleSeries(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => 'integer|between:1,7',
            'time' => 'required|date_format:H:i',
            'duration' => 'nullable|integer|min:15|max:480',
            'start' => 'nullable|date|after_or_equal:today',
            'until' => 'nullable|date|after_or_equal:start|required_without:count',
            'count' => 'nullable|integer|min:1|max:200|required_without:until',
            'topics' => 'nullable|boolean',
        ]);

        $args = [
            'class:schedule-series',
            (string) $batch->id,
            '--weekdays='.implode(',', $validated['weekdays']),
            '--time='.$validated['time'],
        ];
        if (! empty($validated['duration'])) {
            $args[] = '--duration='.$validated['duration'];
        }
        if (! empty($validated['start'])) {
            $args[] = '--start='.$validated['start'];
        }
        if (! empty($validated['until'])) {
            $args[] = '--until='.$validated['until'];
        } else {
            $args[] = '--count='.$validated['count'];
        }
        if (! $request->boolean('topics')) {
            $args[] = '--no-topics';
        }

        return $this->flashResult($this->lms->run($args, 300), 'Class series scheduled.');
    }

    /** Move one class to a new slot — students notified with old vs new time. */
    public function rescheduleClass(Request $request, Batch $batch, int $session): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'duration' => 'nullable|integer|min:15|max:480',
            'reason' => 'nullable|string|max:255',
        ]);

        $args = ['class:reschedule', (string) $session, $validated['date'], $validated['time']];
        if (! empty($validated['duration'])) {
            $args[] = '--duration='.$validated['duration'];
        }
        if (! empty($validated['reason'])) {
            $args[] = '--reason='.$validated['reason'];
        }

        return $this->flashResult($this->lms->run($args), 'Class rescheduled — batch notified.');
    }

    /** Cancel one class — Zoom removed, batch notified, reminders voided. */
    public function cancelClass(Request $request, Batch $batch, int $session): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:255']);

        $args = ['class:cancel', (string) $session];
        if (! empty($validated['reason'])) {
            $args[] = '--reason='.$validated['reason'];
        }

        return $this->flashResult($this->lms->run($args), 'Class cancelled — batch notified.');
    }

    /** Attach a recording URL so absentees can replay the class. */
    public function setRecording(Request $request, Batch $batch, int $session): RedirectResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2000',
            'title' => 'nullable|string|max:255',
            'passcode' => 'nullable|string|max:100',
        ]);

        $args = ['class:recording', (string) $session, $validated['url']];
        if (! empty($validated['title'])) {
            $args[] = '--title='.$validated['title'];
        }
        if (! empty($validated['passcode'])) {
            $args[] = '--passcode='.$validated['passcode'];
        }

        return $this->flashResult($this->lms->run($args), 'Recording attached — students can replay the class.');
    }

    /** @param array{ok: bool, output: string} $result */
    private function flashResult(array $result, string $fallback): RedirectResponse
    {
        return $result['ok']
            ? back()->with('success', $result['output'] ?: $fallback)
            : back()->with('error', 'LMS rejected the action: '.mb_substr($result['output'], 0, 300));
    }

    /**
     * Manually add a class to this batch. The LMS does the heavy lifting
     * (Zoom meeting, reminder ladder) and instantly notifies every student
     * in the batch on WhatsApp + email that a new class is on their calendar.
     */
    public function scheduleClass(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'duration' => 'nullable|integer|min:15|max:480',
            'title' => 'nullable|string|max:255',
            'kind' => 'nullable|in:class,mentoring',
            'host' => 'nullable|integer',
        ]);

        $args = ['class:schedule', (string) $batch->id, $validated['date'], $validated['time']];
        if (! empty($validated['duration'])) {
            $args[] = '--duration='.$validated['duration'];
        }
        if (! empty($validated['title'])) {
            $args[] = '--title='.$validated['title'];
        }
        if (! empty($validated['kind'])) {
            $args[] = '--kind='.$validated['kind'];
        }
        if (! empty($validated['host'])) {
            $args[] = '--host='.$validated['host'];
        }

        $result = $this->lms->run($args);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Class scheduled and students notified.')
            : back()->with('error', 'Could not schedule the class: '.mb_substr($result['output'], 0, 300));
    }

    public function student(Batch $batch, LmsUser $user): View
    {
        return $this->renderLmsPage(function () use ($batch, $user) {
            $batch->load('course');

            $member = BatchMember::where('batch_id', $batch->id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $sessionIds = $batch->liveSessions()->pluck('id')->all();

            $attendance = empty($sessionIds) ? collect() : Attendance::query()
                ->with('liveSession')
                ->whereIn('live_session_id', $sessionIds)
                ->where('user_id', $user->id)
                ->get()
                ->sortByDesc(fn ($a) => $a->liveSession?->scheduled_start)
                ->values();

            $totalSessions = count($sessionIds);

            // Course learning progress: completed topics vs the course's topic count.
            $topicIds = Topic::whereIn('module_id', Module::where('course_id', $batch->course_id)->select('id'))
                ->pluck('id');
            $topicsTotal = $topicIds->count();
            $topicsCompleted = $topicsTotal === 0 ? 0 : TopicCompletion::where('user_id', $user->id)
                ->whereIn('topic_id', $topicIds)
                ->count();

            $quizAttempts = QuizAttempt::with('quiz.lesson')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get();

            $submissions = AssignmentSubmission::with(['assignment', 'grade'])
                ->where('user_id', $user->id)
                ->orderByDesc('submitted_at')
                ->get();

            $mockInterviews = MockInterview::with('blueprint')
                ->where('user_id', $user->id)
                ->orderByDesc('started_at')
                ->get();

            $latestScore = StudentScore::where('user_id', $user->id)
                ->orderByDesc('computed_at')
                ->first();

            $feePlan = FeePlan::with(['instalments', 'payments'])
                ->where('batch_id', $batch->id)
                ->where('user_id', $user->id)
                ->first();

            $certificates = Certificate::with('course')
                ->where('user_id', $user->id)
                ->orderByDesc('issued_at')
                ->get();

            $otherMemberships = BatchMember::with('batch.course')
                ->where('user_id', $user->id)
                ->where('batch_id', '!=', $batch->id)
                ->get();

            return view('live-batches.student', compact(
                'batch', 'user', 'member', 'attendance', 'totalSessions',
                'topicsTotal', 'topicsCompleted', 'quizAttempts', 'submissions',
                'mockInterviews', 'latestScore', 'feePlan', 'certificates', 'otherMemberships'
            ));
        });
    }

    /**
     * Set a course's registration fee (₹). New fee plans for the course use it
     * immediately; blank restores the platform default. Existing plans are
     * untouched — a price change never rewrites an agreed schedule.
     */
    public function updateCourseFee(Request $request, int $course): RedirectResponse
    {
        $validated = $request->validate([
            'fee_rupees' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $model = Course::query()->findOrFail($course);
        $model->update([
            'fee_paise' => $validated['fee_rupees'] !== null && $validated['fee_rupees'] !== ''
                ? (int) round(((float) $validated['fee_rupees']) * 100)
                : null,
        ]);

        return back()->with('success', "{$model->name}: registration fee updated.");
    }
}
