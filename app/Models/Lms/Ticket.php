<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends LmsModel
{
    protected $table = 'tickets';

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'first_response_due_at' => 'datetime',
            'breached_at' => 'datetime',
            'ai_triaged_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'student_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }
}
