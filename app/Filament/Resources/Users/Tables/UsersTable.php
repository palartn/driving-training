<?php

namespace App\Filament\Resources\Users\Tables;

use App\Support\RoleLabel;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label('اسم المستخدم')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('الدور')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => RoleLabel::label($state)),
                TextColumn::make('center.name')
                    ->label('المركز')
                    ->placeholder('كل المراكز')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label('آخر دخول')
                    ->dateTime()
                    ->placeholder('لم يدخل بعد')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('الدور')
                    ->options(fn () => RoleLabel::allOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name')
                    ->visible(fn () => auth()->user()?->center_id === null),
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('فعّال')
                    ->falseLabel('معطّل'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
