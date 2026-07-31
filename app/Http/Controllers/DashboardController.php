<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\LeaveRequest;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Roles that see the full operations dashboard. */
    private const ADMIN_ROLE_CODES = ['SUPER_ADMIN', 'ADMIN', 'HEAD_OF_OPERATIONS'];

    private const BIRTHDAY_QUOTES = [
        'Another year older, another year wiser, another year more fabulous.',
        'Count your life by smiles, not tears. Count your age by friends, not years.',
        'Today you are you, that is truer than true. There is no one alive who is you-er than you!',
        'May your day be filled with sunshine, laughter, and just the right amount of cake.',
        "Growing old is mandatory. Growing wise is optional — but clearly, you're nailing both.",
        "Here's to another trip around the sun, and all the adventures still to come.",
    ];

    public function index(): View
    {
        $user = Auth::user();

        // Non-admin roles get a dashboard tailored to their job instead of the ops dashboard.
        if (! in_array($user?->role?->role_code, self::ADMIN_ROLE_CODES, true)) {
            return $this->roleDashboard($user);
        }

        return $this->adminDashboard();
    }

    private function adminDashboard(): View
    {
        $today = Carbon::today();

        // ---- On Leave Today ----
        $onLeaveToday = LeaveRequest::with(['user', 'leaveType'])
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $today)
            ->whereDate('to_date', '>=', $today)
            ->get();

        // ---- Birthdays — today + next 7 days ----
        $usersWithDob = User::whereNotNull('date_of_birth')
            ->where('employment_status', 'Active')
            ->get(['id', 'full_name', 'date_of_birth', 'profile_photo']);

        $birthdaysUpcoming = $usersWithDob->map(function (User $user) use ($today) {
            $occurrence = Carbon::create($today->year, $user->date_of_birth->month, $user->date_of_birth->day);

            if ($occurrence->month !== $user->date_of_birth->month) {
                $occurrence = Carbon::create($today->year, 2, 28);
            }

            if ($occurrence->lt($today->copy()->startOfDay())) {
                $occurrence = $occurrence->addYear();
            }

            $user->next_birthday = $occurrence;
            $user->days_until_birthday = $today->diffInDays($occurrence, false);

            return $user;
        })
            ->filter(fn (User $u) => $u->days_until_birthday >= 0 && $u->days_until_birthday <= 7)
            ->sortBy('days_until_birthday')
            ->values();

        // Extracted separately for the celebration banner — no extra query needed.
        $birthdaysToday = $birthdaysUpcoming->where('days_until_birthday', 0)->values();
        $birthdayQuote = self::BIRTHDAY_QUOTES[array_rand(self::BIRTHDAY_QUOTES)];

        // ---- Incomplete / Overdue Tasks ----
        $overdueTasks = Task::with(['assignees' => function ($q) {
            $q->wherePivot('is_completed', false);
        }, 'creator'])
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', '<', $today)
            ->get()
            ->filter(fn (Task $t) => $t->assignees->isNotEmpty())
            ->map(function (Task $task) use ($today) {
                $task->days_overdue = (int) floor(Carbon::parse($task->due_date)->diffInDays($today));

                return $task;
            });

        $dueTodayTasks = Task::with(['assignees' => function ($q) {
            $q->wherePivot('is_completed', false);
        }])
            ->where('status', '!=', 'completed')
            ->whereDate('due_date', '=', $today)
            ->get()
            ->filter(fn (Task $t) => $t->assignees->isNotEmpty());

        // ---- Summary stat cards ----
        $totalEmployees = User::where('employment_status', 'Active')->count();
        $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count();
        $overdueTaskCount = $overdueTasks->count();

        // ---- Attendance trend: distinct logins per day, last 7 days ----
        $attLabels = [];
        $attData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $attLabels[] = $i === 0 ? 'Today' : $day->format('D');
            $attData[] = DB::table('user_login_logs')
                ->whereDate('login_time', $day)
                ->distinct()
                ->count('user_id');
        }
        $attendanceTrend = ['labels' => $attLabels, 'data' => $attData];

        // ---- Leave breakdown: approved leave days by type, this year ----
        $breakdown = DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', 'approved')
            ->whereYear('leave_requests.from_date', $today->year)
            ->selectRaw('leave_types.name as name, SUM(leave_requests.total_days) as days')
            ->groupBy('leave_types.name')
            ->orderByDesc('days')
            ->get();

        $leaveTypeBreakdown = [
            'labels' => $breakdown->pluck('name'),
            'data' => $breakdown->pluck('days')->map(fn ($v) => (float) $v),
        ];

        return view('dashboard', [
            'onLeaveToday' => $onLeaveToday,
            'birthdaysUpcoming' => $birthdaysUpcoming,
            'birthdaysToday' => $birthdaysToday,
            'birthdayQuote' => $birthdayQuote,
            'overdueTasks' => $overdueTasks,
            'dueTodayTasks' => $dueTodayTasks,
            'totalEmployees' => $totalEmployees,
            'pendingLeaveRequests' => $pendingLeaveRequests,
            'overdueTaskCount' => $overdueTaskCount,
            'onLeaveTodayCount' => $onLeaveToday->count(),
            'attendanceTrend' => $attendanceTrend,
            'leaveTypeBreakdown' => $leaveTypeBreakdown,
        ]);
    }

    /**
     * A role-appropriate dashboard for non-admin users.
     */
    private function roleDashboard(User $user): View
    {
        $code = $user->role?->role_code;

        // The HR Manager supervises the callers rather than working a lead list
        // of their own, so they get a monitoring dashboard, not "my leads".
        if ($code === 'HR_MANAGER') {
            return $this->hrManagerDashboard($user);
        }

        $group = match (true) {
            in_array($code, ['SALES_MANAGER', 'SALES_EXECUTIVE'], true) => 'sales',
            in_array($code, ['HR', 'HR_TEAM_LEAD', 'HR_ADMIN'], true) => 'hr',
            in_array($code, ['MARKETING_MANAGER', 'MARKETING_EXECUTIVE', 'SOCIAL_MEDIA_MANAGER', 'MEDIA_STRATEGIST'], true) => 'marketing',
            in_array($code, ['TRAINER', 'STUDENT_COUNSELOR', 'DATA_ENGINEER_TRAINER', 'TECH_MENTOR', 'TECH_MANAGER'], true) => 'training',
            default => 'personal',
        };

        $uid = $user->id;
        $loggedInToday = DB::table('user_login_logs')->where('user_id', $uid)->whereDate('login_time', today())->exists();
        $myTodos = DB::table('todos')->where('user_id', $uid)->where('is_completed', false)->count();
        $myLeads = Lead::with('status')->where('assigned_to_user_id', $uid)->latest()->limit(6)->get();
        $myLeadCount = Lead::where('assigned_to_user_id', $uid)->count();

        // My own pipeline, so the dashboard says something about the work
        // rather than just linking away to it.
        $myPipeline = Lead::query()
            ->where('leads.assigned_to_user_id', $uid)
            ->join('lead_statuses', 'lead_statuses.id', '=', 'leads.current_status_id')
            ->groupBy('lead_statuses.id', 'lead_statuses.name', 'lead_statuses.color', 'lead_statuses.sort_order')
            ->orderBy('lead_statuses.sort_order')
            ->get([
                'lead_statuses.name',
                'lead_statuses.color',
                DB::raw('COUNT(*) as total'),
            ]);

        // The same "needs chasing" rule the HR Performance board uses, scoped
        // to this person: assigned over a day ago, still open, nothing logged.
        $myUntouched = Lead::where('assigned_to_user_id', $uid)
            ->where('assigned_at', '<', now()->subDay())
            ->whereDoesntHave('status', fn ($q) => $q->whereIn('slug', ['joined', 'lost', 'not_interested', 'invalid_number']))
            ->whereDoesntHave('calls', fn ($c) => $c->where('initiated_by_user_id', $uid))
            ->whereDoesntHave('statusHistory', fn ($h) => $h->where('changed_by_user_id', $uid))
            ->count();

        $interestedId = LeadStatus::where('slug', 'interested')->value('id');
        $newId = LeadStatus::where('slug', 'new')->value('id');

        $cards = match ($group) {
            'sales' => [
                $this->card('Total Leads', Lead::count(), 'ti-users', 'primary', route('leads.index')),
                $this->card('New Leads', $newId ? Lead::where('current_status_id', $newId)->count() : 0, 'ti-user-plus', 'info', route('leads.index', ['status_id' => $newId])),
                $this->card('Interested', $interestedId ? Lead::where('current_status_id', $interestedId)->count() : 0, 'ti-flame', 'success', route('leads.index', ['status_id' => $interestedId])),
                $this->card('My Leads', $myLeadCount, 'ti-user-check', 'warning', route('leads.index', ['assignment' => 'mine'])),
            ],
            'hr' => [
                $this->card('My Leads to Call', $myLeadCount, 'ti-phone-call', 'primary', route('leads.index', ['assignment' => 'mine'])),
                $this->card('Interested & Unassigned', $interestedId ? Lead::where('current_status_id', $interestedId)->whereNull('assigned_to_user_id')->count() : 0, 'ti-flame', 'danger', route('leads.index', ['status_id' => $interestedId, 'assignment' => 'unassigned'])),
                $this->card('On Leave Today', LeaveRequest::where('status', 'approved')->whereDate('from_date', '<=', today())->whereDate('to_date', '>=', today())->count(), 'ti-calendar-off', 'warning', route('leave-requests.index')),
                $this->card('My To-Dos', $myTodos, 'ti-checklist', 'info', route('todos.index')),
            ],
            'marketing' => [
                $this->card('Campaigns', DB::table('campaigns')->count(), 'ti-send', 'primary', route('campaigns.index')),
                $this->card('Total Students', DB::table('students')->count(), 'ti-users', 'info', url('students')),
                $this->card('Active Students', DB::table('students')->where('status', 'active')->count(), 'ti-user-check', 'success', url('students')),
                $this->card('Social Followers', (int) DB::table('social_accounts')->sum('followers_count'), 'ti-heart', 'danger', route('social-analytics.index')),
            ],
            'training' => [
                $this->card('Total Students', DB::table('students')->count(), 'ti-users', 'primary', url('students')),
                $this->card('Active Students', DB::table('students')->where('status', 'active')->count(), 'ti-user-check', 'success', url('students')),
                $this->card('My To-Dos', $myTodos, 'ti-checklist', 'info', route('todos.index')),
                $this->card('My Tasks', DB::table('task_assignees')->where('user_id', $uid)->where('is_completed', false)->count(), 'ti-list-check', 'warning', route('tasks.index')),
            ],
            default => [
                $this->card('My To-Dos', $myTodos, 'ti-checklist', 'primary', route('todos.index')),
                $this->card('My Leads', $myLeadCount, 'ti-user-check', 'info', route('leads.index', ['assignment' => 'mine'])),
                $this->card('My Notes', DB::table('notes')->where('user_id', $uid)->count(), 'ti-notes', 'success', route('notes.index')),
                $this->card('Attendance', $loggedInToday ? 'Present' : 'Not logged in', 'ti-clock', $loggedInToday ? 'success' : 'danger', route('attendance.index')),
            ],
        };

        return view('dashboard-role', [
            'user' => $user,
            'roleName' => $user->role?->role_name ?? 'Team Member',
            'group' => $group,
            'cards' => $cards,
            'myLeads' => $myLeads,
            'myLeadCount' => $myLeadCount,
            'myPipeline' => $myPipeline,
            'myUntouched' => $myUntouched,
            'loggedInToday' => $loggedInToday,
        ]);
    }

    /** Roles that actually call leads — the people an HR Manager supervises. */
    private const CALLER_ROLE_CODES = ['HR', 'HR_TEAM_LEAD', 'HR_ADMIN'];

    /**
     * The HR Manager's view: how the calling team is doing, and what still
     * needs handing out. Never "my leads" — a manager carries no lead list.
     */
    private function hrManagerDashboard(User $user): View
    {
        $callers = User::query()
            ->withRoleCode(self::CALLER_ROLE_CODES)
            ->where('is_active', true)
            ->get(['id', 'full_name', 'first_name', 'role_id']);

        $callerIds = $callers->pluck('id');

        $assigned = Lead::whereIn('assigned_to_user_id', $callerIds)->count();

        $untouched = Lead::whereIn('assigned_to_user_id', $callerIds)
            ->where('assigned_at', '<', now()->subDay())
            ->whereDoesntHave('status', fn ($q) => $q->whereIn('slug', ['joined', 'lost', 'not_interested', 'invalid_number']))
            ->whereDoesntHave('calls', fn ($c) => $c->whereColumn('lead_calls.initiated_by_user_id', 'leads.assigned_to_user_id'))
            ->whereDoesntHave('statusHistory', fn ($h) => $h->whereColumn('lead_status_history.changed_by_user_id', 'leads.assigned_to_user_id'))
            ->count();

        $unassigned = Lead::whereNull('assigned_to_user_id')
            ->whereDoesntHave('status', fn ($q) => $q->whereIn('slug', ['joined', 'lost', 'not_interested', 'invalid_number']))
            ->count();

        $joinedId = LeadStatus::where('slug', 'joined')->value('id');
        $joinedThisMonth = $joinedId
            ? Lead::where('current_status_id', $joinedId)->where('updated_at', '>=', now()->startOfMonth())->count()
            : 0;

        // Per-caller snapshot — the same definition the HR Performance board
        // uses, kept deliberately short here (top 6 by open workload).
        $team = $callers->map(function (User $caller) {
            $base = fn () => Lead::where('assigned_to_user_id', $caller->id);

            $assigned = $base()->count();
            $pending = $base()
                ->whereDoesntHave('status', fn ($q) => $q->whereIn('slug', ['joined', 'lost', 'not_interested', 'invalid_number']))
                ->whereDoesntHave('calls', fn ($c) => $c->where('initiated_by_user_id', $caller->id))
                ->whereDoesntHave('statusHistory', fn ($h) => $h->where('changed_by_user_id', $caller->id))
                ->count();

            return (object) [
                'user' => $caller,
                'assigned' => $assigned,
                'pending' => $pending,
                'joined' => $base()->whereHas('status', fn ($q) => $q->where('slug', 'joined'))->count(),
                'done_pct' => $assigned > 0 ? (int) round(($assigned - $pending) / $assigned * 100) : null,
            ];
        })->sortByDesc('pending')->values();

        $unassignedLeads = Lead::with('status')
            ->whereNull('assigned_to_user_id')
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard-hr-manager', [
            'user' => $user,
            'roleName' => $user->role?->role_name ?? 'HR Manager',
            'loggedInToday' => DB::table('user_login_logs')->where('user_id', $user->id)->whereDate('login_time', today())->exists(),
            'callerCount' => $callers->count(),
            'assigned' => $assigned,
            'untouched' => $untouched,
            'unassigned' => $unassigned,
            'joinedThisMonth' => $joinedThisMonth,
            'team' => $team,
            'unassignedLeads' => $unassignedLeads,
            'onLeaveToday' => LeaveRequest::with('user')
                ->where('status', 'approved')
                ->whereDate('from_date', '<=', today())
                ->whereDate('to_date', '>=', today())
                ->get(),
        ]);
    }

    /**
     * @return array{label: string, value: mixed, icon: string, color: string, url: string}
     */
    private function card(string $label, mixed $value, string $icon, string $color, string $url): array
    {
        return ['label' => $label, 'value' => $value, 'icon' => $icon, 'color' => $color, 'url' => $url];
    }
}
