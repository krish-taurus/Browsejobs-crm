<?php

namespace App\Models\Lms;

/**
 * Deliberate write exception to the read-only doctrine (like Product/Tenant):
 * public-site content (hero copy, per-page SEO) is edited from the CRM's
 * Website Content page.
 */
class SiteContent extends LmsModel
{
    protected $table = 'site_contents';

    protected $fillable = ['tenant_id', 'key', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
