<?php

return [
    // عدد المواد غير المسددة الذي يمنع الطالب من دخول الاختبار (إلا باستثناء معتمد).
    'unpaid_subjects_block_threshold' => (int) env('UNPAID_SUBJECTS_BLOCK_THRESHOLD', 2),
];
