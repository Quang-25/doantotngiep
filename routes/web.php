<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SanPhamController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ThanhToanController;
use App\Http\Controllers\TinTucController;
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

Route::get('/san-pham/{id}', [SanPhamController::class, 'chiTietSanPham'])->name('sanpham.chitiet');



Route::get('/tin-tuc-khuyen-mai', [TinTucController::class, 'index'])->name('tintuc.index');
Route::get('/tin-tuc/{id}', [TinTucController::class, 'show'])->name('tintuc.show');