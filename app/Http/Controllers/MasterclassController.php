<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Batch;
use App\Models\Lms\BatchMember;
use App\Models\Lms\Course;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterclassController extends Controller
{
    use ReadsLmsDatabase;

    public function __construct(private LmsCommandRunner $lms) {}

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $query = Batch::query()
                ->with(['course', 'trainer', 'liveSessions' => fn ($q) => $q->orderBy('scheduled_start')])
                ->withCount(['members as students_count'])
                ->where('type', 'masterclass');

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

            $courses = Course::orderBy('name')->get(['id', 'name', 'slug', 'masterclass_video_url']);

            $totalMasterclasses = Batch::where('type', 'masterclass')->count();
            $activeMasterclasses = Batch::where('type', 'masterclass')->where('status', 'active')->count();
            $totalRegistrations = BatchMember::whereIn(
                'batch_id',
                Batch::where('type', 'masterclass')->select('id')
            )->count();

            return view('masterclasses.index', compact(
                'batches', 'courses', 'totalMasterclasses', 'activeMasterclasses', 'totalRegistrations'
            ));
        });
    }

    /**
     * Plan a masterclass in advance: course + date + time slot. Creates the
     * batch and its class (Zoom + reminder ladder) inside the LMS.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_slug' => 'required|string|max:255',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'duration' => 'nullable|integer|min:15|max:480',
            'capacity' => 'nullable|integer|min:1',
        ]);

        $args = ['masterclass:plan', $validated['course_slug'], $validated['date'], $validated['time']];
        if (! empty($validated['duration'])) {
            $args[] = '--duration='.$validated['duration'];
        }
        if (! empty($validated['capacity'])) {
            $args[] = '--capacity='.$validated['capacity'];
        }

        $result = $this->lms->run($args);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Masterclass planned.')
            : back()->with('error', 'Could not plan the masterclass: '.mb_substr($result['output'], 0, 300));
    }

    /**
     * Set (or clear) a course's recorded masterclass — the video the public
     * watch page streams as a daily simulated-live showing, so a Monday lead
     * never waits for Saturday.
     */
    public function setVideo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_slug' => 'required|string|max:255',
            'video_url' => 'nullable|url|max:2000',
        ]);

        $result = $this->lms->run([
            'course:set-masterclass-video',
            $validated['course_slug'],
            $validated['video_url'] ?: 'clear',
        ]);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Masterclass recording saved.')
            : back()->with('error', 'Could not save: '.mb_substr($result['output'], 0, 300));
    }

    /**
     * Move a masterclass to a new date/time — students and trainers are
     * notified with old vs new time by the LMS.
     */
    public function reschedule(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ]);

        $args = ['masterclass:reschedule', (string) $batch->id, $validated['date'], $validated['time']];
        if (! empty($validated['reason'])) {
            $args[] = '--reason='.$validated['reason'];
        }

        $result = $this->lms->run($args);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Masterclass rescheduled.')
            : back()->with('error', 'Could not reschedule: '.mb_substr($result['output'], 0, 300));
    }

    /**
     * Cancel a masterclass — its class is cancelled with notifications to
     * everyone enrolled, and the weekend automation respects the cancellation.
     */
    public function cancel(Request $request, Batch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $args = ['masterclass:cancel', (string) $batch->id];
        if (! empty($validated['reason'])) {
            $args[] = '--reason='.$validated['reason'];
        }

        $result = $this->lms->run($args);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Masterclass cancelled.')
            : back()->with('error', 'Could not cancel: '.mb_substr($result['output'], 0, 300));
    }
}
