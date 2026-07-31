<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssignmentSubmission extends LmsModel
{
    protected $table = 'assignment_submissions';

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function grade(): HasOne
    {
        return $this->hasOne(AssignmentGrade::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }
}
