<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Celebration;
use App\Models\Lms\ContentHubItem;
use App\Models\Lms\Course;
use App\Models\Lms\LmsUser;
use App\Models\Lms\PulseDigest;
use App\Models\Lms\PulseItem;
use App\Models\Lms\Tenant;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Student Pulse curation. The student portal's Pulse page has three sections —
 * Market Pulse, the celebration wall, and the Content Hub — and every one of
 * them is fed by rows a human has to add. The LMS digest builder deliberately
 * returns nothing when no source items exist ("never invented news"), so with
 * no curation screen the whole page sat empty forever. This is that screen.
 */
class StudentPulseController extends Controller
{
    use ReadsLmsDatabase;

    public function __construct(private readonly LmsCommandRunner $lms) {}

    public function index(): View
    {
        return $this->renderLmsPage(function () {
            $digest = PulseDigest::query()->orderByDesc('digest_date')->first();

            return view('student-pulse.index', [
                'items' => PulseItem::query()->where('is_active', true)->orderByDesc('published_on')->limit(50)->get(),
                'digest' => $digest,
                'digestItems' => $digest
                    ? PulseItem::query()->whereIn('id', (array) $digest->item_ids)->get(['id', 'title', 'source_name'])
                    : collect(),
                'windowDays' => 3,
                'content' => ContentHubItem::query()->where('is_active', true)->orderByDesc('published_at')->limit(50)->get(),
                'celebrations' => Celebration::query()->with('student:id,name')->orderByDesc('created_at')->limit(50)->get(),
                'courses' => Course::query()->orderBy('name')->get(['slug', 'name']),
                'students' => LmsUser::query()->where('user_type', 'student')->orderBy('name')->limit(500)->get(['id', 'name']),
                'kinds' => ContentHubItem::KINDS,
            ]);
        });
    }

    /** Curate one Market Pulse source item. */
    public function storeItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'url' => ['required', 'url', 'max:255'],
            'source_name' => ['required', 'string', 'max:120'],
            'course_slug' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:190'],
            'published_on' => ['nullable', 'date'],
        ]);

        PulseItem::query()->create([
            'tenant_id' => $this->tenantId(),
            'title' => $validated['title'],
            'url' => $validated['url'],
            'source_name' => $validated['source_name'],
            // Nullable fields the browser simply omits are absent from
            // $validated, so read them defensively rather than by key.
            'course_slug' => ($validated['course_slug'] ?? '') ?: null,
            'note' => ($validated['note'] ?? '') ?: null,
            'published_on' => $validated['published_on'] ?? Carbon::today()->toDateString(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Source added. Build the digest to publish it to students.');
    }

    public function destroyItem(int $item): RedirectResponse
    {
        PulseItem::query()->whereKey($item)->update(['is_active' => false]);

        return back()->with('success', 'Source removed from the next digest.');
    }

    /**
     * Build today's digest now. The LMS owns the AI narration, so this runs its
     * command rather than reimplementing it here.
     */
    public function generateDigest(): RedirectResponse
    {
        $fresh = PulseItem::query()
            ->where('is_active', true)
            ->whereDate('published_on', '>=', Carbon::today()->subDays(3))
            ->count();

        if ($fresh === 0) {
            return back()->with('error', 'No sources from the last 3 days — add at least one, then build. The digest never invents news.');
        }

        $result = $this->lms->run(['digest:market-pulse'], 180);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? 'Digest built — students see it on their Pulse page within a minute.'
                : 'Could not build the digest: '.$result['output'],
        );
    }

    public function storeContent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(ContentHubItem::KINDS)],
            'title' => ['required', 'string', 'max:190'],
            'url' => ['required', 'url', 'max:500'],
            'published_at' => ['nullable', 'date'],
        ]);

        ContentHubItem::query()->create([
            'tenant_id' => $this->tenantId(),
            'kind' => $validated['kind'],
            'title' => $validated['title'],
            'url' => $validated['url'],
            'source' => 'manual',
            'published_at' => $validated['published_at'] ?? now(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Published to the Content Hub.');
    }

    public function destroyContent(int $item): RedirectResponse
    {
        ContentHubItem::query()->whereKey($item)->update(['is_active' => false]);

        return back()->with('success', 'Removed from the Content Hub.');
    }

    /**
     * Add a win to the celebration wall. Consent is mandatory — the wall shows
     * a real student's name and employer, so it is never published without it.
     */
    public function storeCelebration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'display_mode' => ['required', Rule::in(['named', 'anonymous'])],
            'anonymous_label' => ['required_if:display_mode,anonymous', 'nullable', 'string', 'max:190'],
            'role_title' => ['required', 'string', 'max:190'],
            'company' => ['nullable', 'string', 'max:190'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'You must confirm the student consented before this can go on the wall.',
        ]);

        Celebration::query()->create([
            'tenant_id' => $this->tenantId(),
            'student_id' => $validated['student_id'],
            'display_mode' => $validated['display_mode'],
            'anonymous_label' => ($validated['anonymous_label'] ?? '') ?: null,
            'role_title' => $validated['role_title'],
            'company' => ($validated['company'] ?? '') ?: null,
            'consented_at' => now(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Win saved. Publish it to put it on the students\' wall.');
    }

    public function publishCelebration(int $celebration): RedirectResponse
    {
        Celebration::query()->whereKey($celebration)->update(['published_at' => now(), 'is_active' => true]);

        return back()->with('success', 'Win published to the celebration wall.');
    }

    public function unpublishCelebration(int $celebration): RedirectResponse
    {
        Celebration::query()->whereKey($celebration)->update(['published_at' => null]);

        return back()->with('success', 'Win taken off the wall.');
    }

    private function tenantId(): int
    {
        return (int) (Tenant::query()->min('id') ?? 1);
    }
}
