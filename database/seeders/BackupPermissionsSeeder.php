<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * إضافة صلاحيات النسخ الاحتياطي للمدير العام فقط، دون تعديل صلاحيات أي دور آخر.
 * آمن للتشغيل على قاعدة فيها تخصيصات للأدوار (على عكس RolesAndPermissionsSeeder الذي يعيد مزامنتها).
 */
class BackupPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = ['backups.view', 'backups.create', 'backups.download'];

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'general_manager')->where('guard_name', 'web')->first()?->givePermissionTo($names);
    }
}
