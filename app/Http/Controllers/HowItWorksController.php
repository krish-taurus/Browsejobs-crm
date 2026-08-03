<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * "How It Works" — a plain-language, visual explanation of the whole business
 * as it is actually wired: what the CRM owns, what the LMS owns, the bridges
 * between them, the journey a person takes from first enquiry to certificate,
 * and exactly who gets notified on which channel at each step.
 *
 * Deliberately has no database dependency so it always renders, even when the
 * LMS connection is down — this is the page someone opens to understand the
 * system, including when something else is broken.
 */
class HowItWorksController extends Controller
{
    public function index(): View
    {
        return view('how-it-works.index', [
            'leadStages' => self::LEAD_STAGES,
            'studentStages' => self::STUDENT_STAGES,
            'notifications' => self::NOTIFICATIONS,
            'schedule' => self::SCHEDULE,
            'bridges' => self::BRIDGES,
        ]);
    }

    /**
     * The lead funnel, in the order it happens. `owner` is which system does
     * the work; `auto` marks steps that need no human.
     */
    private const LEAD_STAGES = [
        [
            'status' => 'New',
            'colour' => '#1b6df0',
            'icon' => 'ti-user-plus',
            'auto' => true,
            'what' => 'A lead arrives — website form, masterclass booking, ad campaign, or added by hand.',
            'then' => 'The team is alerted instantly and the AI agent is queued to call.',
        ],
        [
            'status' => 'AI Call Running',
            'colour' => '#7c3aed',
            'icon' => 'ti-robot',
            'auto' => true,
            'what' => 'The AI agent phones the lead, has the first conversation and scores it.',
            'then' => 'The call result writes back automatically — transcript, outcome and sentiment.',
        ],
        [
            'status' => 'Interested',
            'colour' => '#0ba860',
            'icon' => 'ti-flame',
            'auto' => false,
            'what' => 'The lead wants to know more. An HR caller is assigned to them.',
            'then' => 'The masterclass link is sent, and the caller follows up personally.',
        ],
        [
            'status' => 'Follow-up',
            'colour' => '#f59e0b',
            'icon' => 'ti-phone-call',
            'auto' => false,
            'what' => 'Not decided yet — needs another conversation.',
            'then' => 'Stays on the caller\'s list. Untouched after 24 hours it shows as overdue.',
        ],
        [
            'status' => 'Joined',
            'colour' => '#0891b2',
            'icon' => 'ti-check',
            'auto' => false,
            'what' => 'They enrol. This is the handover point between the two systems.',
            'then' => 'A student account and batch seat are created in the LMS.',
        ],
        [
            'status' => 'Closed',
            'colour' => '#6c757d',
            'icon' => 'ti-x',
            'auto' => false,
            'what' => 'Not Interested, Invalid Number or Lost — the lead stops here.',
            'then' => 'Removed from follow-up lists so nobody wastes calls on it.',
        ],
    ];

    /** Life after "Joined" — everything the student experiences. */
    private const STUDENT_STAGES = [
        ['title' => 'Enrolled in a batch', 'icon' => 'ti-users', 'colour' => '#1b6df0',
            'body' => 'A seat in a live batch with a start and end date, and a trainer.'],
        ['title' => 'Fee plan created', 'icon' => 'ti-receipt-rupee', 'colour' => '#0ba860',
            'body' => 'Paid in full or as instalments. Each instalment has its own due date and payment link.'],
        ['title' => 'Live classes', 'icon' => 'ti-device-desktop', 'colour' => '#7c3aed',
            'body' => 'Zoom meetings created automatically, with reminders before each class.'],
        ['title' => 'Attendance tracked', 'icon' => 'ti-clipboard-check', 'colour' => '#0891b2',
            'body' => 'Join and leave times recorded per class, giving an attendance percentage.'],
        ['title' => 'Assignments & grading', 'icon' => 'ti-notebook', 'colour' => '#f59e0b',
            'body' => 'Students submit; the trainer grades. A grade stays private until it is released.'],
        ['title' => 'Mock interviews', 'icon' => 'ti-microphone', 'colour' => '#d64545',
            'body' => 'Practice rounds scored on real interview data, with a gap report.'],
        ['title' => 'Certificate', 'icon' => 'ti-certificate', 'colour' => '#0ba860',
            'body' => 'Issued on completion, with a QR code anyone can scan to verify it.'],
        ['title' => 'Placement', 'icon' => 'ti-briefcase', 'colour' => '#1b6df0',
            'body' => 'Job matching and applications. A win can go on the student wall, with consent.'],
    ];

