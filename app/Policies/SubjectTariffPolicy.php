<?php

namespace App\Policies;

use App\Models\SubjectTariff;
use App\Models\User;

class SubjectTariffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('subject_tariffs.view');
    }

    public function view(User $user, SubjectTariff $tariff): bool
    {
        return $user->can('subject_tariffs.view');
    }

    public function create(User $user): bool
    {
        return $user->can('subject_tariffs.create');
    }

    /** سجل الأسعار للإضافة فقط: لا تعديل ولا حذف، والتغيير بإضافة تعريفة جديدة بتاريخ سريان. */
    public function update(User $user, SubjectTariff $tariff): bool
    {
        return false;
    }

    public function delete(User $user, SubjectTariff $tariff): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
