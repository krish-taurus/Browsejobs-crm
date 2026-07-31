<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Attendance;
use App\Models\Lms\Batch;
use App\Models\Lms\LiveSession;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassAttendanceController extends Controller
{
    use ReadsLmsDatabase;

    public function __construct(private LmsCommandRunner $lms) {}

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $query = LiveSession::query()
                ->with(['batch.course', 'host'])
                ->withCount('attendance as attendees_count')
                ->withAvg('attendance as avg_attended_pct', 'attended_pct');

            if ($request->filled('batch_id')) {
                $query->where('batch_id', $request->input('batch_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('date')) {
                $query->whereDate('scheduled_start', $request->input('date'));
            }

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }

            $sessions = $query->orderByDesc('scheduled_start')->paginate(20)->withQueryString();

            $batchOptions = Batch::with('course')->orderByDesc('created_at')->get();

            $totalSessions = LiveSession::count();
            $completedSessions = LiveSession::whereIn('status', ['completed', 'ended'])->count();
            $upcomingSessions = LiveSession::where('scheduled_start', '>', now())->count();

            return view('class-attendance.index', compact(
                'sessions', 'batchOptions', 'totalSessions', 'completedSessions', 'upcomingSessions'
            ));
        });
    }

    public function show(LiveSession $session): View
    {
        return $this->renderLmsPage(function () use ($session) {
            $session->load(['batch.course', 'host']);

            // Full roster of the batch, joined against attendance so absentees show too.
            $members = $session->batch->members()->with('user')->get();

            $attendance = Attendance::where('live_session_id', $session->id)
                ->get()
                ->keyBy('user_id');

            return view('class-attendance.show', compact('session', 'members', 'attendance'));
        });
    }

    /**
     * Mark a student present/absent for this class — the manual correction
     * path when Zoom missed a join. Written by the LMS (audited there).
     */
    public function mark(Request $request, LiveSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'present' => 'required|in:0,1',
            'late' => 'nullable|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $args = [
            'attendance:mark',
            (string) $session->id,
            (string) $validated['user_id'],
            $validated['present'] === '1' ? '100' : '0',
        ];
        if ($request->boolean('late')) {
            $args[] = '--late';
        }
        if (! empty($validated['reason'])) {
            $args[] = '--reason='.$validated['reason'];
        }

        $result = $this->lms->run($args);

        return $result['ok']
            ? back()->with('success', $result['output'] ?: 'Attendance updated.')
            : back()->with('error', 'Could not update attendance: '.mb_substr($result['output'], 0, 300));
    }
}
