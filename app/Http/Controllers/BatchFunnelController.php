<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lead;
use App\Models\Lms\Batch;
use App\Models\Lms\BatchMember;
use App\Models\Lms\Course;
use App\Models\Lms\FeePlan;
use App\Models\Lms\LmsLead;
use App\Models\Lms\LmsUser;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The CRM's single management cockpit for the enrolment funnel: course interest
 * → Saturday masterclass → 7-day bootcamp → paid batch. The LMS stays a
 * student-only surface; every stage is monitored (and the automation manually
 * triggerable) from here.
 */
class BatchFunnelController extends Controller
{
    use ReadsLmsDatabase;

    public function index(): View
    {
        return $this->renderLmsPage(function () {
            // Mirror of the LMS scheduling rule: courses alternate weekend days
            // by catalogue position — even index Saturday, odd index Sunday.
            $orderedCourses = Course::query()->orderBy('position')->orderBy('id')->get();
            $courseDays = $orderedCourses->values()->mapWithKeys(function ($course, $index) {
                $day = $index % 2 === 0 ? Carbon::SATURDAY : Carbon::SUNDAY;
                $next = Carbon::today()->dayOfWeek === $day ? Carbon::today() : Carbon::today()->next($day);

                return [$course->slug => ['label' => $day === Carbon::SATURDAY ? 'Saturday' : 'Sunday', 'date' => $next]];
            });

            $courses = $orderedCourses->keyBy('slug');

            // --- Stage 0: course interest (masterclass bookings not yet seated) ---
            $students = LmsUser::students()->get();
            $byPhone = $students->filter(fn ($u) => filled($u->phone))
                ->keyBy(fn ($u) => preg_replace('/\D+/', '', (string) $u->phone));
            $byEmail = $students->filter(fn ($u) => filled($u->email))
                ->keyBy(fn ($u) => mb_strtolower((string) $u->email));

            $interest = LmsLead::query()
                ->where('lead_type', 'masterclass')
                ->whereNull('merged_into_id')
                ->get()
                ->groupBy(fn ($lead) => $lead->course_slug ?: '(no course)')
                ->map(function ($leads, $slug) use ($courses, $courseDays, $byPhone, $byEmail) {
                    $registered = $leads->filter(function ($lead) use ($byPhone, $byEmail) {
                        $phone = $lead->phone_normalized ?: preg_replace('/\D+/', '', (string) $lead->phone);

                        return $byPhone->has($phone)
                            || ($lead->email && $byEmail->has(mb_strtolower($lead->email)));
                    });

                    $schedule = $courseDays->get($slug);

                    return [
                        'course' => $courses->get($slug)?->name ?? $slug,
                        'leads' => $leads->count(),
                        'registered' => $registered->count(),
                        'day' => $schedule['label'] ?? null,
                        'next_date' => $schedule['date'] ?? null,
                    ];
                })
                ->sortByDesc('leads')
                ->values();

            // --- Stages 1-3: the batch chains ---
            $batches = Batch::query()
                ->with(['course'])
                ->withCount('members')
                ->orderByDesc('created_at')
                ->get();

            $masterclasses = $batches->where('type', 'masterclass')->values();
            $bootcampsByMasterclass = $batches->where('type', 'bootcamp')->keyBy('linked_source_batch_id');
            $paidByBootcamp = $batches->where('type', 'paid')->whereNotNull('linked_source_batch_id')->keyBy('linked_source_batch_id');

            $paidIds = $batches->where('type', 'paid')->pluck('id');
            $feeTotals = $paidIds->isEmpty() ? collect() : FeePlan::query()
                ->whereIn('batch_id', $paidIds)
                ->with('instalments')
                ->get()
                ->groupBy('batch_id')
                ->map(fn ($plans) => [
                    'payable' => $plans->sum(fn ($p) => max(0, $p->total_paise - $p->discount_paise)),
                    'paid' => $plans->sum(fn ($p) => $p->instalments->where('status', 'paid')->sum('amount_paise')),
                ]);

            $memberStatusCounts = BatchMember::query()
                ->whereIn('batch_id', $batches->pluck('id'))
                ->selectRaw('batch_id, status, COUNT(*) as total')
                ->groupBy('batch_id', 'status')
                ->get()
                ->toBase()
                ->groupBy('batch_id');

            $chains = $masterclasses->map(function ($mc) use ($bootcampsByMasterclass, $paidByBootcamp) {
                $bootcamp = $bootcampsByMasterclass->get($mc->id);
                $paid = $bootcamp ? $paidByBootcamp->get($bootcamp->id) : null;

                return ['masterclass' => $mc, 'bootcamp' => $bootcamp, 'paid' => $paid];
            });

            // Bootcamps/paid batches created before chain-linking existed.
            $chainedBootcampIds = $chains->pluck('bootcamp.id')->filter();
            $unchainedBootcamps = $batches->where('type', 'bootcamp')->whereNotIn('id', $chainedBootcampIds)->values();

            $stats = [
                'interest' => $interest->sum('leads'),
                'ready' => $interest->sum('registered'),
                'activeBootcamps' => $batches->where('type', 'bootcamp')->where('status', 'active')->count(),
                'awaitingPayment' => $memberStatusCounts
                    ->only($paidIds->all())
                    ->flatten(1)
                    ->whereIn('status', ['reserved', 'payment_pending'])
                    ->sum('total'),
            ];

            // Leads stuck without a course — the one thing that blocks seating.
            $noCourseLeads = LmsLead::query()
                ->where('lead_type', 'masterclass')
                ->whereNull('merged_into_id')
                ->whereNull('course_slug')
                ->orderByDesc('id')
                ->get();

            return view('batch-funnel.index', compact(
                'interest', 'chains', 'unchainedBootcamps', 'memberStatusCounts',
                'feeTotals', 'stats', 'noCourseLeads', 'orderedCourses'
            ));
        });
    }

    /**
     * Assign the interested course to a lead that reached the LMS without one —
     * the last blocker before the funnel can seat them. Mirrors the choice onto
     * the matching CRM lead so both sides agree.
     */
    public function assignCourse(Request $request, LmsLead $lmsLead): RedirectResponse
    {
        $validated = $request->validate(['course_slug' => 'required|string|max:255']);

        $slug = Course::query()->where('slug', $validated['course_slug'])->value('slug');
        abort_unless($slug !== null, 422, 'Unknown course.');

        $lmsLead->forceFill(['course_slug' => $slug])->save();

        // Keep the CRM lead in sync (matched by last-10-digit phone).
        $digits = preg_replace('/\D+/', '', (string) $lmsLead->phone) ?? '';
        $last10 = substr($digits, -10);
        if (strlen($last10) >= 10) {
            Lead::query()
                ->where('mobile', 'like', "%{$last10}")
                ->whereNull('interested_course_slug')
                ->update(['interested_course_slug' => $slug]);
        }

        return back()->with('success', ($lmsLead->name ?: $lmsLead->phone).' → '.$slug.'. Run the funnel to seat them.');
    }

    /**
     * Manually run the LMS funnel automation (the same `funnel:advance` the
     * scheduler runs daily) — so admins never need the LMS admin panel.
     */
    public function run(LmsCommandRunner $lms): RedirectResponse
    {
        $result = $lms->run(['funnel:advance']);

        if (! $result['ok']) {
            return back()->with('error', 'Funnel run failed: '.mb_substr($result['output'], 0, 300));
        }

        return back()->with('success', 'Funnel advanced: '.mb_substr($result['output'] ?: 'done.', 0, 500));
    }
}
