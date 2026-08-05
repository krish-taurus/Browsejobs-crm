<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One real interview round against a job application (read-only mirror).
 * Outcomes: advanced | rejected | pending | offer
 */
class JobApplicationRound extends LmsModel
{
    protected $table = 'job_application_rounds';

    protected function casts(): array
    {
        return [
            'happened_on' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }
}
