<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's application to a curated job posting (read-only mirror).
 * Statuses: applied | shortlisted | interviewing | offer | placed | rejected | withdrawn
 */
class JobApplication extends LmsModel
{
    protected $table = 'job_applications';

    protected function casts(): array
    {
        return [
            'offer_accepted_at' => 'datetime',
            'placed_at' => 'datetime',
        ];
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(JobApplicationRound::class)->orderBy('round_no');
    }
}
