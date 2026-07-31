<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends LmsModel
{
    protected $table = 'topics';

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
