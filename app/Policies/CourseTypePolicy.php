<?php

namespace App\Policies;

use App\Models\CourseType;
use App\Models\User;

class CourseTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('course_types.view');
    }

    public function view(User $user, CourseType $type): bool
    {
        return $user->can('course_types.view');
    }

    public function create(User $user): bool
    {
        return $user->can('course_types.create');
    }

    public function update(User $user, CourseType $type): bool
    {
        return $user->can('course_types.update');
    }

    /** الأنواع لا تُحذف بل تُعطَّل. */
    public function delete(User $user, CourseType $type): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
