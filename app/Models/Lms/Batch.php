<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends LmsModel
{
    protected $table = 'batches';

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'trainer_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(BatchMember::class);
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function feePlans(): HasMany
    {
        return $this->hasMany(FeePlan::class);
    }
}
