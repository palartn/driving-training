<?php

namespace App\Policies;

use App\Models\Center;
use App\Models\User;

class CenterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('centers.view');
    }

    public function view(User $user, Center $center): bool
    {
        return $user->can('centers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('centers.create');
    }

    public function update(User $user, Center $center): bool
    {
        return $user->can('centers.update');
    }

    /** المراكز لا تُحذف بل تُعطَّل (is_active)، لارتباط سجلات كثيرة بها. */
    public function delete(User $user, Center $center): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
