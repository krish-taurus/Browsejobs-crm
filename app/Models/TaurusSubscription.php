<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring vendor spend watched by the Taurus expense sentinel.
 */
class TaurusSubscription extends Model
{
    protected $fillable = [
        'name', 'vendor', 'monthly_cost', 'seats',
        'last_used_on', 'usage_note', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_cost' => 'decimal:2',
            'last_used_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Days since this was last used, or null when usage was never recorded.
     */
    public function daysIdle(): ?int
    {
        return $this->last_used_on
            ? (int) $this->last_used_on->startOfDay()->diffInDays(today())
            : null;
    }

    /**
     * waste | watch | ok — never-used counts as waste, not as unknown.
     */
    public function sentinelFlag(): string
    {
        $days = $this->daysIdle();

        if ($days === null || $days > config('taurus.sentinel.waste_after_days')) {
            return 'waste';
        }

        return $days > config('taurus.sentinel.watch_after_days') ? 'watch' : 'ok';
    }
}
