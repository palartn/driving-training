<?php

namespace App\Support;

class PermissionLabel
{
    private const MODULES = [
        'students' => 'الطلاب',
        'courses' => 'الدورات',
        'subjects' => 'المواد',
        'course_types' => 'أنواع الدورات',
        'subject_tariffs' => 'تعريفة المواد',
        'enrollments' => 'التسجيل',
        'payments' => 'الدفعات',
        'exemptions' => 'الإعفاءات',
        'exams' => 'مواعيد الاختبارات',
        'exam_attempts' => 'محاولات الاختبار',
        'retake_fees' => 'رسوم الإعادة',
        'exam_overrides' => 'استثناءات الاختبار',
        'centers' => 'المراكز',
        'users' => 'المستخدمون',
        'roles' => 'الأدوار',
        'audit_logs' => 'سجل التدقيق',
        'reports' => 'التقارير',
    ];

    private const ACTIONS = [
        'view' => 'عرض',
        'create' => 'إضافة',
        'update' => 'تعديل',
        'delete' => 'حذف',
    ];

    private const SPECIAL = [
        'enrollments.close' => 'التسجيل: إغلاق التسجيل (انسحاب/إلغاء)',
        'results.approve' => 'الاختبارات: اعتماد النتائج',
        'payments.correct' => 'الدفعات: تصحيح دفعة مدخلة خطأ',
    ];

    public static function label(string $name): string
    {
        if (isset(self::SPECIAL[$name])) {
            return self::SPECIAL[$name];
        }

        [$module, $action] = array_pad(explode('.', $name, 2), 2, '');

        return (self::MODULES[$module] ?? $module).': '.(self::ACTIONS[$action] ?? $action);
    }
}
