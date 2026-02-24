<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login'])->name('login');

Route::get('/cms/dashboard', [App\Http\Controllers\CMS\DashboardController::class, 'index']);