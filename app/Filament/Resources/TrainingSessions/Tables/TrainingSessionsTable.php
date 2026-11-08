<?php

namespace App\Filament\Resources\TrainingSessions\Tables;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Filament\Resources\TrainingSessions\TrainingSessionResource;
use App\Models\TrainingSession;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrainingSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['course', 'subject', 'instructor'])
                ->withCount('attendanceRecords'))
            ->columns([
                TextColumn::make('session_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('time')
                    ->label('الوقت')
                    ->state(fn (TrainingSession $record) => $record->timeLabel()),
                TextColumn::make('course.course_number')
                    ->label('الدورة')
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('المادة'),
                TextColumn::make('instructor.name')
                    ->label('المدرب')
                    ->placeholder('-'),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('attendance_records_count')
                    ->label('سجلات الحضور'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(SessionStatus::class),
                SelectFilter::make('type')
                    ->label('النوع')
                    ->options(SessionType::class),
                SelectFilter::make('course_id')
                    ->label('الدورة')
                    ->relationship('course', 'course_number'),
                Filter::make('session_date')
                    ->label('الفترة')
                    ->schema([
                        DatePicker::make('from')->label('من تاريخ'),
                        DatePicker::make('until')->label('إلى تاريخ'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('session_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('session_date', '<=', $date))),
            ])
            ->recordActions([
                Action::make('attendance')
                    ->label('الحضور')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('success')
                    ->visible(fn () => auth()->user()->can('attendance.view'))
                    ->url(fn (TrainingSession $record) => TrainingSessionResource::getUrl('attendance', ['record' => $record])),
                EditAction::make(),
            ])
            ->defaultSort('session_date', 'desc');
    }
}
