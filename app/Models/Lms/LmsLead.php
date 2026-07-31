<?php

namespace App\Models\Lms;

class LmsLead extends LmsModel
{
    protected $table = 'leads';

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'first_touch_at' => 'datetime',
        ];
    }
}
