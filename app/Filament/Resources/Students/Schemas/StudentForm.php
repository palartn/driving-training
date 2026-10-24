<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        $phoneRule = '/^\+?[0-9]{7,15}$/';
        $phoneMessage = 'رقم الهاتف يجب أن يحوي أرقامًا فقط (يُسمح بعلامة + في البداية).';

        return $schema->components([
            Section::make('بيانات الطالب')
                ->columns(2)
                ->schema([
                    TextInput::make('national_id')
                        ->label('رقم الهوية')
                        ->required()
                        ->inputMode('numeric')
                        ->maxLength(9)
                        ->regex('/^[0-9]{9}$/')
                        ->unique(ignoreRecord: true)
                        ->validationMessages([
                            'regex' => 'رقم الهوية يجب أن يتكون من 9 أرقام.',
                            'unique' => 'رقم الهوية مسجّل مسبقًا.',
                        ])
                        ->columnSpanFull(),
                    TextInput::make('first_name')->label('الاسم الأول')->required()->maxLength(50),
                    TextInput::make('second_name')->label('اسم الأب')->required()->maxLength(50),
                    TextInput::make('third_name')->label('اسم الجد')->required()->maxLength(50),
                    TextInput::make('fourth_name')->label('اسم العائلة')->required()->maxLength(50),
                ]),

            Section::make('الاتصال والسكن')
                ->columns(2)
                ->schema([
                    TextInput::make('phone')
                        ->label('رقم الهاتف')
                        ->required()
                        ->tel()
                        ->maxLength(20)
                        ->regex($phoneRule)
                        ->validationMessages(['regex' => $phoneMessage]),
                    TextInput::make('phone_secondary')
                        ->label('رقم هاتف احتياطي')
                        ->tel()
                        ->maxLength(20)
                        ->regex($phoneRule)
                        ->validationMessages(['regex' => $phoneMessage]),
                    TextInput::make('address')
                        ->label('عنوان السكن')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Select::make('governorate_id')
                        ->label('المحافظة')
                        ->relationship('governorate', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('center_id')
                        ->label('المركز')
                        ->relationship(
                            'center',
                            'name',
                            modifyQueryUsing: fn (Builder $query) => auth()->user()?->center_id
                                ? $query->whereKey(auth()->user()->center_id)
                                : $query->where('is_active', true),
                        )
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn () => auth()->user()?->center_id)
                        ->disabled(fn () => auth()->user()?->center_id !== null)
                        ->dehydrated(),
                ]),
        ]);
    }
}
