<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // تسلسل الأدوار: 4 مدير عام، 3 مدير، 2 رئيس قسم، 1 موظف.
            $table->unsignedTinyInteger('hierarchy_level')->default(0)->after('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('hierarchy_level');
        });
    }
};
