<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/** يقرأ النسخ الاحتياطية الموجودة في مجلد النسخ (للعرض والتنزيل الآمن). */
class BackupCatalog
{
    /** أسماء ملفات النسخ فقط: أحرف وأرقام وشرطات ونقاط، وتنتهي بـ .sql.gz */
    public const NAME_PATTERN = '/^[A-Za-z0-9_.\-]+\.sql\.gz$/';

    public function directory(): string
    {
        return rtrim((string) config('backup.directory'), '\\/');
    }

    /** @return array<int, array{name: string, bytes: int, created_at: Carbon, has_checksum: bool}> الأحدث أولًا */
    public function all(): array
    {
        $directory = $this->directory();

        if (! is_dir($directory)) {
            return [];
        }

        $items = [];

        foreach (File::files($directory) as $file) {
            if (! preg_match(self::NAME_PATTERN, $file->getFilename())) {
                continue;
            }

            $items[] = [
                'name' => $file->getFilename(),
                'bytes' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
                'has_checksum' => is_file($file->getPathname().'.sha256'),
            ];
        }

        usort($items, fn (array $a, array $b) => $b['created_at']->getTimestamp() <=> $a['created_at']->getTimestamp());

        return $items;
    }

    public function lastBackupAt(): ?Carbon
    {
        return $this->all()[0]['created_at'] ?? null;
    }

    /** المسار الكامل لنسخة بالاسم إن كان الاسم صالحًا والملف موجودًا، وإلا null. */
    public function find(string $name): ?string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    public static function humanSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024, 1).' KB';
    }
}
