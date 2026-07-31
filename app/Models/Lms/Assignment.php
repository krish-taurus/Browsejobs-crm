<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends LmsModel
{
    protected $table = 'assignments';

    /**
     * Deliberate write exception to the read-only doctrine (like Product):
     * assignments are authored from the CRM Grading page.
     */
    protected $fillable = ['tenant_id', 'lesson_id', 'title', 'instructions', 'rubric', 'max_points', 'allow_link', 'allow_file'];

    protected function casts(): array
    {
        return [
            'rubric' => 'array',
            'allow_link' => 'boolean',
            'allow_file' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
