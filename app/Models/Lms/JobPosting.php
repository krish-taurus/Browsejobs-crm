<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A staff-curated job opening students can apply to (read-only mirror).
 */
class JobPosting extends LmsModel
{
    protected $table = 'job_postings';

    protected function casts(): array
    {
        return [
            'closes_on' => 'date',
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
