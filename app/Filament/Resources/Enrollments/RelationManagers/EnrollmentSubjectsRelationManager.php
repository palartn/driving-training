<?php

namespace App\Filament\Resources\Enrollments\RelationManagers;

use App\Enums\FinancialStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\EnrollmentSubject;
use App\Models\Payment;
use App\Services\PaymentService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentSubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollmentSubjects';

    protected static ?string $title = 'مواد التسجيل والحالة المالية';

    /** الإجراءات (دفع/إعفاء) مسموحة في صفحة العرض. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $currency = ' '.config('training.currency_symbol', '₪');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subject', 'payment', 'exemption', 'enrollment.course']))
            ->columns([
                TextColumn::make('subject.name')
                    ->label('المادة'),
                TextColumn::make('lectures_count')
                    ->label('المحاضرات'),
                TextColumn::make('price_amount')
                    ->label('السعر')
                    ->suffix($currency),
                TextColumn::make('exemption.exemption_amount')
                    ->label('الإعفاء')
                    ->suffix($currency)
                    ->placeholder('-'),
                TextColumn::make('due')
                    ->label('المستحق')
                    ->state(fn (EnrollmentSubject $record) => number_format($record->dueCents() / 100, 2))
                    ->suffix($currency),
                TextColumn::make('financial_status')
                    ->label('الحالة المالية')
                    ->state(fn (EnrollmentSubject $record) => $record->financialStatus())
                    ->badge(),
                TextColumn::make('payment.receipt_number')
                    ->label('رقم الإيصال')
                    ->placeholder('-'),
                TextColumn::make('payment.paid_at')
                    ->label('تاريخ الدفع')
                    ->date()
                    ->placeholder('-'),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('تسجيل دفعة')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (EnrollmentSubject $record) => auth()->user()->can('payments.create')
                        && $record->financialStatus() === FinancialStatus::Unpaid)
                    ->fillForm(fn (EnrollmentSubject $record): array => [
                        'amount' => number_format($record->dueCents() / 100, 2, '.', ''),
                        'paid_at' => today()->toDateString(),
                    ])
                    ->schema([
                        TextInput::make('amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            ->required()
                            ->suffix(config('training.currency_symbol', '₪'))
                            ->helperText('يجب أن يساوي المستحق بعد الإعفاء تمامًا.'),
                        DatePicker::make('paid_at')
                            ->label('تاريخ الدفع')
                            ->required()
                            ->maxDate(today()),
                        // قاعدة مخصصة بدل unique(): الأخيرة تتجاهل «سجل الإجراء» (المادة) فتولّد استعلامًا خاطئًا.
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
                    ->action(function (array $data, EnrollmentSubject $record): void {
                        try {
                            app(PaymentService::class)->recordSubjectPayment($record, $data, auth()->user());

                            Notification::make()->success()->title('تم تسجيل الدفعة')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),

                Action::make('exempt')
                    ->label('إعفاء')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->color('info')
                    ->visible(fn (EnrollmentSubject $record) => auth()->user()->can('exemptions.create')
                        && $record->payment === null
                        && $record->exemption === null)
                    ->schema([
                        TextInput::make('exemption_amount')
                            ->label('مبلغ الإعفاء')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->suffix(config('training.currency_symbol', '₪'))
                            ->helperText('الإعفاء الكلي: أدخل سعر المادة كاملًا. ولا يتجاوز سعر المادة.'),
                        Textarea::make('reason')
                            ->label('سبب الإعفاء')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('سيُسجَّل اسمك كمعتمِد للإعفاء، ولا يمكن تعديله بعد الحفظ.')
                    ->action(function (array $data, EnrollmentSubject $record): void {
                        try {
                            app(PaymentService::class)->addExemption(
                                $record,
                                $data['exemption_amount'],
                                $data['reason'],
                                auth()->user(),
                            );

                            Notification::make()->success()->title('تم تسجيل الإعفاء')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }
}
