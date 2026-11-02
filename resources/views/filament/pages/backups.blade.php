<x-filament-panels::page>
    <style>
        .bk-warn { border: 1px solid #d97706; background: rgba(217, 119, 6, .12); border-radius: .75rem; padding: .75rem 1rem; font-weight: 600; }
        .bk-table { width: 100%; border-collapse: collapse; }
        .bk-table th, .bk-table td { padding: .6rem .75rem; border-bottom: 1px solid rgba(128, 128, 128, .3); text-align: right; }
        .bk-table th { font-size: .8rem; opacity: .75; }
        .bk-ltr { direction: ltr; unicode-bidi: embed; }
        .bk-link { color: #d97706; font-weight: 700; text-decoration: none; }
        .bk-note { font-size: .85rem; opacity: .75; line-height: 1.7; }
    </style>

    @if ($warning = $this->staleWarning())
        <div class="bk-warn">{{ $warning }}</div>
    @endif

    <div style="overflow-x: auto">
        <table class="bk-table">
            <thead>
                <tr>
                    <th>ملف النسخة</th>
                    <th>الحجم</th>
                    <th>تاريخ الإنشاء</th>
                    <th>ملف التحقق (SHA-256)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->backups() as $backup)
                    <tr>
                        <td class="bk-ltr">{{ $backup['name'] }}</td>
                        <td>{{ \App\Services\BackupCatalog::humanSize($backup['bytes']) }}</td>
                        <td>{{ $backup['created_at']->format('Y-m-d H:i') }}</td>
                        <td>{{ $backup['has_checksum'] ? 'موجود' : 'مفقود' }}</td>
                        <td>
                            @if ($this->canDownload())
                                <a class="bk-link" href="{{ route('backups.download', ['file' => $backup['name']]) }}">تنزيل</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">لا توجد نسخ بعد. اضغط «إنشاء نسخة الآن».</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="bk-note">
        تُنشأ نسخة تلقائية يوميًا (الوقت من إعداد BACKUP_TIME) وتُحذف النسخ الأقدم من مدة الاحتفاظ. النسخة تحوي كل بيانات النظام، فاحفظ نسخة منها على جهاز أو قرص خارج الخادم.
        الاستعادة تتم من الطرفية بواسطة مسؤول النظام فقط، ولا تُنفَّذ من هذه الشاشة. وتنزيل النسخة وإنشاؤها يُسجَّلان في سجل التدقيق.
    </p>
</x-filament-panels::page>
