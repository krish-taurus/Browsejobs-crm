<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentScore extends LmsModel
{
    protected $table = 'student_scores';

    protected function casts(): array
    {
        return [
            'mastery' => 'array',
            'next_action' => 'array',
            'red_flags' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }
}
