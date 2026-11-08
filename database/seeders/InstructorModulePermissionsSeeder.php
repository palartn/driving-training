<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات المدربين والجلسات والحضور: إضافة فقط دون مزامنة (لا يمحو تخصيصاتك على الأدوار).
 * المدير العام والمدير: الكل. رئيس القسم: الكل. الموظف: عرض المدربين والجلسات، وتسجيل الحضور وتعديله.
 */
class InstructorModulePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [
            'instructors.view', 'instructors.create', 'instructors.update',
            'training_sessions.view', 'training_sessions.create', 'training_sessions.update',
            'attendance.view', 'attendance.create', 'attendance.update',
        ];

        $employee = [
            'instructors.view', 'training_sessions.view',
            'attendance.view', 'attendance.create', 'attendance.update',
        ];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $grants = [
            'general_manager' => $all,
            'manager' => $all,
            'department_head' => $all,
            'employee' => $employee,
        ];

        foreach ($grants as $role => $permissions) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        }
    }
}
