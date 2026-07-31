<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends LmsModel
{
    protected $table = 'payments';

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }

    public function feePlan(): BelongsTo
    {
        return $this->belongsTo(FeePlan::class);
    }

    public function instalment(): BelongsTo
    {
        return $this->belongsTo(Instalment::class);
    }
}
