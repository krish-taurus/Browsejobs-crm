<?php

namespace App\Models\Lms;

class ProductPurchase extends LmsModel
{
    protected $table = 'product_purchases';

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }
}
