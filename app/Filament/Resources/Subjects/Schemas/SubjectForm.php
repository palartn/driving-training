<?php

namespace App\Filament\Resources\Subjects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المادة')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('اسم المادة')
                        ->required()
                        ->maxLength(150)
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'هذه المادة مسجّلة مسبقًا.']),
                    Toggle::make('is_active')
                        ->label('فعّالة')
                        ->default(true)
                        ->helperText('المواد لا تُحذف، وتعطيل المادة يخفيها من قوائم اختيار مواد الدورات.'),
                ]),
        ]);
    }
}
