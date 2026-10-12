<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/attendance', function () {
    return view('attendance-test');
})->middleware('auth');

Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware('guest');


Route::post('/admin/login', [
    \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class,
    'store',
])->middleware(['guest', 'throttle:login']);

Route::get('/admin/attendance/list', function () {
    abort_unless(auth()->user()->admin_status, 403);

    return view('attendance-test');
})->middleware('auth');

Route::get('/', function () {
    return redirect('/login');
});
