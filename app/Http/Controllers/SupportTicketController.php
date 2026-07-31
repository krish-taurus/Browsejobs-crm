<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsLmsDatabase;
use App\Models\Lms\Ticket;
use App\Services\LmsCommandRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRM support desk over the LMS ticket system: queue with SLA/AI-priority
 * signals, full thread view, staff replies and status changes — reply/status
 * run through LMS commands so notifications and SLA bookkeeping stay intact.
 */
class SupportTicketController extends Controller
{
    use ReadsLmsDatabase;

    public function __construct(private readonly LmsCommandRunner $lms) {}

    public function index(Request $request): View
    {
        return $this->renderLmsPage(function () use ($request) {
            $tickets = Ticket::query()
                ->with(['student', 'messages'])
                ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->input('search'), function ($q, $search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhereHas('student', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                })
                ->orderByRaw("CASE WHEN status IN ('open', 'in_progress') THEN 0 ELSE 1 END")
                ->orderByDesc('ai_priority_raised')
                ->orderBy('first_response_due_at')
                ->limit(100)
                ->get();

            $stats = [
                'open' => Ticket::query()->whereIn('status', ['open', 'in_progress'])->count(),
                'waiting' => Ticket::query()->where('status', 'waiting_on_student')->count(),
                'breached' => Ticket::query()->whereNotNull('breached_at')->whereNotIn('status', ['resolved', 'closed'])->count(),
                'resolved_week' => Ticket::query()->where('resolved_at', '>=', now()->subDays(7))->count(),
            ];

            return view('support-tickets.index', compact('tickets', 'stats'));
        });
    }

    public function show(int $ticket): View
    {
        return $this->renderLmsPage(function () use ($ticket) {
            $model = Ticket::query()->with(['student', 'messages'])->findOrFail($ticket);

            return view('support-tickets.show', ['ticket' => $model]);
        });
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $result = $this->lms->run([
            'ticket:reply', (string) $ticket,
            '--body='.$validated['body'],
            '--as='.(string) ($request->user()->email ?? ''),
        ], 60);

        return $result['ok']
            ? back()->with('success', trim($result['output']) ?: 'Reply sent.')
            : back()->with('error', 'Reply failed: '.mb_substr($result['output'], 0, 300));
    }

    public function status(Request $request, int $ticket): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:open,in_progress,waiting_on_student,resolved,closed']]);

        $result = $this->lms->run(['ticket:status', (string) $ticket, $validated['status']], 60);

        return $result['ok']
            ? back()->with('success', trim($result['output']) ?: 'Status updated.')
            : back()->with('error', 'Update failed: '.mb_substr($result['output'], 0, 300));
    }
}
