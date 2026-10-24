<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RoleLabel
{
    private const LABELS = [
        'general_manager' => 'المدير العام',
        'manager' => 'المدير',
        'department_head' => 'رئيس القسم',
        'employee' => 'الموظف',
    ];

    public static function label(string $name): string
    {
        return self::LABELS[$name] ?? $name;
    }

    /** الأدوار الأساسية لا يُغيَّر اسمها. */
    public static function isCore(string $name): bool
    {
        return array_key_exists($name, self::LABELS);
    }

    public static function levelOptions(): array
    {
        return [
            1 => 'موظف',
            2 => 'رئيس قسم',
            3 => 'مدير',
            4 => 'مدير عام',
        ];
    }

    public static function allOptions(): array
    {
        return Role::query()
            ->orderByDesc('hierarchy_level')
            ->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => self::label($role->name)])
            ->all();
    }

    /** الأدوار التي يحق للمستخدم الحالي إسنادها: الأدنى منه مرتبة، والمدير العام يسند الكل. */
    public static function assignableOptions(User $actor): array
    {
        $level = $actor->hierarchyLevel();

        return Role::query()
            ->when($level < 4, fn ($query) => $query->where('hierarchy_level', '<', $level))
            ->orderByDesc('hierarchy_level')
            ->get()
            ->mapWithKeys(fn (Role $role) => [$role->name => self::label($role->name)])
            ->all();
    }
}
