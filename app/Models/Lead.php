<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'mobile', 'name', 'email', 'source', 'campaign_name', 'interested_course_slug', 'lms_lead_id',
        'masterclass_link_sent_at', 'masterclass_followup_at', 'allocated_batch_number',
        'added_by_user_id', 'current_status_id',
        'assigned_to_user_id', 'assigned_by_user_id', 'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'masterclass_link_sent_at' => 'datetime',
            'masterclass_followup_at' => 'datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'current_status_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(LeadCall::class)->latest();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest();
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(LeadAiAnalysis::class)->latest();
    }

    public function displayName(): string
    {
        return $this->name ?: $this->mobile;
    }
}
