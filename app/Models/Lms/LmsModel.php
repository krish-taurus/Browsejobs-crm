<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class for models backed by the BrowseJobs LMS database.
 * The CRM only reads from that database — never create/update/delete
 * through these models.
 */
abstract class LmsModel extends Model
{
    protected $connection = 'lms';
}
