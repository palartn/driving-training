<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    private function sameCenter(User $user, Course $course): bool
    {
        return $user->center_id === null || (int) $user->center_id === (int) $course->center_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('courses.view');
    }

    public function view(User $user, Course $course): bool
    {
        return $user->can('courses.view') && $this->sameCenter($user, $course);
    }

    public function create(User $user): bool
    {
        return $user->can('courses.create');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can('courses.update') && $this->sameCenter($user, $course);
    }

    /** الدورات لا تُحذف (مرتبطة بمواد وتسجيلات واختبارات)، وتُلغى بتغيير حالتها إلى «ملغاة». */
    public function delete(User $user, Course $course): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
