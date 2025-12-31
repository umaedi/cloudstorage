<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\CloudStorageController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/cloud', [CloudStorageController::class, 'upload']);
Route::get('/cloud/stream/{pathFile}', [CloudStorageController::class, 'cloudStream']);


