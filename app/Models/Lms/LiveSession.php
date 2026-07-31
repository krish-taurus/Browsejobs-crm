<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveSession extends LmsModel
{
    protected $table = 'live_sessions';

    protected function casts(): array
    {
        return [
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'host_user_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'live_session_id');
    }
}
