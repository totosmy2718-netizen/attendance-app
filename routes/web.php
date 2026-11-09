<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CorrectionRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\CorrectionRequestController as AdminCorrectionRequestController;

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

    Route::get('/attendance/{id}', function ($id) {
        if (auth()->user()->admin_status) {
            return redirect('/admin/attendance/' . $id);
        }

        return app(AttendanceController::class)->show($id);
    })->whereNumber('id');


    Route::get('/admin/attendance/{id}', [
        AdminAttendanceController::class,
        'show',
    ])->whereNumber('id');

    Route::post('/attendance/{id}', function ($id) {
        if (auth()->user()->admin_status) {
            return app()->call(
                [app(AdminAttendanceController::class), 'update'],
                ['id' => $id]
            );
        }

        return app()->call(
            [app(CorrectionRequestController::class), 'store'],
            ['id' => $id]
        );
    })->whereNumber('id');

    Route::get('/stamp_correction_request/list', [
        CorrectionRequestController::class,
        'index',
    ]);

    Route::get('/application/{id}', [
        CorrectionRequestController::class,
        'show',
    ]);

    Route::post('/admin/logout', [
        \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class,
        'destroy',
    ]);

    Route::get('/admin/staff/list', [
        StaffController::class,
        'index',
    ]);

    Route::get('/admin/attendance/staff/{id}', [
        StaffController::class,
        'show',
    ])->whereNumber('id');

    Route::get('/stamp_correction_request/list', function () {
        if (auth()->user()->admin_status) {
            return app(AdminCorrectionRequestController::class)->index();
        }

        return app(CorrectionRequestController::class)->index();
    });

    Route::get('/stamp_correction_request/approve/{id}', [
        AdminCorrectionRequestController::class,
        'show',
    ])->whereNumber('id');

    Route::post('/stamp_correction_request/approve/{id}', [
        AdminCorrectionRequestController::class,
        'approve',
    ])->whereNumber('id');

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
