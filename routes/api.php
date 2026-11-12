<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group([
    'prefix' => 'auth',
    'as' => 'auth.',
], function() {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::group([
    'prefix' => 'profile',
    'as' => 'profile.',
    'middleware' => 'auth:sanctum',
], function() {
    Route::get('/', [ProfileController::class, 'profile']);
    Route::post('/change-image', [ProfileController::class, 'updateImage']);
    Route::post('/change-password', [ProfileController::class, 'updatePassword']);
});

Route::group([
    'prefix' => 'dashboard',
    'as' => 'dashboard.',
    'middleware' => 'auth:sanctum',
], function() {
    Route::get('/', [DashboardController::class, 'list']);
});

/*
| FCM device registration. Login also accepts the token inline, this group
| covers the onTokenRefresh case when the app is already authenticated.
*/
Route::group([
    'prefix' => 'devices',
    'as' => 'devices.',
    'middleware' => 'auth:sanctum',
], function() {
    Route::post('/', [DeviceController::class, 'store']);
    Route::delete('/', [DeviceController::class, 'destroy']);
});

Route::group([
    'prefix' => 'guest',
    'as' => 'guest.',
    'middleware' => 'auth:sanctum'
], function() {
    Route::get('list', [GuestController::class, 'list']);
    Route::get('{id}/photo', [GuestController::class, 'photo']);
    Route::post('accept/{id}', [GuestController::class, 'accept']);
    Route::post('reject/{id}', [GuestController::class, 'reject']);
    Route::post('reschedule/{id}', [GuestController::class, 'reschedule']);
    Route::post('complete/{id}', [GuestController::class, 'complete']);
});
