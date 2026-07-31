<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Batch;
use App\Models\Lms\BatchMember;
use App\Models\Lms\MockInterview;
use App\Models\Lms\Product;
use App\Models\Lms\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MockTestResultController extends Controller
{
    use ReadsLmsDatabase;

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $batchUserIds = null;
            if ($request->filled('batch_id')) {
                $batchUserIds = BatchMember::where('batch_id', $request->input('batch_id'))
                    ->pluck('user_id')
                    ->all();
            }

            $mocks = MockInterview::query()
                ->with(['user', 'blueprint'])
                ->when($batchUserIds !== null, fn ($q) => $q->whereIn('user_id', $batchUserIds))
                ->when($request->input('search'), function ($q, $search) {
                    $q->whereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderByDesc('started_at')
                ->paginate(15, ['*'], 'mocks_page')
                ->withQueryString();

            $quizAttempts = QuizAttempt::query()
                ->with(['user', 'quiz'])
                ->when($batchUserIds !== null, fn ($q) => $q->whereIn('user_id', $batchUserIds))
                ->when($request->input('search'), function ($q, $search) {
                    $q->whereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderByDesc('created_at')
                ->paginate(15, ['*'], 'quizzes_page')
                ->withQueryString();

            $batchOptions = Batch::with('course')->orderByDesc('created_at')->get();

            $totalMocks = MockInterview::count();
            $completedMocks = MockInterview::where('status', 'completed')->count();
            $avgMockScore = (int) round(MockInterview::whereNotNull('overall_score')->avg('overall_score') ?? 0);
            $avgQuizScore = (int) round(QuizAttempt::whereNotNull('score_pct')->avg('score_pct') ?? 0);

            $voicePacks = Product::query()
                ->where('feature', 'voice_mock')
                ->orderBy('price_paise')
                ->get();

            return view('mock-test-results.index', compact(
                'mocks', 'quizAttempts', 'batchOptions',
                'totalMocks', 'completedMocks', 'avgMockScore', 'avgQuizScore',
                'voicePacks'
            ));
        });
    }

    /**
     * Reprice a voice-interview pack in the LMS store. The student /mock page
     * reads prices live from the products table, so a save here is effective
     * on the students' next page load — the charge amount is always taken
     * server-side from this row, never from the client.
     */
    public function updatePricing(Request $request, int $product): RedirectResponse
    {
        $validated = $request->validate([
            'price_rupees' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'sessions' => ['required', 'integer', 'min:1', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ]);

        $pack = Product::query()
            ->where('feature', 'voice_mock')
            ->findOrFail($product);

        $pack->update([
            'price_paise' => (int) round($validated['price_rupees'] * 100),
            'grant_amount' => (int) $validated['sessions'],
            'active' => $request->boolean('active'),
        ]);

        return back()->with('status', "{$pack->name} updated — students see the new price immediately.");
    }
}
