<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Lms\LmsLead;
use App\Models\Lms\LmsUser;
use App\Services\LeadService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Pull every LMS acquisition channel into the CRM:
 *  - leads captured on the LMS site (masterclass bookings, counselling,
 *    bootcamp forms, syllabus downloads), and
 *  - student accounts created via open registration (/register), which the
 *    LMS stores as users only — no lead row.
 *
 * Imported leads start in "New" status, which fires the normal new-lead
 * pipeline: team notifications + the AI auto-call.
 *
 * Dedupe is two-layer: the exact LMS lead id (leads.lms_lead_id), plus the
 * last-10-digit phone match so a person who exists from another source
 * (Meta ads, manual entry) is never imported twice and never double-dialled.
 */
class SyncLeadsFromLms extends Command
{
    protected $signature = 'leads:sync-lms {--limit=100 : Max leads to import per run}';

    protected $description = 'Import new LMS leads into the CRM (status New → AI auto-call pipeline).';

    public function handle(LeadService $leads): int
    {
        try {
            $syncedIds = Lead::whereNotNull('lms_lead_id')->pluck('lms_lead_id');

            $pending = LmsLead::query()
                ->whereNull('merged_into_id')
                ->whereNotIn('id', $syncedIds)
                ->orderBy('id')
                ->limit((int) $this->option('limit'))
                ->get();

            $registrations = LmsUser::students()
                ->whereNotNull('phone')
                ->whereNull('anonymized_at')
                ->orderBy('id')
                ->limit((int) $this->option('limit'))
                ->get();
        } catch (QueryException|\PDOException $e) {
            $this->error('LMS database not reachable — skipping this run.');
            report($e);

            return self::FAILURE;
        }

        if ($pending->isEmpty() && $registrations->isEmpty()) {
            $this->info('No new LMS leads to import.');

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($pending as $lmsLead) {
            $phone = $lmsLead->phone_normalized ?: $lmsLead->phone;

            if (blank($phone)) {
                $skipped++;

                continue;
            }

            $existing = $this->findByPhone($phone);

            if ($existing) {
                // Same person already in the CRM from another source — just link it
                // so this LMS lead is never considered again.
                if (! $existing->lms_lead_id) {
                    $existing->update(['lms_lead_id' => $lmsLead->id]);
                }
                $skipped++;

                continue;
            }

            $lead = $leads->create([
                'mobile' => $phone,
                'name' => $lmsLead->name,
                'email' => $lmsLead->email,
                'campaign_name' => $lmsLead->utm_campaign ?: $lmsLead->course_slug,
            ], 'lms', null);

            $lead->update(['lms_lead_id' => $lmsLead->id]);

            $imported++;
            $this->line("Imported LMS lead #{$lmsLead->id} ({$lmsLead->name}) as CRM lead #{$lead->id}");
        }

        // Open registrations: an account created on /register is a lead too —
        // the LMS never writes a lead row for them, so import from users.
        // Phone-match dedupe alone carries these (no lms_lead_id id-space).
        foreach ($registrations as $lmsUser) {
            if ($this->findByPhone($lmsUser->phone)) {
                $skipped++;

                continue;
            }

            $lead = $leads->create([
                'mobile' => $lmsUser->phone,
                'name' => $lmsUser->name,
                'email' => $lmsUser->email,
                'campaign_name' => 'registration',
            ], 'lms', null);

            $imported++;
            $this->line("Imported LMS registration user #{$lmsUser->id} ({$lmsUser->name}) as CRM lead #{$lead->id}");
        }

        $this->info("Done: {$imported} imported, {$skipped} skipped (already in CRM or no phone).");

        return self::SUCCESS;
    }

    /**
     * Match an existing CRM lead by the last 10 digits of the phone number,
     * so +91 98765 43210, 09876543210 and 9876543210 all count as the same person.
     */
    private function findByPhone(string $phone): ?Lead
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $last10 = substr($digits, -10);

        if (strlen($last10) < 10) {
            return Lead::where('mobile', $phone)->first();
        }

        return Lead::where('mobile', $phone)
            ->orWhere('mobile', 'like', "%{$last10}")
            ->first();
    }
}
