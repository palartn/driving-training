<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\BackupCatalog;
use Illuminate\Http\Request;

class BackupDownloadController extends Controller
{
    /** تنزيل نسخة احتياطية: صلاحية خاصة، مع التحقق من سلامة الملف وتسجيل العملية في سجل التدقيق. */
    public function __invoke(Request $request, string $file, BackupCatalog $catalog)
    {
        abort_unless($request->user()?->can('backups.download'), 403);

        $path = $catalog->find($file);
        abort_if($path === null, 404);

        $expected = trim(explode(' ', (string) @file_get_contents($path.'.sha256'))[0]);

        abort_if(
            $expected === '' || ! hash_equals($expected, hash_file('sha256', $path)),
            409,
            'فشل التحقق من سلامة النسخة الاحتياطية؛ لن يُنزَّل الملف.'
        );

        AuditLogger::record('backup.downloaded', $request->user(), null, [
            'file' => $file,
            'bytes' => filesize($path),
        ]);

        return response()->download($path, $file, ['Content-Type' => 'application/gzip']);
    }
}
