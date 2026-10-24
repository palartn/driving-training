<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Enums\PricingMode;
use App\Models\SubjectTariff;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class TariffsRelationManager extends RelationManager
{
    protected static string $relationship = 'tariffs';

    protected static ?string $title = 'سعر المادة (سجل الأسعار)';

    /**
     * طريقة تسعير واحدة: سعر المادة مباشرة.
     * يُخزَّن في fixed_price مع pricing_mode = fixed (الأعمدة الأخرى في الجدول غير مستعملة).
     */
    private static function toModelData(array $data): array
    {
        $data['pricing_mode'] = PricingMode::Fixed->value;
        $data['lecture_price'] = null;
        $data['created_by'] = auth()->id();

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('fixed_price')
                ->label('سعر المادة')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->suffix(config('training.currency_symbol', '₪'))
                ->required(),
            TextInput::make('lectures_count')
                ->label('عدد المحاضرات')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(65535)
                ->required()
                ->helperText('للمعلومات فقط؛ لا يدخل في حساب السعر.'),
            DatePicker::make('effective_from')
                ->label('يسري من تاريخ')
                ->default(today())
                ->required()
                ->unique(
                    SubjectTariff::class,
                    'effective_from',
                    modifyRuleUsing: fn (Unique $rule, RelationManager $livewire) => $rule
                        ->where('subject_id', $livewire->getOwnerRecord()->getKey()),
                )
                ->validationMessages(['unique' => 'يوجد سعر بهذا التاريخ لهذه المادة.'])
                ->helperText('لا تُعدَّل الأسعار القديمة؛ يُضاف سعر جديد بتاريخ سريان فتبقى أسعار الطلاب المسجّلين كما كانت.'),
        ]);
    }

    public function table(Table $table): Table
    {
        $currency = ' '.config('training.currency_symbol', '₪');

        return $table
            ->recordTitleAttribute('effective_from')
            ->columns([
                TextColumn::make('effective_from')
                    ->label('يسري من')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label('سعر المادة')
                    ->suffix($currency)
                    ->weight('bold'),
                TextColumn::make('lectures_count')
                    ->label('عدد المحاضرات'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة سعر جديد')
                    ->mutateDataUsing(fn (array $data): array => self::toModelData($data)),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
