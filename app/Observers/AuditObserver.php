<?php

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * يسجّل الإضافة والتعديل لنماذج البيانات المرجعية والطلاب والمستخدمين.
 * (التسجيل والدفع والإعفاء والاختبارات تُسجَّل صراحةً داخل خدماتها فلا تُراقَب هنا.)
 */
class AuditObserver
{
    /** حقول لا تُسجَّل: أسرار أو ضجيج. */
    private const IGNORED = ['password', 'remember_token', 'created_at', 'updated_at', 'last_login_at'];

    public function created(Model $model): void
    {
        AuditLogger::record($this->event($model, 'created'), $model, null, $this->clean($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->clean($model->getChanges());

        if ($changes === []) {
            return;
        }

        $old = $this->clean(array_intersect_key($model->getRawOriginal(), $changes));

        AuditLogger::record($this->event($model, 'updated'), $model, $old, $changes);
    }

    private function event(Model $model, string $action): string
    {
        return Str::snake(class_basename($model)).'.'.$action;
    }

    private function clean(array $values): array
    {
        return array_diff_key($values, array_flip(self::IGNORED));
    }
}
