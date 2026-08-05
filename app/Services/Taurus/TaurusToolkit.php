<?php

namespace App\Services\Taurus;

use App\Models\Conversation;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaurusAction;
use App\Models\TaurusSubscription;
use App\Models\TaurusTarget;
use App\Models\User;
use App\Notifications\NewChatMessage;
use App\Notifications\TaskAssigned;
use App\Services\TaurusSnapshotService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * The tools Taurus can call, and the code behind them.
 *
 * Two classes of tool, and the split is the whole safety model:
 *
 * - READ tools run the moment the model asks. Worst case is a wasted query.
 * - WRITE tools never run from a model decision. They are recorded as a
 *   pending {@see TaurusAction} and the model is told so; the Super Admin sees
 *   a confirmation card and clicks. Only then does execute() run, with the
 *   arguments frozen at proposal time and re-validated on the way in.
 *
 * That means a prompt injected through a lead's name or a call transcript can
 * cause a *proposal* to appear, never a message to actually go out.
 */
class TaurusToolkit
{
    public function __construct(private readonly TaurusSnapshotService $snapshot) {}

    /**
     * Tool schemas, in the shape the Anthropic Messages API expects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'get_business_snapshot',
                'description' => 'The full live dashboard: placement pipeline, revenue and expenses, '
                    .'lead conversion and funnel, student and staff attendance, ad spend, tracked '
                    .'subscriptions, and team targets versus live actuals. Call this first for any '
                    .'question about how the business is doing.',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'list_team',
                'description' => 'Every active staff member with their id, name and role. Call this to '
                    .'resolve a person named in the founder\'s request (for example "tell Meera") into '
                    .'the user_id that send_message, create_task and set_target need.',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'search_leads',
                'description' => 'Search the CRM lead list. Every filter is optional; with none it '
                    .'returns the most recent leads.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Match against name, mobile or email.'],
                        'status' => ['type' => 'string', 'description' => 'Lead status slug: new, interested, follow_up, not_interested, invalid_number, joined, lost.'],
                        'source' => ['type' => 'string', 'description' => 'Lead source, e.g. "Meta Ads".'],
                        'assigned_to_user_id' => ['type' => 'integer', 'description' => 'Only leads assigned to this user.'],
                        'unassigned' => ['type' => 'boolean', 'description' => 'Only leads with nobody assigned.'],
                        'created_within_days' => ['type' => 'integer', 'description' => 'Only leads created in the last N days.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, 1-50. Defaults to 20.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_lead',
                'description' => 'Everything on one lead: details, status history, and a summary of each '
                    .'call including disposition and sentiment.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['lead_id' => ['type' => 'integer']],
                    'required' => ['lead_id'],
                ],
            ],
            [
                'name' => 'search_tasks',
                'description' => 'Tasks in the CRM, newest first, with their assignees and completion state.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'assignee_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'description' => 'pending, in_progress or completed.'],
                        'overdue_only' => ['type' => 'boolean'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, 1-50. Defaults to 20.'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'search_expenses',
                'description' => 'Recorded expenses, newest first. Use for "what did we spend on X" questions '
                    .'that the snapshot totals do not answer.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string', 'description' => 'Partial match on the category.'],
                        'within_days' => ['type' => 'integer', 'description' => 'Only expenses in the last N days. Defaults to 30.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, 1-50. Defaults to 20.'],
                    ],
                    'required' => [],
                ],
            ],

            /* ---- Everything below only ever proposes. ---- */

