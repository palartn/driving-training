<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use App\Enums\AttemptResult;
use App\Exceptions\BusinessRuleException;
use App\Models\Enrollment;
use App\Models\ExamAttempt;
use App\Models\Payment;
use App\Services\ExamAttemptService;
use App\Services\PaymentService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'attempts';

    protected static ?string $title = 'الطلاب المسجّلون والنتائج';

    public function isReadOnly(): bool
    {
        return false;
    }

    /** حقل اختيار تسجيل الطالب في دورة هذا الاختبار (بحث بالهوية أو الاسم أو الهاتف). */
    private function enrollmentSelect(): Select
    {
        $label = fn (Enrollment $e): string => $e->student->full_name.' — '.$e->student->national_id;

        return Select::make('enrollment_id')
            ->label('الطالب')
            ->placeholder('ابحث برقم الهوية أو الاسم أو الهاتف')
            ->required()
            ->searchable()
            ->getSearchResultsUsing(function (string $search) use ($label): array {
                $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

                return Enrollment::query()
                    ->active()
                    ->where('course_id', $this->getOwnerRecord()->course_id)
                    ->whereHas('student', fn (Builder $q) => $q
                        ->where('national_id', 'like', $term)
                        ->orWhere('full_name', 'like', $term)
                        ->orWhere('phone', 'like', $term))
                    ->with('student')
                    ->limit(30)
                    ->get()
                    ->mapWithKeys(fn (Enrollment $e) => [$e->id => $label($e)])
                    ->all();
            })
            ->getOptionLabelUsing(fn ($value): ?string => ($enrollment = Enrollment::with('student')->find($value))
                ? $label($enrollment)
                : null);
    }

    public function table(Table $table): Table
    {
        $currency = ' '.config('training.currency_symbol', '₪');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enrollment.student', 'payment', 'exam.course']))
            ->columns([
                TextColumn::make('enrollment.student.national_id')
                    ->label('رقم الهوية')
                    ->searchable(),
                TextColumn::make('enrollment.student.full_name')
                    ->label('الطالب')
                    ->searchable(),
                TextColumn::make('attempt_no')
                    ->label('المحاولة'),
                TextColumn::make('result')
                    ->label('النتيجة')
                    ->badge(),
                TextColumn::make('retake_fee_amount')
                    ->label('رسوم الإعادة')
                    ->suffix($currency)
                    ->placeholder('-'),
                TextColumn::make('fee_status')
                    ->label('سداد الرسوم')
                    ->state(fn (ExamAttempt $record) => (float) $record->retake_fee_amount > 0
                        ? ($record->payment ? 'مسددة' : 'غير مسددة')
                        : '-')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'مسددة' => 'success',
                        'غير مسددة' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('approved_at')
                    ->label('الاعتماد')
                    ->dateTime()
                    ->placeholder('بانتظار الاعتماد'),
            ])
            ->headerActions([
                Action::make('registerStudent')
                    ->label('تسجيل طالب في الاختبار')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn () => auth()->user()->can('exam_attempts.create'))
                    ->schema([
                        $this->enrollmentSelect(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $enrollment = Enrollment::findOrFail($data['enrollment_id']);

                            $attempt = app(ExamAttemptService::class)->register(
                                $this->getOwnerRecord(),
                                $enrollment,
                                auth()->user(),
                            );

                            Notification::make()
                                ->success()
                                ->title($attempt->attempt_no > 1
                                    ? 'سُجّل الطالب (محاولة إعادة رقم '.$attempt->attempt_no.')؛ تُدفع رسوم الإعادة قبل دخوله.'
                                    : 'تم تسجيل الطالب في الاختبار')
                                ->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title('تعذّر التسجيل')->body($e->getMessage())->persistent()->send();
                        }
                    }),

                Action::make('grantOverride')
                    ->label('اعتماد استثناء (غير مسدد)')
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('warning')
                    ->visible(fn () => auth()->user()->can('exam_overrides.create'))
                    ->schema([
                        $this->enrollmentSelect(),
                        Textarea::make('reason')
                            ->label('سبب الاستثناء')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('سيُسجَّل اسمك كمعتمِد للاستثناء، ويسمح للطالب بدخول هذا الاختبار رغم المواد غير المسددة.')
                    ->action(function (array $data): void {
                        try {
                            app(ExamAttemptService::class)->grantOverride(
                                Enrollment::findOrFail($data['enrollment_id']),
                                $this->getOwnerRecord(),
                                $data['reason'],
                                auth()->user(),
                            );

                            Notification::make()->success()->title('تم اعتماد الاستثناء')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('payFee')
                    ->label('دفع رسوم الإعادة')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (ExamAttempt $record) => auth()->user()->can('payments.create')
                        && (float) $record->retake_fee_amount > 0
                        && $record->payment === null)
                    ->fillForm(fn (ExamAttempt $record): array => [
                        'amount' => number_format((float) $record->retake_fee_amount, 2, '.', ''),
                        'paid_at' => today()->toDateString(),
                    ])
                    ->schema([
                        TextInput::make('amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            ->required()
                            ->suffix(config('training.currency_symbol', '₪'))
                            ->helperText('يجب أن يساوي رسوم الإعادة تمامًا.'),
                        DatePicker::make('paid_at')
                            ->label('تاريخ الدفع')
                            ->required()
                            ->maxDate(today()),
                        TextInput::make('receipt_number')
                            ->label('رقم الإيصال')
                            ->required()
                            ->maxLength(30)
                            ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                if (filled($value) && Payment::where('receipt_number', trim((string) $value))->exists()) {
                                    $fail('رقم الإيصال مستخدم مسبقًا.');
                                }
                            }),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, ExamAttempt $record): void {
                        try {
                            app(PaymentService::class)->recordAttemptPayment($record, $data, auth()->user());

                            Notification::make()->success()->title('تم تسجيل دفعة رسوم الإعادة')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),

                Action::make('enterResult')
                    ->label('إدخال النتيجة')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (ExamAttempt $record) => auth()->user()->can('exam_attempts.update')
                        && $record->approved_at === null)
                    ->fillForm(fn (ExamAttempt $record): array => [
                        'result' => $record->result === AttemptResult::Pending ? null : $record->result->value,
                    ])
                    ->schema([
                        Select::make('result')
                            ->label('النتيجة')
                            ->options([
                                AttemptResult::Passed->value => AttemptResult::Passed->label(),
                                AttemptResult::Failed->value => AttemptResult::Failed->label(),
                            ])
                            ->required(),
                    ])
                    ->action(function (array $data, ExamAttempt $record): void {
                        try {
                            app(ExamAttemptService::class)->enterResult(
                                $record,
                                AttemptResult::from($data['result']),
                                auth()->user(),
                            );

                            Notification::make()->success()->title('تم إدخال النتيجة')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),

                Action::make('approveResult')
                    ->label('اعتماد النتيجة')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('info')
                    ->visible(fn (ExamAttempt $record) => auth()->user()->can('results.approve')
                        && $record->result !== AttemptResult::Pending
                        && $record->approved_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('بعد الاعتماد لا يمكن تغيير النتيجة. وعند اعتماد النجاح في النظري والعملي يُغلق تسجيل الطالب بحالة «مكتمل».')
                    ->action(function (ExamAttempt $record): void {
                        try {
                            app(ExamAttemptService::class)->approveResult($record, auth()->user());

                            Notification::make()->success()->title('تم اعتماد النتيجة')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('id');
    }
}
