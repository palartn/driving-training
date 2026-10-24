<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Support\RoleLabel;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الدور')
                    ->formatStateUsing(fn (string $state) => RoleLabel::label($state))
                    ->description(fn ($record) => $record->name)
                    ->searchable(),
                TextColumn::make('hierarchy_level')
                    ->label('المرتبة')
                    ->formatStateUsing(fn ($state) => RoleLabel::levelOptions()[(int) $state] ?? '-')
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label('عدد الصلاحيات')
                    ->counts('permissions'),
                TextColumn::make('users_count')
                    ->label('عدد المستخدمين')
                    ->counts('users'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('hierarchy_level', 'desc');
    }
}
