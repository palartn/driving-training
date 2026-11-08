<?php

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\User;

class TrainingSessionPolicy
{
    private function sameCenter(User $user, TrainingSession $session): bool
    {
        return $user->center_id === null || (int) $user->center_id === (int) $session->course->center_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('training_sessions.view');
    }

    public function view(User $user, TrainingSession $session): bool
    {
        return $user->can('training_sessions.view') && $this->sameCenter($user, $session);
    }

    public function create(User $user): bool
    {
        return $user->can('training_sessions.create');
    }

    public function update(User $user, TrainingSession $session): bool
    {
        return $user->can('training_sessions.update') && $this->sameCenter($user, $session);
    }

    /** الجلسات لا تُحذف؛ تُلغى بتغيير حالتها (ما لم يُسجَّل فيها حضور). */
    public function delete(User $user, TrainingSession $session): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
