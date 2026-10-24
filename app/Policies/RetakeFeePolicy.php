<?php

namespace App\Policies;

use App\Models\RetakeFee;
use App\Models\User;

class RetakeFeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('retake_fees.view');
    }

    public function view(User $user, RetakeFee $fee): bool
    {
        return $user->can('retake_fees.view');
    }

    public function create(User $user): bool
    {
        return $user->can('retake_fees.create');
    }

    /** سجل الرسوم للإضافة فقط: يُغيَّر المبلغ بإضافة سجل جديد بتاريخ سريان. */
    public function update(User $user, RetakeFee $fee): bool
    {
        return false;
    }

    public function delete(User $user, RetakeFee $fee): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
