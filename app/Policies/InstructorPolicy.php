<?php

namespace App\Policies;

use App\Models\Instructor;
use App\Models\User;

class InstructorPolicy
{
    /** المدرب مشترك بين المراكز: المستخدم المرتبط بمركز يتعامل مع المدربين الذين يدرّسون في مركزه. */
    private function sameCenter(User $user, Instructor $instructor): bool
    {
        return $user->center_id === null
            || $instructor->centers()->whereKey($user->center_id)->exists();
    }

    public function viewAny(User $user): bool
    {
        return $user->can('instructors.view');
    }

    public function view(User $user, Instructor $instructor): bool
    {
        return $user->can('instructors.view') && $this->sameCenter($user, $instructor);
    }

    public function create(User $user): bool
    {
        return $user->can('instructors.create');
    }

    public function update(User $user, Instructor $instructor): bool
    {
        return $user->can('instructors.update') && $this->sameCenter($user, $instructor);
    }

    /** المدربون لا يُحذفون (مرتبطون بجلسات وإسنادات)، بل يُعطَّلون. */
    public function delete(User $user, Instructor $instructor): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
