<?php

namespace App\Policies;

use App\Models\CourseSubjectInstructor;
use App\Models\User;

class CourseSubjectInstructorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('instructors.view');
    }

    public function view(User $user, CourseSubjectInstructor $assignment): bool
    {
        return $user->can('instructors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('instructors.update');
    }

    public function update(User $user, CourseSubjectInstructor $assignment): bool
    {
        return false;
    }

    /** إلغاء الإسناد لا يمس الجلسات السابقة (تحفظ مدربها). */
    public function delete(User $user, CourseSubjectInstructor $assignment): bool
    {
        return $user->can('instructors.update');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
