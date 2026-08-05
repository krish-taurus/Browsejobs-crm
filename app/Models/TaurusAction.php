<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change Taurus has proposed and a human has not yet decided on.
 * Rows are never acted on automatically.
 */
class TaurusAction extends Model
{
    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'action', 'target_type', 'target_id', 'payload', 'summary', 'status',
        'requested_by_user_id', 'decided_by_user_id', 'decided_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * @param  Builder<TaurusAction>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