    /**
     * How the CRM and the LMS stay in step. These are the only connections —
     * everything else in each system is independent.
     */
    private const BRIDGES = [
        ['title' => 'Leads come in from the website', 'dir' => 'LMS → CRM', 'every' => 'every 5 minutes',
            'body' => 'Forms, masterclass bookings and syllabus downloads on the public site become CRM leads.'],
        ['title' => 'The CRM reads student data live', 'dir' => 'LMS → CRM', 'every' => 'on every page load',
            'body' => 'Batches, attendance, fees and grades are read straight from the LMS — never copied, never stale.'],
        ['title' => 'The CRM drives LMS actions', 'dir' => 'CRM → LMS', 'every' => 'when you click',
            'body' => 'Planning a masterclass, adding a student or building a digest runs the real LMS logic, so Zoom links and reminders behave identically.'],
        ['title' => 'Website content is CRM-managed', 'dir' => 'CRM → Website', 'every' => 'within a minute',
            'body' => 'Homepage copy, page text and SEO are edited in the CRM and appear on the public site without a deploy.'],
        ['title' => 'The CRM keeps the LMS awake', 'dir' => 'CRM → LMS', 'every' => 'every minute',
            'body' => 'A heartbeat runs the LMS\'s own scheduled jobs and queue, so class reminders and fee ladders actually fire.'],
    ];

    /**
     * Who hears about what. Channels are the ones actually wired up:
     * in-app bell, browser push, email, WhatsApp and AI voice call.
     */
    private const NOTIFICATIONS = [
        ['event' => 'A new lead arrives', 'who' => 'Sales & HR team',
            'channels' => ['bell', 'push', 'email', 'whatsapp'], 'when' => 'Instantly'],
        ['event' => 'The AI agent calls a new lead', 'who' => 'The lead',
            'channels' => ['call'], 'when' => 'Within 10 minutes of arriving'],
        ['event' => 'A lead is assigned to you', 'who' => 'The assigned caller',
            'channels' => ['bell', 'push', 'email', 'whatsapp'], 'when' => 'Instantly'],
        ['event' => 'Masterclass link', 'who' => 'The lead',
            'channels' => ['whatsapp'], 'when' => 'When marked Interested'],
        ['event' => '"Did you watch it?" follow-up', 'who' => 'The lead',
            'channels' => ['whatsapp', 'call'], 'when' => 'Next day, once only'],
        ['event' => 'Class starting soon', 'who' => 'Students in the batch',
            'channels' => ['whatsapp'], 'when' => '12 hours, 2 hours and 5 minutes before'],
        ['event' => 'Instalment due or overdue', 'who' => 'The student',
            'channels' => ['whatsapp', 'email'], 'when' => 'Daily reminder ladder'],
        ['event' => 'Payment received', 'who' => 'Student & finance',
            'channels' => ['bell', 'email'], 'when' => 'When the gateway confirms it'],
        ['event' => 'Grade released', 'who' => 'The student',
            'channels' => ['bell'], 'when' => 'When the trainer approves it'],
        ['event' => 'Task overdue', 'who' => 'Assignee & manager',
            'channels' => ['bell', 'email'], 'when' => 'Daily at 9:00 am'],
        ['event' => 'Not logged in today', 'who' => 'The staff member, then admins',
            'channels' => ['whatsapp', 'email'], 'when' => '11:00 am, escalates at 5:30 pm'],
        ['event' => 'Daily standup', 'who' => 'Whole team',
            'channels' => ['push', 'whatsapp'], 'when' => '9:25 am on weekdays'],
    ];

    /** What runs on its own, so nobody has to remember it. */
    private const SCHEDULE = [
        ['time' => 'Every minute', 'items' => ['Send queued messages', 'Keep the LMS scheduler and queue running']],
        ['time' => 'Every 5–10 minutes', 'items' => ['Pull new website leads into the CRM', 'Call any New lead the system missed', 'Sync AI call results']],
        ['time' => 'Hourly', 'items' => ['Masterclass follow-ups', 'Class wrap-up and Zoom recordings', 'Support SLA checks']],
        ['time' => 'Early morning', 'items' => ['Move batches through the funnel (5:15)', 'Recompute student scores (6:00)', 'Build the Market Pulse digest (6:45)', 'Fee reminder ladder (7:00)']],
        ['time' => 'Working hours', 'items' => ['Standup reminder (9:25)', 'Overdue task flags (9:00)', 'Login nudge (11:00) and escalation (17:30)']],
        ['time' => 'Weekly & monthly', 'items' => ['Weekly student reports', 'Market Pulse email', 'Placement readiness recalibration']],
    ];
}
