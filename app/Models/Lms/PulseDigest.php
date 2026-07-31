<?php

namespace App\Models\Lms;

/** The generated daily Market Pulse narrative shown on the student Pulse page. */
class PulseDigest extends LmsModel
{
    protected $table = 'pulse_digests';

    protected function casts(): array
    {
        return [
            'digest_date' => 'date',
            'item_ids' => 'array',
        ];
    }
}
