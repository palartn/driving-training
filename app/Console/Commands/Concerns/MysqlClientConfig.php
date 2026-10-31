<?php

namespace App\Console\Commands\Concerns;

/** ملف إعداد مؤقت لأدوات MySQL حتى لا تظهر كلمة المرور في سطر الأوامر. */
trait MysqlClientConfig
{
    protected function writeClientConfig(array $connection): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dtmy');
        $escape = fn ($value): string => addcslashes((string) $value, "\"\\");

        $content = "[client]\n"
            .'user="'.$escape($connection['username'] ?? '')."\"\n"
            .'password="'.$escape($connection['password'] ?? '')."\"\n"
            .'host="'.$escape($connection['host'] ?? '127.0.0.1')."\"\n"
            .'port='.(int) ($connection['port'] ?? 3306)."\n";

        file_put_contents($path, $content);
        @chmod($path, 0600);

        return $path;
    }
}
