<?php

namespace App\Filament\Resources\Instructors;

use App\Filament\Resources\Instructors\Pages\CreateInstructor;
use App\Filament\Resources\Instructors\Pages\EditInstructor;
use App\Filament\Resources\Instructors\Pages\ListInstructors;
use App\Filament\Resources\Instructors\Schemas\InstructorForm;
use App\Filament\Resources\Instructors\Tables\InstructorsTable;
use App\Models\Instructor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstructorResource extends Resource
{
    protected static ?string $model = Instructor::class;

    protected static ?string $modelLabel = 'مدرب';

    protected static ?string $pluralModelLabel = 'المدربون';

    protected static ?string $navigationLabel = 'المدربون';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'name';

    /** المدرب مشترك بين المراكز: المستخدم المرتبط بمركز يرى المدربين الذين يدرّسون في مركزه. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $center = auth()->user()?->center_id;

        return $center === null
            ? $query
            : $query->whereHas('centers', fn (Builder $q) => $q->whereKey($center));
    }

    public static function form(Schema $schema): Schema
    {
        return InstructorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstructorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstructors::route('/'),
            'create' => CreateInstructor::route('/create'),
            'edit' => EditInstructor::route('/{record}/edit'),
        ];
    }
}
