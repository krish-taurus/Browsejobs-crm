<?php

namespace App\Models\Lms;

/**
 * A mentor's weekly availability slot (read-only mirror) — mentors manage
 * these themselves in the LMS "My mentoring" hub.
 */
class MentorAvailability extends LmsModel
{
    protected $table = 'mentor_availabilities';
}
