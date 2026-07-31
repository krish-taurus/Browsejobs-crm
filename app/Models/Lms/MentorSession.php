<?php

namespace App\Models\Lms;

/**
 * A booked 1:1 mentoring session (read-only mirror).
 */
class MentorSession extends LmsModel
{
    protected $table = 'mentor_sessions';

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function mentor()
    {
        return $this->belongsTo(MentorProfile::class, 'mentor_profile_id');
    }

    public function student()
    {
        return $this->belongsTo(LmsUser::class, 'student_id');
    }
}
