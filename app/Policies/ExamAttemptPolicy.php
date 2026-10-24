<?php

namespace App\Policies;

use App\Models\ExamAttempt;
use App\Models\User;

class ExamAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exam_attempts.view');
    }

    public function view(User $user, ExamAttempt $attempt): bool
    {
        return $user->can('exam_attempts.view');
    }

    /** التسجيل والنتائج والاعتماد عبر إجراءات مخصصة تفحص القواعد في الخدمة. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ExamAttempt $attempt): bool
    {
        return false;
    }

    public function delete(User $user, ExamAttempt $attempt): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
