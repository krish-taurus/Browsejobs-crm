<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Assignment;
use App\Models\Lms\AssignmentSubmission;
use App\Models\Lms\Lesson;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRM grading cockpit (PRD §6.5 workflow, founder-managed): create assignments
 * with rubrics, watch submissions arrive, trigger AI draft grading, and release
 * grades — release/grade run through LMS commands so every side effect (audit,
 * student notification) stays intact.
 */
class GradingController extends Controller
{
    use ReadsLmsDatabase;

    public function __construct(private readonly LmsCommandRunner $lms) {}

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $submissions = AssignmentSubmission::query()
                ->with(['assignment.lesson:id,title', 'user', 'grade'])
                ->when($request->input('search'), function ($q, $search) {
                    $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                })
                ->orderByDesc('submitted_at')
                ->limit(200)
                ->get()
                ->map(function (AssignmentSubmission $s) {
                    $bucket = match (true) {
                        $s->grade === null => 'ungraded',
                        $s->grade->status !== 'approved' => 'draft',
                        default => 'released',
                    };
                    $s->bucket = $bucket;

                    return $s;
                });

            if ($bucket = $request->input('status')) {
                $submissions = $submissions->where('bucket', $bucket)->values();
            }

            // Review order: AI drafts first (they are one click from done),
            // then ungraded, then released history.
            $order = ['draft' => 0, 'ungraded' => 1, 'released' => 2];
            $submissions = $submissions->sortBy(fn ($s) => $order[$s->bucket] ?? 9)->values();

            $stats = [
                'awaiting_release' => $submissions->where('bucket', 'draft')->count(),
                'ungraded' => $submissions->where('bucket', 'ungraded')->count(),
                'released' => $submissions->where('bucket', 'released')->count(),
                'avg_score' => (int) round($submissions->where('bucket', 'released')->avg(fn ($s) => $s->grade?->score) ?? 0),
            ];

            $lessonOptions = Lesson::query()->orderBy('id')->get(['id', 'title']);

            $assignments = Assignment::query()->with('lesson:id,title')->orderByDesc('id')->limit(20)->get();

            return view('grading.index', compact('submissions', 'stats', 'lessonOptions', 'assignments'));
        });
    }

    /** Create an assignment (title + instructions + rubric) on a lesson. */
    public function storeAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['required', 'string', 'max:5000'],
            'criteria' => ['required', 'array', 'min:1', 'max:6'],
            'criteria.*.name' => ['nullable', 'string', 'max:80'],
            'criteria.*.points' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rubric = collect($validated['criteria'])
            ->filter(fn ($c) => ! empty($c['name']) && ! empty($c['points']))
            ->map(fn ($c) => ['criterion' => $c['name'], 'max_points' => (int) $c['points']])
            ->values();

        if ($rubric->isEmpty()) {
            return back()->with('error', 'Add at least one rubric row (name + points).');
        }

        $lesson = Lesson::query()->findOrFail($validated['lesson_id']);

        Assignment::query()->create([
            'tenant_id' => $lesson->tenant_id,
            'lesson_id' => $lesson->id,
            'title' => $validated['title'],
            'instructions' => $validated['instructions'],
            'rubric' => $rubric->all(),
            'max_points' => $rubric->sum('max_points'),
            'allow_link' => true,
            'allow_file' => true,
        ]);

        return back()->with('success', "Assignment \"{$validated['title']}\" created — students see it on the lesson page.");
    }

    /** Run AI draft grading for an ungraded submission (LMS grading:grade). */
    public function gradeSubmission(int $submission): RedirectResponse
    {
        $result = $this->lms->run(['grading:grade', (string) $submission], 180);

        return $result['ok']
            ? back()->with('success', trim($result['output']) ?: 'AI draft ready for review.')
            : back()->with('error', 'Grading failed: '.mb_substr($result['output'], 0, 300));
    }

    /** Release a draft grade to the student (LMS grading:release). */
    public function releaseGrade(int $grade): RedirectResponse
    {
        $result = $this->lms->run(['grading:release', (string) $grade], 60);

        return $result['ok']
            ? back()->with('success', trim($result['output']) ?: 'Grade released.')
            : back()->with('error', 'Release failed: '.mb_substr($result['output'], 0, 300));
    }
}
