<?php

namespace App\Models\Lms;

/**
 * A curated Market Pulse source item. Deliberate write exception to the
 * read-only doctrine: the founder curates these from the CRM, because the
 * digest builder refuses to invent news and produces nothing without them.
 */
class PulseItem extends LmsModel
{
    protected $table = 'pulse_items';

    protected $fillable = [
        'tenant_id', 'title', 'url', 'source_name',
        'course_slug', 'note', 'published_on', 'added_by', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