            [
                'name' => 'send_message',
                'description' => 'Propose sending a chat message to a colleague in the CRM. This does NOT '
                    .'send it — the founder sees the exact text and approves it first. Write the message '
                    .'body as the founder would send it, in first person.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer', 'description' => 'Recipient, from list_team.'],
                        'body' => ['type' => 'string', 'description' => 'The message text, max 5000 characters.'],
                    ],
                    'required' => ['user_id', 'body'],
                ],
            ],
            [
                'name' => 'create_task',
                'description' => 'Propose creating a task and assigning it to one or more people. Does NOT '
                    .'create it — the founder approves first.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                        'priority' => ['type' => 'string', 'description' => 'low, medium or high.'],
                        'assignee_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    ],
                    'required' => ['title', 'due_date', 'priority', 'assignee_ids'],
                ],
            ],
            [
                'name' => 'set_target',
                'description' => 'Propose setting someone\'s monthly target. Does NOT save it — the founder '
                    .'approves first.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'metric' => ['type' => 'string', 'description' => 'leads_added, leads_converted, calls_made or tasks_completed.'],
                        'target_value' => ['type' => 'number'],
                        'period' => ['type' => 'string', 'description' => 'YYYY-MM. Defaults to the current month.'],
                    ],
                    'required' => ['user_id', 'metric', 'target_value'],
                ],
            ],
            [
                'name' => 'cancel_subscription',
                'description' => 'Propose cancelling a tracked subscription flagged by the expense sentinel. '
                    .'Does NOT cancel anything — the founder approves, and cancelling with the vendor is '
                    .'still a human errand.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['subscription_id' => ['type' => 'integer']],
                    'required' => ['subscription_id'],
                ],
            ],
        ];
    }

    /** Tools that change something and therefore require a human click. */
    public const WRITE_TOOLS = ['send_message', 'create_task', 'set_target', 'cancel_subscription'];

    public function isWriteTool(string $name): bool
    {
        return in_array($name, self::WRITE_TOOLS, true);
    }

    /**
     * Run a read tool, or record a write tool as a pending proposal.
     *
     * @param  array<string, mixed>  $input
     * @return array{content: string, is_error: bool, action_id: int|null}
     */
    public function call(string $name, array $input): array
    {
        try {
            if ($this->isWriteTool($name)) {
                return $this->propose($name, $input);
            }

            return $this->ok(match ($name) {
                'get_business_snapshot' => $this->snapshot->all(),
                'list_team' => $this->listTeam(),
                'search_leads' => $this->searchLeads($input),
                'get_lead' => $this->getLead($input),
                'search_tasks' => $this->searchTasks($input),
                'search_expenses' => $this->searchExpenses($input),
                default => throw new \InvalidArgumentException("Unknown tool: {$name}"),
            });
        } catch (\Throwable $e) {
            report($e);

            // Handed back to the model as a tool result so it can adapt, rather
            // than thrown — a bad argument should not end the conversation.
            return ['content' => 'Tool error: '.$e->getMessage(), 'is_error' => true, 'action_id' => null];
        }
    }

    /**
     * @return array{content: string, is_error: bool, action_id: null}
     */
    private function ok(mixed $data): array
    {
        return [
            'content' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'is_error' => false,
            'action_id' => null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Read tools */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listTeam(): array
    {
        return User::with('role')
            ->where('is_active', true)
            ->where('employment_status', 'Active')
            ->orderBy('full_name')
            ->get()
            ->map(fn (User $u) => [
                'user_id' => $u->id,
                'name' => $u->full_name,
                'role' => $u->role?->role_name ?? '—',
                'role_code' => $u->role?->role_code,
                'designation' => $u->designation,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function searchLeads(array $input): array
    {
        $limit = $this->boundedLimit($input);

        return Lead::with(['status', 'assignee'])
            ->when($input['query'] ?? null, function ($q, string $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'like', "%{$term}%")
                        ->orWhere('mobile', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($input['status'] ?? null, fn ($q, string $slug) => $q->whereHas('status', fn ($s) => $s->where('slug', $slug)))
            ->when($input['source'] ?? null, fn ($q, string $src) => $q->where('source', 'like', "%{$src}%"))
            ->when($input['assigned_to_user_id'] ?? null, fn ($q, $id) => $q->where('assigned_to_user_id', $id))
            ->when(($input['unassigned'] ?? false) === true, fn ($q) => $q->whereNull('assigned_to_user_id'))
            ->when($input['created_within_days'] ?? null, fn ($q, $days) => $q->where('created_at', '>=', now()->subDays((int) $days)))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead) => [
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'mobile' => $lead->mobile,
                'course' => $lead->interested_course_slug,
                'source' => $lead->source,
                'status' => $lead->status?->name,
                'assigned_to' => $lead->assignee?->full_name,
                'created_at' => $lead->created_at?->toDateString(),
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function getLead(array $input): array
    {
        $lead = Lead::with(['status', 'assignee', 'calls.initiatedBy', 'statusHistory.status', 'statusHistory.changedBy'])
            ->findOrFail((int) $input['lead_id']);

        return [
            'lead_id' => $lead->id,
            'name' => $lead->name,
            'mobile' => $lead->mobile,
            'email' => $lead->email,
            'source' => $lead->source,
            'campaign' => $lead->campaign_name,
            'course' => $lead->interested_course_slug,
            'status' => $lead->status?->name,
            'assigned_to' => $lead->assignee?->full_name,
            'created_at' => $lead->created_at?->toDateTimeString(),
            'masterclass_link_sent_at' => $lead->masterclass_link_sent_at?->toDateTimeString(),
            'allocated_batch_number' => $lead->allocated_batch_number,
            // Transcripts are deliberately excluded — they are long, and the
            // lead page already has an AI analysis feature built for them.
            'calls' => $lead->calls->sortBy('started_at')->values()->map(fn ($c) => [
                'type' => $c->type,
                'status' => $c->status,
                'disposition' => $c->disposition,
                'sentiment' => $c->sentiment,
                'duration_seconds' => $c->duration_seconds,
                'at' => $c->started_at?->toDateTimeString(),
                'by' => $c->initiatedBy?->full_name ?? 'System',
            ])->all(),
            'status_history' => $lead->statusHistory->sortBy('created_at')->values()->map(fn ($h) => [
                'status' => $h->status?->name,
                'at' => $h->created_at?->toDateTimeString(),
                'by' => $h->changedBy?->full_name ?? 'System',
                'remarks' => $h->remarks,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function searchTasks(array $input): array
    {
        return Task::with(['assignees', 'creator'])
            ->when($input['assignee_id'] ?? null, fn ($q, $id) => $q->whereHas('assignees', fn ($a) => $a->where('users.id', $id)))
            ->when($input['status'] ?? null, fn ($q, string $s) => $q->where('status', $s))
            ->when(($input['overdue_only'] ?? false) === true, fn ($q) => $q->where('status', '!=', 'completed')->whereDate('due_date', '<', today()))
            ->latest()
            ->limit($this->boundedLimit($input))
            ->get()
            ->map(fn (Task $t) => [
                'task_id' => $t->id,
                'title' => $t->title,
                'due_date' => $t->due_date?->toDateString(),
                'priority' => $t->priority,
                'status' => $t->status,
                'is_overdue' => $t->status !== 'completed' && $t->due_date?->isPast(),
                'created_by' => $t->creator?->full_name,
                'assignees' => $t->assignees->map(fn ($a) => [
                    'name' => $a->full_name,
                    'completed' => (bool) $a->pivot->is_completed,
                ])->all(),
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function searchExpenses(array $input): array
    {
        $days = (int) ($input['within_days'] ?? 30);

        return Expense::with('creator')
            ->where('status', '!=', 'cancelled')
            ->where('expense_date', '>=', now()->subDays($days)->startOfDay()->toDateTimeString())
            ->when($input['category'] ?? null, fn ($q, string $c) => $q->where('category', 'like', "%{$c}%"))
            ->orderByDesc('expense_date')
            ->limit($this->boundedLimit($input))
            ->get()
            ->map(fn (Expense $e) => [
                'expense_id' => $e->id,
                'title' => $e->title,
                'category' => $e->category,
                'amount_inr' => (float) $e->amount,
                'date' => $e->expense_date?->toDateString(),
                'vendor' => $e->vendor,
                'status' => $e->status,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function boundedLimit(array $input): int
    {
        return max(1, min(50, (int) ($input['limit'] ?? 20)));
    }

    /* ------------------------------------------------------------------ */
    /* Write tools — propose only */
    /* ------------------------------------------------------------------ */

    /**
     * Validate the model's arguments now (so a bad proposal fails while the
     * model can still correct itself), then park them for approval.
     *
     * @param  array<string, mixed>  $input
     * @return array{content: string, is_error: bool, action_id: int|null}
     */
    private function propose(string $name, array $input): array
    {
        $validator = Validator::make($input, $this->rulesFor($name));

        if ($validator->fails()) {
            return [
                'content' => 'Invalid arguments: '.implode(' ', $validator->errors()->all()),
                'is_error' => true,
                'action_id' => null,
            ];
        }

        $payload = $validator->validated();

        $action = TaurusAction::create([
            'action' => $name,
            'payload' => $payload,
            'summary' => $this->summarise($name, $payload),
            'status' => TaurusAction::STATUS_PENDING,
            'requested_by_user_id' => Auth::id(),
        ]);

        return [
            'content' => json_encode([
                'queued_for_approval' => true,
                'action_id' => $action->id,
                'summary' => $action->summary,
                'note' => 'Nothing has happened yet. Tell the founder exactly what you are about to do '
                    .'and that it is waiting on their approval.',
            ]),
            'is_error' => false,
            'action_id' => $action->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(string $name): array
    {
        return match ($name) {
            'send_message' => [
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'body' => ['required', 'string', 'max:5000'],
            ],
            'create_task' => [
                'title' => ['required', 'string', 'max:150'],
                'description' => ['nullable', 'string', 'max:2000'],
                'due_date' => ['required', 'date'],
                'priority' => ['required', 'in:low,medium,high'],
                'assignee_ids' => ['required', 'array', 'min:1'],
                'assignee_ids.*' => ['integer', 'exists:users,id'],
            ],
            'set_target' => [
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'metric' => ['required', 'in:'.implode(',', array_keys(config('taurus.target_metrics')))],
                'target_value' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'period' => ['nullable', 'date_format:Y-m'],
            ],
            'cancel_subscription' => [
                'subscription_id' => ['required', 'integer', 'exists:taurus_subscriptions,id'],
            ],
            default => [],
        };
    }

    /**
     * A frozen, human-readable description of the proposal. Shown on the
     * confirmation card and kept on the record after the decision, so the
     * audit trail does not depend on rows that may later change.
     *
     * @param  array<string, mixed>  $payload
     */
    private function summarise(string $name, array $payload): string
    {
        return match ($name) {
            'send_message' => 'Message '.$this->userName($payload['user_id']).': "'
                .Str::limit($payload['body'], 160).'"',
            'create_task' => 'Create task "'.$payload['title'].'" ('.$payload['priority'].', due '
                .$payload['due_date'].') for '.$this->userNames($payload['assignee_ids']),
            'set_target' => 'Set '.$this->userName($payload['user_id']).'\'s '
                .config("taurus.target_metrics.{$payload['metric']}", $payload['metric'])
                .' target to '.$payload['target_value'].' for '.($payload['period'] ?? now()->format('Y-m')),
            'cancel_subscription' => 'Cancel '
                .(TaurusSubscription::find($payload['subscription_id'])?->name ?? 'subscription')
                .' — flagged as unused',
            default => $name,
        };
    }

    private function userName(int $id): string
    {
        return User::find($id)?->full_name ?? "user #{$id}";
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function userNames(array $ids): string
    {
        return User::whereIn('id', $ids)->pluck('full_name')->implode(', ') ?: 'nobody';
    }

    /* ------------------------------------------------------------------ */
    /* Execution — only ever reached from an approved action */
    /* ------------------------------------------------------------------ */

    /**
     * Carry out an approved proposal. Arguments come from the stored payload,
     * never from the current request, so what runs is exactly what the founder
     * saw and approved.
     */
    public function execute(TaurusAction $action): string
    {
        $payload = $action->payload ?? [];

        return match ($action->action) {
            'send_message' => $this->sendMessage($payload),
            'create_task' => $this->createTask($payload),
            'set_target' => $this->setTarget($payload),
            'cancel_subscription' => $this->cancelSubscription($payload),
            default => throw new \RuntimeException("Cannot execute unknown action: {$action->action}"),
        };
    }

    /**
     * Sends as the approving user through the CRM's own chat, reusing their
     * private conversation if one exists — the recipient sees a normal message
     * from the founder, and it appears in the chat screen like any other.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendMessage(array $payload): string
    {
        $sender = Auth::user();
        $recipient = User::findOrFail($payload['user_id']);

        if ($recipient->id === $sender->id) {
            throw new \RuntimeException('That message is addressed to you.');
        }

        $conversation = Conversation::where('type', 'private')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $sender->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $recipient->id))
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create(['type' => 'private', 'created_by' => $sender->id]);
            $conversation->participants()->attach([$sender->id, $recipient->id]);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $payload['body'],
        ]);

        $conversation->touch();
        Notification::send($recipient, new NewChatMessage($message));

        return 'Message sent to '.$recipient->full_name.'.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createTask(array $payload): string
    {
        $task = Task::create([
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'due_date' => $payload['due_date'],
            'priority' => $payload['priority'],
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        $task->assignees()->attach(
            collect($payload['assignee_ids'])->mapWithKeys(fn ($id) => [$id => ['is_completed' => false]])->all()
        );

        Notification::send(User::whereIn('id', $payload['assignee_ids'])->get(), new TaskAssigned($task));

        return 'Task "'.$task->title.'" created and assigned.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function setTarget(array $payload): string
    {
        TaurusTarget::updateOrCreate(
            [
                'user_id' => $payload['user_id'],
                'metric' => $payload['metric'],
                'period' => $payload['period'] ?? now()->format('Y-m'),
            ],
            ['target_value' => $payload['target_value'], 'set_by_user_id' => Auth::id()]
        );

        return 'Target saved.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cancelSubscription(array $payload): string
    {
        $sub = TaurusSubscription::findOrFail($payload['subscription_id']);
        $sub->update(['is_active' => false, 'updated_by' => Auth::id()]);

        return $sub->name.' marked inactive. Cancel it with the vendor to stop the billing.';
    }

    /**
     * Recent chat the founder has had with the team, so Taurus can answer
     * "what did I tell Meera" without a tool round trip. Read-only.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentMessages(int $limit = 15): array
    {
        $userId = Auth::id();

        return Message::with(['sender', 'conversation.participants'])
            ->whereHas('conversation.participants', fn ($q) => $q->where('users.id', $userId))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Message $m) => [
                'from' => $m->sender?->full_name,
                'to' => $m->conversation?->participants
                    ->where('id', '!=', $m->sender_id)->pluck('full_name')->implode(', '),
                'body' => Str::limit($m->body, 200),
                'at' => $m->created_at?->diffForHumans(),
            ])->all();
    }

    /**
     * Sanity check used by the tests: every declared tool must be either a
     * known write tool or have a handler in call().
     */
    public function declaredToolNames(): array
    {
        return array_column($this->definitions(), 'name');
    }
}
