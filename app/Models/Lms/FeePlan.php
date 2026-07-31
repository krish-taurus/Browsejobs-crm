<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePlan extends LmsModel
{
    protected $table = 'fee_plans';

    public function user(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function instalments(): HasMany
    {
        return $this->hasMany(Instalment::class)->orderBy('seq');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
