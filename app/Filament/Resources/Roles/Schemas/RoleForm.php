<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\PermissionLabel;
use App\Support\RoleLabel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الدور')
                ->columns(2)
                ->schema([
                    Hidden::make('guard_name')->default('web'),
                    TextInput::make('name')
                        ->label('اسم الدور (بالإنجليزية، بلا مسافات)')
                        ->required()
                        ->maxLength(100)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?Role $record) => $record !== null && RoleLabel::isCore($record->name))
                        ->dehydrated()
                        ->helperText('أسماء الأدوار الأساسية الأربعة لا تُغيَّر.'),
                    Select::make('hierarchy_level')
                        ->label('المرتبة الوظيفية')
                        ->options(RoleLabel::levelOptions())
                        ->required()
                        ->disabled(fn (?Role $record) => $record !== null && RoleLabel::isCore($record->name))
                        ->dehydrated()
                        ->helperText('تحدد من يستطيع إدارة هذا الدور: لا يدير المستخدم إلا من هم أدنى منه مرتبة.'),
                ]),

            Section::make('الصلاحيات')
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('الصلاحيات الممنوحة')
                        ->relationship('permissions', 'name')
                        ->getOptionLabelFromRecordUsing(fn (Permission $record) => PermissionLabel::label($record->name))
                        ->columns(3)
                        ->searchable()
                        ->bulkToggleable()
                        ->disabled(fn (?Role $record) => $record?->name === 'general_manager')
                        ->helperText('المدير العام يملك كل الصلاحيات دائمًا ولا تُعدَّل.'),
                ]),
        ]);
    }
}
