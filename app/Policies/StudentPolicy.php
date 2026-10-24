<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /** المدير العام (بلا مركز) يرى كل المراكز؛ غيره يقتصر على مركزه. */
    private function sameCenter(User $user, Student $student): bool
    {
        return $user->center_id === null || (int) $user->center_id === (int) $student->center_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        return $user->can('students.view') && $this->sameCenter($user, $student);
    }

    public function create(User $user): bool
    {
        return $user->can('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.update') && $this->sameCenter($user, $student);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('students.delete');
    }

    /** لا يُحذف طالب له تسجيلات (قيد المفاتيح الأجنبية يمنع ذلك أيضًا). */
    public function delete(User $user, Student $student): bool
    {
        return $user->can('students.delete')
            && $this->sameCenter($user, $student)
            && ! $student->enrollments()->exists();
    }
}
