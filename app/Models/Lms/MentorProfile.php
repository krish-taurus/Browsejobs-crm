<?php

namespace App\Models\Lms;

/**
 * A mentor's public profile in the LMS pool (read-only mirror).
 */
class MentorProfile extends LmsModel
{
    protected $table = 'mentor_profiles';

    protected function casts(): array
    {
        return ['expertise_tags' => 'array', 'course_ids' => 'array', 'is_active' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(LmsUser::class, 'user_id');
    }

    public function sessions()
    {
        return $this->hasMany(MentorSession::class, 'mentor_profile_id');
    }

    public function availabilities()
    {
        return $this->hasMany(MentorAvailability::class, 'mentor_profile_id');
    }
}
