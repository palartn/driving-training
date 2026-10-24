<?php

namespace App\Filament\Resources\CourseTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('نوع الدورة')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('اسم النوع')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'هذا النوع مسجّل مسبقًا.']),
                    Toggle::make('is_active')
                        ->label('فعّال')
                        ->default(true)
                        ->helperText('الأنواع لا تُحذف، وتعطيل النوع يخفيه من قوائم الاختيار.'),
                ]),
        ]);
    }
}
