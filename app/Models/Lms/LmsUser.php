<?php

namespace App\Models\Lms;

use Illuminate\Database\Eloquent\Relations\HasMany;

class LmsUser extends LmsModel
{
    protected $table = 'users';

    public function batchMemberships(): HasMany
    {
        return $this->hasMany(BatchMember::class, 'user_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'user_id');
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class, 'user_id');
    }

    public function mockInterviews(): HasMany
    {
        return $this->hasMany(MockInterview::class, 'user_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(StudentScore::class, 'user_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'user_id');
    }

    public function topicCompletions(): HasMany
    {
        return $this->hasMany(TopicCompletion::class, 'user_id');
    }

    public function scopeStudents($query)
    {
        return $query->where('user_type', 'student');
    }
}
