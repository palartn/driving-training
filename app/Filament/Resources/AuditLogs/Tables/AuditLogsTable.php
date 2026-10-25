<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use App\Support\AuditLabel;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('التاريخ والوقت')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->placeholder('النظام'),
                TextColumn::make('event')
                    ->label('العملية')
                    ->formatStateUsing(fn (string $state) => AuditLabel::event($state))
                    ->badge()
                    ->searchable(),
                TextColumn::make('auditable_type')
                    ->label('السجل المتأثر')
                    ->formatStateUsing(fn (string $state, AuditLog $record) => AuditLabel::type($state).' #'.$record->auditable_id),
                TextColumn::make('ip_address')
                    ->label('عنوان IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('العملية')
                    ->options(fn (): array => AuditLog::query()
                        ->distinct()
                        ->pluck('event')
                        ->mapWithKeys(fn (string $event) => [$event => AuditLabel::event($event)])
                        ->all()),
                SelectFilter::make('auditable_type')
                    ->label('نوع السجل')
                    ->options(fn (): array => AuditLabel::types()),
                SelectFilter::make('user_id')
                    ->label('المستخدم')
                    ->relationship('user', 'name'),
                Filter::make('created_at')
                    ->label('الفترة')
                    ->schema([
                        DatePicker::make('from')->label('من تاريخ'),
                        DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()->label('تفاصيل'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
