<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_logs.view');
    }

    /** المدير العام يرى الكل؛ غيره يرى عمليات موظفي مركزه فقط. */
    public function view(User $user, AuditLog $log): bool
    {
        if (! $user->can('audit_logs.view')) {
            return false;
        }

        return $user->center_id === null
            || (int) $log->user?->center_id === (int) $user->center_id;
    }

    /** السجل للعرض فقط. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
