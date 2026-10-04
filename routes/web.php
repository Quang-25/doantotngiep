<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SanPhamController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ThanhToanController;
use App\Http\Controllers\TinTucController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminSanPhamController;
use App\Http\Controllers\Admin\AdminKhachHangController;
use App\Http\Controllers\Admin\AdminDonHangController;
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

// ==========================================
// B. CÁC ROUTE DÀNH CHO QUẢN TRỊ VIÊN (BACKEND)
// ==========================================
Route::prefix('admin')->middleware(['admin.auth'])->group(function () {
    
    // Trang tổng quan (Vẫn giữ nguyên trỏ vào AdminController)
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    
    // Quản lý sản phẩm (ĐỔI TẤT CẢ SANG AdminSanPhamController)
    Route::get('/san-pham', [AdminSanPhamController::class, 'quanLySanPham']);
    Route::post('/api/san-pham', [AdminSanPhamController::class, 'themSanPham']);
    Route::post('/api/san-pham/{id}', [AdminSanPhamController::class, 'suaSanPham']); 
    Route::delete('/api/san-pham/{id}', [AdminSanPhamController::class, 'xoaSanPham']);

    // QUẢN LÝ KHÁCH HÀNG
    Route::get('/khach-hang', [AdminKhachHangController::class, 'quanLyKhachHang']);
    Route::post('/api/khach-hang/{id}', [AdminKhachHangController::class, 'suaKhachHang']); 
    Route::delete('/api/khach-hang/{id}', [AdminKhachHangController::class, 'xoaKhachHang']);

    // QUẢN LÝ ĐƠN HÀNG
    Route::get ('/don-hang', [AdminDonHangController::class, 'quanLyDonHang']);
    Route::get ('/api/don-hang/{id}', [AdminDonHangController::class, 'chiTietDonHang']);
    Route::post('/api/don-hang/{id}', [AdminDonHangController::class, 'capNhatTrangThai']);
    
});