<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuardianAttendanceJustificationController;
use App\Http\Controllers\Api\GuardianInvoiceController;
use App\Http\Controllers\Api\GuardianStudentController;
use App\Http\Controllers\Api\TeacherSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // 1. Authentication & Profile Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });

    // 2. Teacher API Endpoints
    Route::prefix('teacher')->middleware('auth:sanctum')->group(function () {
        Route::get('/active-session', [TeacherSessionController::class, 'activeSession'])->name('api.v1.teacher.active-session');
        Route::post('/attendances/bulk-record', [TeacherSessionController::class, 'bulkRecord'])->name('api.v1.teacher.attendances.bulk-record');
    });

    // 3. Guardian API Endpoints
    Route::prefix('guardian')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/children', [GuardianStudentController::class, 'children'])->name('api.v1.guardian.children');
            Route::get('/students/{student_id}/attendances', [GuardianStudentController::class, 'attendances'])->name('api.v1.guardian.students.attendances');
            Route::post('/attendances/{attendance_id}/justify', [GuardianAttendanceJustificationController::class, 'submit'])->name('api.v1.guardian.attendances.justify');
        });

        // Backward compatibility / unauthenticated direct routes
        Route::post('/attendances/{attendance_id}/justify', [GuardianAttendanceJustificationController::class, 'submit']);
        Route::get('/students/{student_id}/invoices', [GuardianInvoiceController::class, 'index'])
            ->name('api.v1.guardian.students.invoices');
        Route::get('/receipts/{payment}/download', [GuardianInvoiceController::class, 'downloadReceipt'])
            ->name('api.v1.guardian.receipt.download');
    });
});

// Backward compatibility alias for guardian routes
Route::prefix('guardian')->group(function () {
    Route::get('/students/{student_id}/invoices', [GuardianInvoiceController::class, 'index']);
    Route::post('/attendance/justify', [GuardianAttendanceJustificationController::class, 'submit']);
    Route::post('/justifications', [GuardianAttendanceJustificationController::class, 'submit']);
});

Route::prefix('attendances')->group(function () {
    Route::post('/{attendance}/justify', [GuardianAttendanceJustificationController::class, 'submit']);
    Route::post('/justify', [GuardianAttendanceJustificationController::class, 'submit']);
});

Route::prefix('webhooks')->group(function () {
    Route::post('/guardian-justification', [GuardianAttendanceJustificationController::class, 'webhook']);
});
