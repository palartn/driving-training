<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    private function sameCenter(User $user, Enrollment $enrollment): bool
    {
        return $user->center_id === null
            || (int) $user->center_id === (int) $enrollment->course->center_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('enrollments.view');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.view') && $this->sameCenter($user, $enrollment);
    }

    public function create(User $user): bool
    {
        return $user->can('enrollments.create');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->can('enrollments.update') && $this->sameCenter($user, $enrollment);
    }

    /** التسجيلات لا تُحذف؛ تُغلق بانسحاب أو إلغاء مع سبب. */
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
