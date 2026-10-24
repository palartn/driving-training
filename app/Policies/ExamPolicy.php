<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    private function sameCenter(User $user, Exam $exam): bool
    {
        return $user->center_id === null || (int) $user->center_id === (int) $exam->course->center_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('exams.view');
    }

    public function view(User $user, Exam $exam): bool
    {
        return $user->can('exams.view') && $this->sameCenter($user, $exam);
    }

    public function create(User $user): bool
    {
        return $user->can('exams.create');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->can('exams.update') && $this->sameCenter($user, $exam);
    }

    /** مواعيد الاختبار لا تُحذف؛ تُلغى بتغيير حالتها. */
    public function delete(User $user, Exam $exam): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
