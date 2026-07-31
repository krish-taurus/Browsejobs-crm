<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Course;
use App\Models\Lms\MentorProfile;
use App\Models\Lms\MentorSession;
use App\Models\Role;
use App\Models\User;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The LMS mentor pool, managed from the CRM (PRD §6.11): who mentors, what
 * they cover, and how their sessions are going. Availability stays with the
 * mentor in their own LMS hub — admins manage WHO, mentors manage WHEN.
 * Reads the LMS database directly; writes go through the mentor:set command.
 */
class MentorManagementController extends Controller
{
    use ReadsLmsDatabase;

    public function __construct(private LmsCommandRunner $lms) {}

    public function index(): View
    {
        return $this->renderLmsPage(function () {
            $mentors = MentorProfile::query()
                ->with(['user:id,name,email,phone', 'availabilities'])
                ->withCount([
                    'availabilities',
                    'sessions as booked_count' => fn ($q) => $q->where('status', 'booked'),
                    'sessions as completed_count' => fn ($q) => $q->where('status', 'completed'),
                    'sessions as no_show_count' => fn ($q) => $q->where('status', 'no_show'),
                ])
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->get();

            $ratings = MentorSession::query()
                ->whereNotNull('student_rating')
                ->selectRaw('mentor_profile_id, ROUND(AVG(student_rating), 1) as avg_rating')
                ->groupBy('mentor_profile_id')
                ->pluck('avg_rating', 'mentor_profile_id');

            // The booking pipeline: confirmed upcoming 1:1s first (the team
            // coordinates these), then recent history.
            $upcomingSessions = MentorSession::query()
                ->with(['mentor.user:id,name', 'student:id,name,phone'])
                ->where('status', 'booked')
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->get();

            $recentSessions = MentorSession::query()
                ->with(['mentor.user:id,name', 'student:id,name,phone'])
                ->where(fn ($q) => $q->where('status', '!=', 'booked')->orWhere('starts_at', '<', now()))
                ->orderByDesc('starts_at')
                ->limit(30)
                ->get();

            $courses = Course::orderBy('name')->get(['id', 'name', 'slug']);

            // The pool is drawn from the CRM's own Tech Mentor role — the LMS
            // staff account is created automatically on first save.
            $techMentorRoleId = Role::query()->where('role_name', 'Tech Mentor')->value('id') ?? 22;
            $staff = User::query()
                ->where('role_id', $techMentorRoleId)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->get();

            return view('mentors.index', compact('mentors', 'ratings', 'upcomingSessions', 'recentSessions', 'courses', 'staff'));
        });
    }

    /**
     * Add a staff user to the pool or update an existing mentor — the LMS
     * mentor:set command does the writing (updateOrCreate semantics).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user' => 'required|string|max:255',
            'headline' => 'nullable|string|max:190',
            'expertise' => 'nullable|string|max:500',
            'course_slugs' => 'nullable|array',
            'course_slugs.*' => 'string|max:255',
            'active' => 'nullable|in:0,1',
        ]);

        // The form submits a CRM Tech Mentor user id; the LMS is addressed by
        // email (its staff account is auto-created on first save). Direct
        // email/id strings still work for status toggles on existing mentors.
        $identifier = $validated['user'];
        $crmUser = ctype_digit($identifier) ? User::find((int) $identifier) : null;

        $args = ['mentor:set', $crmUser?->email ?? $identifier];
        if ($crmUser !== null) {
            $args[] = '--name='.$crmUser->full_name;
            if (filled($crmUser->phone)) {
                $args[] = '--phone='.$crmUser->phone;
            }
        }
        if ($request->filled('headline')) {
            $args[] = '--headline='.$validated['headline'];
        }
        if ($request->filled('expertise')) {
            $args[] = '--expertise='.$validated['expertise'];
        }
        if ($request->has('course_slugs')) {
            $args[] = '--courses='.implode(',', $validated['course_slugs'] ?? []);
        }
        if (isset($validated['active'])) {
            $args[] = '--active='.$validated['active'];
        }

        $result = $this->lms->run($args);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Mentor saved.')
            : back()->with('error', 'Could not save the mentor: '.mb_substr($result['output'], 0, 300));
    }

    /**
     * Replace a mentor's weekly availability (IST windows) — students book
     * 30-minute slots inside these on their Mentors page.
     */
    public function setSlots(Request $request, int $mentor): RedirectResponse
    {
        $validated = $request->validate([
            'day_start' => 'required|array',
            'day_start.*' => 'nullable|date_format:H:i',
            'day_end' => 'required|array',
            'day_end.*' => 'nullable|date_format:H:i',
        ]);

        $profile = MentorProfile::query()->with('user:id,email')->findOrFail($mentor);

        $dayNames = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
        $entries = [];

        foreach ($dayNames as $day => $name) {
            $start = $validated['day_start'][$day] ?? null;
            $end = $validated['day_end'][$day] ?? null;

            if (filled($start) && filled($end)) {
                if ($end <= $start) {
                    return back()->with('error', "{$name}: end time must be after start time.");
                }
                $entries[] = "{$name} {$start}-{$end}";
            }
        }

        $args = ['mentor:slots', (string) $profile->user?->email];
        if ($entries === []) {
            $args[] = '--clear';
        } else {
            $args[] = '--set='.implode('; ', $entries);
        }

        $result = $this->lms->run($args);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Availability saved.')
            : back()->with('error', 'Could not save availability: '.mb_substr($result['output'], 0, 300));
    }
}
