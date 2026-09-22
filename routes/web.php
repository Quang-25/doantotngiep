<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SanPhamController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ThanhToanController;

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

Route::get('/giohang', function () {
    return view('GioHang');
});

Route::post('/thanhtoan/vnpay', [ThanhToanController::class, 'processCheckout'])->name('thanhtoan.vnpay');

Route::get('/thanhtoan', [ThanhToanController::class, 'index']);

Route::get('/thanh-toan-thanh-cong', [App\Http\Controllers\ThanhToanController::class, 'vnpayReturn']);