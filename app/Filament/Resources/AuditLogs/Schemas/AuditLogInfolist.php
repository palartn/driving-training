<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use App\Models\AuditLog;
use App\Support\AuditLabel;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('العملية')
                ->columns(2)
                ->schema([
                    TextEntry::make('created_at')
                        ->label('التاريخ والوقت')
                        ->dateTime(),
                    TextEntry::make('user.name')
                        ->label('المستخدم')
                        ->placeholder('النظام'),
                    TextEntry::make('event')
                        ->label('العملية')
                        ->formatStateUsing(fn (string $state) => AuditLabel::event($state))
                        ->badge(),
                    TextEntry::make('auditable_type')
                        ->label('السجل المتأثر')
                        ->formatStateUsing(fn (string $state, AuditLog $record) => AuditLabel::type($state).' #'.$record->auditable_id),
                    TextEntry::make('ip_address')
                        ->label('عنوان IP')
                        ->placeholder('-'),
                    TextEntry::make('user_agent')
                        ->label('المتصفح')
                        ->placeholder('-'),
                ]),
            Section::make('القيم')
                ->columns(2)
                ->schema([
                    KeyValueEntry::make('old_values')
                        ->label('قبل')
                        ->keyLabel('الحقل')
                        ->valueLabel('القيمة')
                        ->placeholder('-'),
                    KeyValueEntry::make('new_values')
                        ->label('بعد')
                        ->keyLabel('الحقل')
                        ->valueLabel('القيمة')
                        ->placeholder('-'),
                ]),
        ]);
    }
}
