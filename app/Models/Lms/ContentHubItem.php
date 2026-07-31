<?php

namespace App\Models\Lms;

/**
 * A Content Hub release (podcast/video/post) shown to students on Pulse.
 * Write exception like PulseItem — curated from the CRM.
 */
class ContentHubItem extends LmsModel
{
    /** Kinds the LMS accepts (ContentHubItem::KINDS in the LMS). */
    public const KINDS = ['youtube', 'instagram', 'podcast'];

    protected $table = 'content_hub_items';

    protected $fillable = [
        'tenant_id', 'kind', 'title', 'url', 'source',
        'published_at', 'created_by', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
