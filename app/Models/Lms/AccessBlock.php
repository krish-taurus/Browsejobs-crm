<?php

namespace App\Models\Lms;

class AccessBlock extends LmsModel
{
    protected $table = 'access_blocks';

    protected function casts(): array
    {
        return [
            'lifted_at' => 'datetime',
        ];
    }
}
