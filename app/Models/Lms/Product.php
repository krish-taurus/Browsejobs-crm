<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;

/**
 * Sellable pack in the LMS store (voice interview sessions, CV credits, …).
 *
 * Deliberate exception to the read-only LmsModel doctrine: pack pricing is
 * managed from the CRM (founder request, Jul 2026) — the same pattern as the
 * mentor/live-batch controllers that already write to the LMS database. Only
 * price_paise, grant_amount and active are ever touched from here; the LMS
 * remains the owner of every other product field.
 */
class Product extends Model
{
    protected $connection = 'lms';

    protected $fillable = ['price_paise', 'grant_amount', 'active'];

    protected function casts(): array
    {
        return [
            'price_paise' => 'integer',
            'grant_amount' => 'integer',
            'active' => 'boolean',
        ];
    }
}
