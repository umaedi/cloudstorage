<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/cloud', [API\CloudStorageController::class, 'upload']);
Route::get('/cloud/stream/{pathFile}', [API\CloudStorageController::class, 'cloudStream']);

Route::get('/instagram', [API\InstagramController::class, 'index']);
Route::get('/instagram/{id}', [API\InstagramController::class, 'show']);

Route::prefix('fcm-token')->group(function() {
    Route::controller(API\FCMController::class)->group(function() {
        Route::get('/', 'getTokens');
        Route::post('/store', 'store');
    });
});

Route::prefix('notification')->group(function() {
    Route::controller(API\NotificationController::class)->group(function() {
        Route::get('/', 'index');
        Route::get('/count', 'count');
        Route::get('/show', 'show');
        Route::post('/store', 'store');
        Route::post('/update', 'update');
        Route::post('/destroy/{id}', 'destroy');
    });
});


    