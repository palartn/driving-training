<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// مسار «login» تتطلبه آلية المصادقة العامة في Laravel؛ الدخول الفعلي من لوحة Filament.
Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('enrollment-roster', [ReportController::class, 'enrollmentRoster'])->name('enrollment-roster');
    Route::get('exam-results', [ReportController::class, 'examResults'])->name('exam-results');
    Route::get('payments', [ReportController::class, 'payments'])->name('payments');
    Route::get('unpaid-subjects', [ReportController::class, 'unpaidSubjects'])->name('unpaid-subjects');
});
