<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * المدير العام يدير الجميع. غيره يدير من هم أدنى منه مرتبة وفي مركزه، وحسابه هو.
     */
    private function canManage(User $actor, User $target): bool
    {
        $level = $actor->hierarchyLevel();

        if ($level >= 4 || $actor->is($target)) {
            return true;
        }

        return $level > $target->hierarchyLevel()
            && ($actor->center_id === null || (int) $actor->center_id === (int) $target->center_id);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view') && $this->canManage($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update') && $this->canManage($user, $target);
    }

    /** المستخدمون لا يُحذفون (سجل التدقيق والعمليات مرتبطة بهم)، بل يُعطَّلون. */
    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
