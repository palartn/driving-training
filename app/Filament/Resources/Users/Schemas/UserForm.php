<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Support\RoleLabel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الحساب')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('الاسم')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('username')
                        ->label('اسم المستخدم')
                        ->required()
                        ->maxLength(60)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'اسم المستخدم مستخدم مسبقًا.']),
                    TextInput::make('email')
                        ->label('البريد الإلكتروني')
                        ->email()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->validationMessages(['unique' => 'البريد مستخدم مسبقًا.']),
                    TextInput::make('password')
                        ->label('كلمة المرور')
                        ->password()
                        ->revealable()
                        ->minLength(10)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('10 أحرف على الأقل. عند التعديل: اتركها فارغة للإبقاء على الحالية.'),
                ]),

            Section::make('الدور والمركز')
                ->columns(2)
                ->schema([
                    Select::make('role')
                        ->label('الدور')
                        ->options(fn () => RoleLabel::assignableOptions(auth()->user()))
                        ->required()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateHydrated(function (Select $component, ?User $record) {
                            $component->state($record?->roles->first()?->name);
                        })
                        ->disabled(fn (?User $record) => $record !== null && $record->is(auth()->user()))
                        ->helperText('تُعرض الأدوار الأدنى من دورك فقط (والمدير العام يرى كلها).'),
                    Select::make('center_id')
                        ->label('المركز')
                        ->relationship(
                            'center',
                            'name',
                            modifyQueryUsing: fn (Builder $query) => auth()->user()?->center_id
                                ? $query->whereKey(auth()->user()->center_id)
                                : $query->where('is_active', true),
                        )
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get) => $get('role') !== 'general_manager')
                        ->helperText('يُترك فارغًا للمدير العام فقط؛ بقية الأدوار مرتبطة بمركز.'),
                    Toggle::make('is_active')
                        ->label('الحساب فعّال')
                        ->default(true)
                        ->disabled(fn (?User $record) => $record !== null && $record->is(auth()->user()))
                        ->helperText('تعطيل الحساب يمنع الدخول دون حذفه.'),
                ]),
        ]);
    }
}
