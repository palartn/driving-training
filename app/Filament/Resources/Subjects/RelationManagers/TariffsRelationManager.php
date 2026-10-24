<?php

namespace App\Filament\Resources\Subjects\RelationManagers;

use App\Enums\PricingMode;
use App\Models\SubjectTariff;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class TariffsRelationManager extends RelationManager
{
    protected static string $relationship = 'tariffs';

    protected static ?string $title = 'تعريفة المادة (سجل الأسعار)';

    /** قيمة نمط التسعير كنص، سواء وصلت كنص أو ككائن Enum. الافتراضي: سعر ثابت. */
    private static function modeValue(mixed $state): string
    {
        $value = $state instanceof BackedEnum ? $state->value : $state;

        return filled($value) ? (string) $value : PricingMode::Fixed->value;
    }

    /**
     * حقل سعر واحد دائم الظهور (لا يعتمد على الإخفاء الشرطي)،
     * ويُحوَّل عند الحفظ إلى fixed_price أو lecture_price بحسب النمط.
     */
    private static function toModelData(array $data): array
    {
        $price = $data['unit_price'] ?? null;
        unset($data['unit_price']);

        if (self::modeValue($data['pricing_mode'] ?? null) === PricingMode::PerLecture->value) {
            $data['lecture_price'] = $price;
            $data['fixed_price'] = null;
        } else {
            $data['fixed_price'] = $price;
            $data['lecture_price'] = null;
        }

        $data['created_by'] = auth()->id();

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('pricing_mode')
                ->label('طريقة التسعير')
                ->options(PricingMode::class)
                ->default(PricingMode::Fixed->value)
                ->required()
                ->live(),
            TextInput::make('unit_price')
                ->label(fn (Get $get) => self::modeValue($get('pricing_mode')) === PricingMode::PerLecture->value
                    ? 'سعر المحاضرة الواحدة'
                    : 'سعر المادة')
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
                ->required(),
            DatePicker::make('effective_from')
                ->label('تسري من تاريخ')
                ->default(today())
                ->required()
                ->unique(
                    SubjectTariff::class,
                    'effective_from',
                    modifyRuleUsing: fn (Unique $rule, RelationManager $livewire) => $rule
                        ->where('subject_id', $livewire->getOwnerRecord()->getKey()),
                )
                ->validationMessages(['unique' => 'توجد تعريفة بهذا التاريخ لهذه المادة.'])
                ->helperText('لا تُعدَّل التعريفات القديمة؛ يُضاف سجل جديد بتاريخ سريان فتبقى أسعار الطلاب المسجّلين كما كانت.'),
        ]);
    }

    public function table(Table $table): Table
    {
        $currency = ' '.config('training.currency_symbol', '₪');

        return $table
            ->recordTitleAttribute('effective_from')
            ->columns([
                TextColumn::make('effective_from')
                    ->label('تسري من')
                    ->date()
                    ->sortable(),
                TextColumn::make('pricing_mode')
                    ->label('طريقة التسعير')
                    ->badge(),
                TextColumn::make('fixed_price')
                    ->label('السعر الثابت')
                    ->suffix($currency)
                    ->placeholder('-'),
                TextColumn::make('lecture_price')
                    ->label('سعر المحاضرة')
                    ->suffix($currency)
                    ->placeholder('-'),
                TextColumn::make('lectures_count')
                    ->label('عدد المحاضرات'),
                TextColumn::make('total_price')
                    ->label('الإجمالي')
                    ->suffix($currency)
                    ->weight('bold'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة تعريفة جديدة')
                    ->mutateDataUsing(fn (array $data): array => self::toModelData($data)),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
