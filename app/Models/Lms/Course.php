<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends LmsModel
{
    protected $table = 'courses';

    /**
     * Deliberate write exception to the read-only doctrine (like Product):
     * the per-course registration fee is managed from the CRM. Only fee_paise
     * is ever written from here.
     */
    protected $fillable = ['fee_paise'];

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }
}
