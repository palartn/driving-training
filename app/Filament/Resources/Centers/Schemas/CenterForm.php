<?php

namespace App\Filament\Resources\Centers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المركز')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('اسم المركز')
                        ->required()
                        ->maxLength(150)
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'اسم المركز مسجّل مسبقًا.']),
                    TextInput::make('code')
                        ->label('رمز المركز')
                        ->required()
                        ->maxLength(20)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->helperText('أحرف وأرقام وشرطات فقط، ويجب أن يكون فريدًا.')
                        ->validationMessages(['unique' => 'رمز المركز مسجّل مسبقًا.']),
                    Select::make('governorate_id')
                        ->label('المحافظة')
                        ->relationship('governorate', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('phone')
                        ->label('هاتف المركز')
                        ->tel()
                        ->maxLength(20)
                        ->regex('/^\+?[0-9]{7,15}$/')
                        ->validationMessages(['regex' => 'رقم الهاتف يجب أن يحوي أرقامًا فقط (يُسمح بعلامة + في البداية).']),
                    TextInput::make('address')
                        ->label('العنوان')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('المركز فعّال')
                        ->helperText('المراكز لا تُحذف، وتعطيل المركز يخفيه من قوائم الاختيار.')
                        ->default(true),
                ]),
        ]);
    }
}
