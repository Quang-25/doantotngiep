<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SanPhamController;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('Home');
});

Route::get('/sanpham', [SanPhamController::class, 'index'])->name('sanpham');

Route::get('/Dangky', function () {
    return view('DangKy');
});

Route::get('/Dangnhap', function () {
    return view('DangNhap');
});

