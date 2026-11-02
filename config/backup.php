<?php

return [
    // مجلد النسخ الاحتياطية. الأفضل أن يكون على قرص أو مشاركة شبكية غير قرص النظام والتطبيق.
    'directory' => env('BACKUP_DIRECTORY', storage_path('app/backups')),

    // عدد الأيام التي تُحفظ فيها النسخ قبل حذفها تلقائيًا (بعد نجاح نسخة جديدة فقط).
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),

    // وقت النسخ اليومي (يعمل عبر مجدوِل Laravel).
    'time' => env('BACKUP_TIME', '02:00'),

    // مسارا أدوات MySQL. على Windows مثلًا:
    // C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqldump.exe
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    'mysql_path' => env('MYSQL_CLIENT_PATH', 'mysql'),
];
