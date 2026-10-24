<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('subjects.view');
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->can('subjects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('subjects.create');
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->can('subjects.update');
    }

    /** المواد لا تُحذف (مرتبطة بدورات وتسجيلات)، بل تُعطَّل. */
    public function delete(User $user, Subject $subject): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
