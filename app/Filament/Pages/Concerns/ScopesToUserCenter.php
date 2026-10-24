<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * يقيّد استعلامات المورد بمركز المستخدم.
 * المستخدم بلا مركز (المدير العام) يرى كل المراكز.
 */
trait ScopesToUserCenter
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $centerId = auth()->user()?->center_id;

        return $centerId === null
            ? $query
            : $query->where(static::centerColumn(), $centerId);
    }

    protected static function centerColumn(): string
    {
        return 'center_id';
    }
}
