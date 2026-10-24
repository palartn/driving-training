<?php

namespace App\Filament\Resources\RetakeFees;

use App\Filament\Resources\RetakeFees\Pages\CreateRetakeFee;
use App\Filament\Resources\RetakeFees\Pages\ListRetakeFees;
use App\Filament\Resources\RetakeFees\Schemas\RetakeFeeForm;
use App\Filament\Resources\RetakeFees\Tables\RetakeFeesTable;
use App\Models\RetakeFee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RetakeFeeResource extends Resource
{
    protected static ?string $model = RetakeFee::class;

    protected static ?string $modelLabel = 'رسوم إعادة';

    protected static ?string $pluralModelLabel = 'رسوم إعادة الاختبار';

    protected static ?string $navigationLabel = 'رسوم إعادة الاختبار';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return RetakeFeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RetakeFeesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRetakeFees::route('/'),
            'create' => CreateRetakeFee::route('/create'),
        ];
    }
}
