<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * أدوار وصلاحيات افتراضية مقترحة (غير معتمدة نهائيًا).
 * تُعدَّل لاحقًا من شاشة الأدوار. الاسم: <المورد>.<العملية>
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = [
            'students' => ['view', 'create', 'update', 'delete'],
            'courses' => ['view', 'create', 'update', 'delete'],
            'subjects' => ['view', 'create', 'update', 'delete'],
            'course_types' => ['view', 'create', 'update'],
            'subject_tariffs' => ['view', 'create'],
            'enrollments' => ['view', 'create', 'update'],
            'payments' => ['view', 'create'],
            'exemptions' => ['view', 'create'],
            'exams' => ['view', 'create', 'update'],
            'exam_attempts' => ['view', 'create', 'update'],
            'retake_fees' => ['view', 'create'],
            'exam_overrides' => ['view', 'create'],
            'centers' => ['view', 'create', 'update'],
            'users' => ['view', 'create', 'update'],
            'roles' => ['view', 'create', 'update'],
            'audit_logs' => ['view'],
            'reports' => ['view'],
        ];

        $all = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $all[] = "{$module}.{$action}";
            }
        }

        // صلاحيات خاصة.
        $special = ['enrollments.close', 'results.approve', 'payments.correct'];
        $all = array_merge($all, $special);

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // المدير العام: كل الصلاحيات.
        $generalManager = $all;

        // المدير: كل شيء عدا إدارة المستخدمين والأدوار والمراكز، وتحديد التعريفة والرسوم وأنواع الدورات.
        $managerExcluded = ['retake_fees.create', 'subject_tariffs.create', 'course_types.create', 'course_types.update'];
        $manager = array_values(array_filter($all, function (string $p) use ($managerExcluded) {
            return ! str_starts_with($p, 'users.')
                && ! str_starts_with($p, 'roles.')
                && ! str_starts_with($p, 'centers.')
                && ! in_array($p, $managerExcluded, true);
        }));

        // رئيس القسم: التشغيل الكامل مع الإعفاء والاستثناء واعتماد النتائج وإغلاق التسجيل.
        $departmentHead = [
            'students.view', 'students.create', 'students.update',
            'courses.view', 'courses.create', 'courses.update',
            'subjects.view', 'course_types.view', 'subject_tariffs.view',
            'enrollments.view', 'enrollments.create', 'enrollments.update', 'enrollments.close',
            'payments.view', 'payments.create',
            'exemptions.view', 'exemptions.create',
            'exams.view', 'exams.create', 'exams.update',
            'exam_attempts.view', 'exam_attempts.create', 'exam_attempts.update', 'results.approve',
            'retake_fees.view',
            'exam_overrides.view', 'exam_overrides.create',
            'audit_logs.view', 'reports.view',
        ];

        // الموظف: الإدخال اليومي بلا إعفاء ولا استثناء ولا اعتماد.
        $employee = [
            'students.view', 'students.create', 'students.update',
            'courses.view', 'subjects.view', 'course_types.view', 'subject_tariffs.view',
            'enrollments.view', 'enrollments.create',
            'payments.view', 'payments.create',
            'exams.view',
            'exam_attempts.view', 'exam_attempts.create', 'exam_attempts.update',
            'retake_fees.view',
            'reports.view',
        ];

        $roles = [
            'general_manager' => [4, $generalManager],
            'manager' => [3, $manager],
            'department_head' => [2, $departmentHead],
            'employee' => [1, $employee],
        ];

        foreach ($roles as $name => [$level, $permissions]) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $role->update(['hierarchy_level' => $level]);
            $role->syncPermissions($permissions);
        }
    }
}
