<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CorrectionRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;

Route::middleware('auth')->group(function () {
    Route::get('/attendance', [
        AttendanceController::class,
        'index',
    ]);

    Route::post('/attendance', [
        AttendanceController::class,
        'store',
    ]);

    Route::get('/attendance/list', [
        AttendanceController::class,
        'list',
    ]);

    Route::get('/attendance/{id}', [
        AttendanceController::class,
        'show',
    ])->whereNumber('id');

    Route::post('/attendance/{id}', [
        CorrectionRequestController::class,
        'store',
    ])->whereNumber('id');

    Route::get('/stamp_correction_request/list', [
        CorrectionRequestController::class,
        'index',
    ]);

    Route::get('/application/{id}', [
        CorrectionRequestController::class,
        'show',
    ]);

});

Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware('guest');


Route::post('/admin/login', [
    \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class,
    'store',
])->middleware(['guest', 'throttle:login']);

Route::get('/admin/attendance/list', [
    AdminAttendanceController::class,
    'index',
])->middleware('auth');

Route::get('/', function () {
    return redirect('/login');
});
