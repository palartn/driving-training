<?php

namespace App\Filament\Pages;

use App\Services\AuditLogger;
use App\Services\BackupCatalog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

class Backups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'النسخ الاحتياطي';

    protected static ?string $title = 'النسخ الاحتياطي لقاعدة البيانات';

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 70;

    protected string $view = 'filament.pages.backups';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('backups.view') ?? false;
    }

    public function backups(): array
    {
        return app(BackupCatalog::class)->all();
    }

    public function canDownload(): bool
    {
        return auth()->user()?->can('backups.download') ?? false;
    }

    /** تحذير إن لم توجد نسخة، أو كانت آخر نسخة أقدم من 36 ساعة (علامة أن المجدول متوقف). */
    public function staleWarning(): ?string
    {
        $last = app(BackupCatalog::class)->lastBackupAt();

        if ($last === null) {
            return 'لا توجد أي نسخة احتياطية بعد.';
        }

        if ($last->lt(now()->subHours(36))) {
            return 'آخر نسخة احتياطية كانت '.$last->diffForHumans().'. تحقق من أن مجدول النظام يعمل.';
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupNow')
                ->label('إنشاء نسخة الآن')
                ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
                ->visible(fn () => auth()->user()->can('backups.create'))
                ->requiresConfirmation()
                ->modalDescription('تُنشأ نسخة كاملة مضغوطة من قاعدة البيانات في مجلد النسخ على الخادم. قد تستغرق دقيقة أو أكثر مع كبر البيانات.')
                ->action(function (): void {
                    @set_time_limit(600);

                    $code = Artisan::call('app:backup-database');

                    if ($code === 0) {
                        AuditLogger::record('backup.created', auth()->user(), null, ['source' => 'screen']);

                        Notification::make()->success()->title('تم إنشاء النسخة الاحتياطية')->send();

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('فشل إنشاء النسخة الاحتياطية')
                        ->body(mb_substr(trim(Artisan::output()), 0, 400))
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
