<?php

namespace App\Models\Lms;

/**
 * Deliberate write exception to the read-only doctrine (like Product/Course):
 * whitelabel branding (name, logo, colours) is managed from the CRM. Only
 * name and branding are ever written from here.
 */
class Tenant extends LmsModel
{
    protected $table = 'tenants';

    protected $fillable = ['name', 'branding'];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'feature_flags' => 'array',
        ];
    }
}
