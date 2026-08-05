<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's target for one metric in one month. The matching actual is
 * always measured live — see TaurusSnapshotService::actualFor().
 */
class TaurusTarget extends Model
{
    protected $fillable = [
        'user_id', 'metric', 'target_value', 'period', 'set_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }

    /**
     * @param  Builder<TaurusTarget>  $query
     */
    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function metricLabel(): string
    {
        return config("taurus.target_metrics.{$this->metric}", $this->metric);
    }
}
