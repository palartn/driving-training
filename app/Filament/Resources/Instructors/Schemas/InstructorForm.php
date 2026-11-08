<?php

namespace App\Filament\Resources\Instructors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class InstructorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المدرب')
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
                        ]),
                    TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(150),
                    TextInput::make('phone')
                        ->label('رقم الهاتف')
                        ->required()
                        ->tel()
                        ->maxLength(20)
                        ->regex('/^\+?[0-9]{7,15}$/')
                        ->validationMessages(['regex' => 'رقم الهاتف يجب أن يحوي أرقامًا فقط (يُسمح بعلامة + في البداية).']),
                    TextInput::make('specialization')
                        ->label('التخصص')
                        ->maxLength(150),
                    Select::make('centers')
                        ->label('المراكز التي يدرّس فيها')
                        ->relationship(
                            'centers',
                            'name',
                            modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->required()
                        ->visible(fn () => auth()->user()?->center_id === null)
                        ->helperText('يجوز أن يدرّس المدرب في أكثر من مركز.')
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('المدرب فعّال')
                        ->default(true)
                        ->helperText('المدربون لا يُحذفون، وتعطيل المدرب يخفيه من قوائم الإسناد.'),
                ]),
        ]);
    }
}
