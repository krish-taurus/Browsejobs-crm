<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends LmsModel
{
    protected $table = 'attendance';

    protected function casts(): array
    {
        return [
            'first_joined_at' => 'datetime',
            'last_left_at' => 'datetime',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }
}
