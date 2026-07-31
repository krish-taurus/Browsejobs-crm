<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockInterview extends LmsModel
{
    protected $table = 'mock_interviews';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'scorecard' => 'array',
        ];
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(MockBlueprint::class, 'mock_blueprint_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }
}
