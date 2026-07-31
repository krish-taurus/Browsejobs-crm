<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MockBlueprint extends LmsModel
{
    protected $table = 'mock_blueprints';

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function mockInterviews(): HasMany
    {
        return $this->hasMany(MockInterview::class);
    }
}
