<?php

namespace App\Filament\Resources\TrainingSessions;

use App\Filament\Resources\TrainingSessions\Pages\CreateTrainingSession;
use App\Filament\Resources\TrainingSessions\Pages\EditTrainingSession;
use App\Filament\Resources\TrainingSessions\Pages\ListTrainingSessions;
use App\Filament\Resources\TrainingSessions\Pages\RecordAttendance;
use App\Filament\Resources\TrainingSessions\Schemas\TrainingSessionForm;
use App\Filament\Resources\TrainingSessions\Tables\TrainingSessionsTable;
use App\Models\TrainingSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingSessionResource extends Resource
{
    protected static ?string $model = TrainingSession::class;

    protected static ?string $modelLabel = 'جلسة';

    protected static ?string $pluralModelLabel = 'الجلسات والحضور';

    protected static ?string $navigationLabel = 'الجلسات والحضور';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 6;

    /** الجلسة لا تحمل مركزًا مباشرة؛ مركزها هو مركز دورتها. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $center = auth()->user()?->center_id;

        return $center === null
            ? $query
            : $query->whereHas('course', fn (Builder $q) => $q->where('center_id', $center));
    }

    public static function form(Schema $schema): Schema
    {
        return TrainingSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainingSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainingSessions::route('/'),
            'create' => CreateTrainingSession::route('/create'),
            'edit' => EditTrainingSession::route('/{record}/edit'),
            'attendance' => RecordAttendance::route('/{record}/attendance'),
        ];
    }
}
