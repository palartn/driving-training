<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamOverride;
use App\Models\Exemption;
use App\Models\Payment;

class AuditLabel
{
    /** أحداث الخدمات (تسجيل، دفع، إعفاء، اختبارات). */
    private const EVENTS = [
        'enrollment.created' => 'تسجيل طالب في دورة',
        'enrollment.closed' => 'إغلاق تسجيل (انسحاب/إلغاء)',
        'enrollment.completed' => 'اكتمال التسجيل',
        'payment.created' => 'تسجيل دفعة',
        'payment.retake_fee.created' => 'دفع رسوم إعادة',
        'exemption.created' => 'اعتماد إعفاء',
        'exam_attempt.registered' => 'تسجيل طالب في اختبار',
        'exam_attempt.result_entered' => 'إدخال نتيجة',
        'exam_attempt.result_approved' => 'اعتماد نتيجة',
        'exam_override.created' => 'استثناء من منع الاختبار',
    ];

    /** النماذج المسجَّلة تلقائيًا عبر AuditObserver. */
    private const MODELS = [
        'student' => 'طالب',
        'course' => 'دورة',
        'subject' => 'مادة',
        'subject_tariff' => 'سعر مادة',
        'course_type' => 'نوع دورة',
        'center' => 'مركز',
        'retake_fee' => 'رسوم إعادة',
        'exam' => 'اختبار',
        'user' => 'مستخدم',
    ];

    private const ACTIONS = [
        'created' => 'إضافة',
        'updated' => 'تعديل',
    ];

    private const TYPES = [
        'App\Models\Student' => 'طالب',
        'App\Models\Course' => 'دورة',
        'App\Models\Subject' => 'مادة',
        'App\Models\SubjectTariff' => 'سعر مادة',
        'App\Models\CourseType' => 'نوع دورة',
        'App\Models\Center' => 'مركز',
        'App\Models\RetakeFee' => 'رسوم إعادة',
        'App\Models\Exam' => 'اختبار',
        'App\Models\User' => 'مستخدم',
        Enrollment::class => 'تسجيل',
        Payment::class => 'دفعة',
        Exemption::class => 'إعفاء',
        ExamAttempt::class => 'محاولة اختبار',
        ExamOverride::class => 'استثناء',
    ];

    public static function event(string $event): string
    {
        if (isset(self::EVENTS[$event])) {
            return self::EVENTS[$event];
        }

        [$model, $action] = array_pad(explode('.', $event, 2), 2, '');

        if (isset(self::MODELS[$model], self::ACTIONS[$action])) {
            return self::ACTIONS[$action].' '.self::MODELS[$model];
        }

        return $event;
    }

    public static function type(string $class): string
    {
        return self::TYPES[$class] ?? class_basename($class);
    }

    /** كل الأنواع للفلاتر: [الصنف => التسمية]. */
    public static function types(): array
    {
        return self::TYPES;
    }
}
