<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CorrectionRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\CorrectionRequestController as AdminCorrectionRequestController;


Route::middleware('auth')->group(function () {
    // PG03：勤怠登録画面（一般ユーザー）
    Route::get('/attendance', [
        AttendanceController::class,
        'index',
    ]);

    // PG03：勤怠登録画面からの打刻処理
    Route::post('/attendance', [
        AttendanceController::class,
        'store',
    ]);

    // PG04：勤怠一覧画面（一般ユーザー）
    Route::get('/attendance/list', [
        AttendanceController::class,
        'list',
    ]);

    // PG05・PG09：権限に応じた勤怠詳細への振り分け
    Route::get('/attendance/{id}', function ($id) {
        // PG09：勤怠詳細画面（管理者）へ移動
        if (auth()->user()->admin_status) {
            return redirect('/admin/attendance/' . $id);
        }
        // PG05：勤怠詳細画面（一般ユーザー）
        return app(AttendanceController::class)->show($id);
    })->whereNumber('id');

    // PG09：勤怠詳細画面（管理者）
    Route::get('/admin/attendance/{id}', [
        AdminAttendanceController::class,
        'show',
    ])->whereNumber('id');

    // PG05・PG09：勤怠詳細画面からの送信処理
    Route::post('/attendance/{id}', function ($id) {
        // PG09：管理者による勤怠修正
        if (auth()->user()->admin_status) {
            return app()->call(
                [app(AdminAttendanceController::class), 'update'],
                ['id' => $id]
            );
        }

        // PG05：一般ユーザーによる修正申請
        return app()->call(
            [app(CorrectionRequestController::class), 'store'],
            ['id' => $id]
        );
    })->whereNumber('id');

    // PG06の詳細リンクからPG05へ移動
    Route::get('/application/{id}', [
        CorrectionRequestController::class,
        'show',
    ]);

    // 画面IDなし：管理者のログアウト処理
    Route::post('/admin/logout', [
        \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class,
        'destroy',
    ]);

    // PG10：スタッフ一覧画面（管理者）
    Route::get('/admin/staff/list', [
        StaffController::class,
        'index',
    ]);

    // PG11：スタッフ別勤怠一覧画面（管理者）
    Route::get('/admin/attendance/staff/{id}', [
        StaffController::class,
        'show',
    ])->whereNumber('id');

    // PG06・PG12：権限に応じた申請一覧の表示
    Route::get('/stamp_correction_request/list', function () {
        // PG12：申請一覧画面（管理者）
        if (auth()->user()->admin_status) {
            return app(AdminCorrectionRequestController::class)->index();
        }
        // PG06：申請一覧画面（一般ユーザー）
        return app(CorrectionRequestController::class)->index();
    });

    // PG13：修正申請承認画面（管理者）
    Route::get('/stamp_correction_request/approve/{id}', [
        AdminCorrectionRequestController::class,
        'show',
    ])->whereNumber('id');

    // PG13：修正申請承認画面からの承認処理
    Route::post('/stamp_correction_request/approve/{id}', [
        AdminCorrectionRequestController::class,
        'approve',
    ])->whereNumber('id');
});

// PG07：ログイン画面（管理者）
Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware('guest');

// PG07：管理者ログイン画面からの認証処理
Route::post('/admin/login', [
    \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class,
    'store',
])->middleware(['guest', 'throttle:login']);

// PG08：勤怠一覧画面（管理者）
Route::get('/admin/attendance/list', [
    AdminAttendanceController::class,
    'index',
])->middleware('auth');

// 画面IDなし：トップページからPG02へ移動
Route::get('/', function () {
    return redirect('/login');
});