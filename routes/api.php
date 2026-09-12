<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SanPhamController;
use App\Http\Controllers\GioHangController;
use Illuminate\Session\Middleware\StartSession;

Route::post('/gio-hang/them', [GioHangController::class, 'them']);

Route::get('/sanpham', [SanPhamController::class, 'api']);

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware(StartSession::class);

Route::get('/user', [AuthController::class, 'user'])
    ->middleware(StartSession::class);

Route::get('/home', [HomeController::class, 'api']);

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(StartSession::class);