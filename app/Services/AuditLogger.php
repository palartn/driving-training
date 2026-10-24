<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public static function record(string $event, Model $subject, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $subject::class,
            'auditable_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 255),
        ]);
    }
}
