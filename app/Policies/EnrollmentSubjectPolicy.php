<?php

namespace App\Policies;

use App\Models\EnrollmentSubject;
use App\Models\User;

class EnrollmentSubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('enrollments.view');
    }

    public function view(User $user, EnrollmentSubject $subject): bool
    {
        return $user->can('enrollments.view');
    }

    /** الإنشاء يتم تلقائيًا عند التسجيل، والدفع والإعفاء عبر إجراءات مخصصة. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EnrollmentSubject $subject): bool
    {
        return false;
    }

    public function delete(User $user, EnrollmentSubject $subject): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
