<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SanPhamController;
use App\Http\Controllers\GioHangController;

Route::get('/sanpham', [SanPhamController::class, 'api']);
Route::get('/home', [HomeController::class, 'api']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/gio-hang', [GioHangController::class, 'getCart']);
    Route::post('/gio-hang/them', [GioHangController::class, 'them']);
    Route::post('/gio-hang/cap-nhat', [GioHangController::class, 'capNhat']);
    Route::post('/gio-hang/xoa', [GioHangController::class, 'xoa']);
    Route::post('/gio-hang/xoa-tat-ca', [GioHangController::class, 'xoaTatCa']);
});