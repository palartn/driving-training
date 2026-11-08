<?php

return [
    // عدد المواد غير المسددة الذي يمنع الطالب من دخول الاختبار (إلا باستثناء معتمد).
    'unpaid_subjects_block_threshold' => (int) env('UNPAID_SUBJECTS_BLOCK_THRESHOLD', 2),

    // رمز عملة الأسعار المعروض في الواجهة (الشيكل).
    'currency_symbol' => env('CURRENCY_SYMBOL', '₪'),

    // الحد الأدنى لنسبة الحضور (%) للسماح بدخول الاختبار. 0 = تعطيل القاعدة.
    // القيمة 75 افتراضية مقترحة وغير معتمدة؛ يحددها صاحب المشروع.
    'min_attendance_percent' => (float) env('MIN_ATTENDANCE_PERCENT', 75),

    // هل يُحتسب «غياب بعذر» حضورًا عند حساب النسبة؟ (افتراضي: لا).
    'excused_counts_as_present' => (bool) env('EXCUSED_COUNTS_AS_PRESENT', false),

];
