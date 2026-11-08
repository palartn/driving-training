<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Models\CourseSubjectInstructor;
use App\Models\Instructor;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstructorAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'instructorAssignments';

    protected static ?string $title = 'المدربون المسنَدون إلى مواد الدورة';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('subject_id')
                ->label('المادة')
                ->options(fn (RelationManager $livewire): array => $livewire->getOwnerRecord()
                    ->subjects()
                    ->orderBy('subjects.name')
                    ->pluck('subjects.name', 'subjects.id')
                    ->all())
                ->required(),
            Select::make('instructor_id')
                ->label('المدرب')
                ->options(fn (RelationManager $livewire): array => Instructor::query()
                    ->where('is_active', true)
                    ->whereHas('centers', fn (Builder $q) => $q->whereKey($livewire->getOwnerRecord()->center_id))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->helperText('المدربون الفعّالون الذين يدرّسون في مركز هذه الدورة.')
                ->rule(fn (Get $get, RelationManager $livewire): Closure => function (string $attribute, $value, Closure $fail) use ($get, $livewire) {
                    $exists = CourseSubjectInstructor::query()
                        ->where('course_id', $livewire->getOwnerRecord()->getKey())
                        ->where('subject_id', $get('subject_id'))
                        ->where('instructor_id', $value)
                        ->exists();

                    if ($exists) {
                        $fail('هذا المدرب مسنَد إلى هذه المادة في هذه الدورة مسبقًا.');
                    }
                }),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subject', 'instructor']))
            ->columns([
                TextColumn::make('subject.name')
                    ->label('المادة'),
                TextColumn::make('instructor.name')
                    ->label('المدرب'),
                TextColumn::make('instructor.phone')
                    ->label('الهاتف'),
            ])
            ->headerActions([
                CreateAction::make()->label('إسناد مدرب إلى مادة'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('إلغاء الإسناد')
                    ->modalDescription('لا يؤثر إلغاء الإسناد على الجلسات السابقة، لكن لا تُجدول بعده جلسات جديدة لهذا المدرب في هذه المادة.'),
            ]);
    }
}
