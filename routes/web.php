<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FrontController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
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

Route::get('/', [FrontController::class, 'guestForm'])->name('guest.add');
Route::post('/', [FrontController::class, 'guestCreate']);

Route::get('/survey', [FrontController::class, 'surveyForm'])->name('survey.add');
Route::post('/survey', [FrontController::class, 'surveyCreate']);

Route::get('/chart/{id}', [FrontController::class, 'chart'])->name('chart');
Route::get('/download/report-{id}.xlsx', [FrontController::class, 'download'])->name('download');

Route::get('/test-email', [FrontController::class, 'testEmail'])->name('test.email');
Route::get('/test-fcm/{id}', [FrontController::class, 'testFcm'])->name('test.fcm');

Route::get('/privacy-policy', [FrontController::class, 'privacy'])->name('front.privacy');

Route::group([
    'prefix' => 'user',
    'as' => 'user.',
    'middleware' => ['auth', 'admin'],
], function() {
    Route::get('/add', [UserController::class, 'form'])->name('add');
    Route::post('/add', [UserController::class, 'store']);
    Route::get('/edit/{id}', [UserController::class, 'edit'])->name('edit');
    Route::post('/edit/{id}', [UserController::class, 'update']);
    Route::get('/show/{id}', [UserController::class, 'show'])->name('show');
    Route::get('/list', [UserController::class,'index'])->name('list');
    Route::post('/delete/{id}', [UserController::class, 'delete'])->name('delete');
    Route::post('/reset-password/{id}', [UserController::class, 'resetPassword'])->name('reset-password');
});

Route::group([
    'prefix' => 'setting',
    'as' => 'setting.',
    'middleware' => 'auth',
], function() {
    Route::get('/edit/{code}', [SettingController::class, 'edit'])->name('edit');
    Route::post('/edit/{code}', [SettingController::class, 'update']);
    Route::get('/list', [SettingController::class,'index'])->name('list');
});

Route::group([
    'prefix' => 'report',
    'as' => 'report.',
    'middleware' => 'auth',
], function() {
    Route::get('/form', [ReportController::class, 'form'])->name('form');
    Route::get('/download', [ReportController::class, 'download'])->name('download');

    Route::group([
        'prefix' => 'review',
        'as' => 'review.',
    ], function() {
        Route::get('/form', [ReportController::class, 'reviewForm'])->name('form');
        Route::get('/download', [ReportController::class, 'reviewDownload'])->name('download');
    });
});

Route::group([
    'prefix' => 'auth',
    'as' => 'auth.',
    'middleware' => 'guest',
], function() {
    Route::get('/login', [AuthController::class,'form'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');