<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentGrade extends LmsModel
{
    protected $table = 'assignment_grades';

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }
}
