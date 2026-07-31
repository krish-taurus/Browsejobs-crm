<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student win on the celebration wall. Only ever published with the
 * student's explicit consent (PRD §6.18) — consented_at is required.
 */
class Celebration extends LmsModel
{
    protected $table = 'celebrations';

    protected $fillable = [
        'tenant_id', 'student_id', 'display_mode', 'anonymous_label',
        'role_title', 'company', 'consented_at', 'created_by',
        'published_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'student_id');
    }

    /** What the wall shows — the real name only when the student chose "named". */
    public function displayName(): string
    {
        return $this->display_mode === 'anonymous'
            ? ($this->anonymous_label ?: 'A BrowseJobs student')
            : ($this->student?->name ?? 'A BrowseJobs student');
    }
}
