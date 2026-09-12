<?php

namespace App\Http\Controllers;

use App\Models\TaiKhoan;
use App\Models\KhachHang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Đăng ký tài khoản
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'SoDienThoai' => ['required', 'string', 'max:15'],
            'DiaChi' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (TaiKhoan::where('Email', $validated['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email này đã được đăng ký.'
            ], 422);
        }

        if (KhachHang::where('SoDienThoai', $validated['SoDienThoai'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Số điện thoại này đã được sử dụng.'
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($validated) {
                // Mã hoá mật khẩu bằng Hash::make trước khi lưu
                $taiKhoan = TaiKhoan::create([
                    'Email' => $validated['email'],
                    'MatKhau' => Hash::make($validated['password']),
                    'VaiTro' => 'KhachHang',
                    'TrangThai' => 'HoatDong',
                ]);

                $khachHang = KhachHang::create([
                    'ID_TaiKhoan' => $taiKhoan->ID_TaiKhoan,
                    'HoTen' => $validated['name'],
                    'SoDienThoai' => $validated['SoDienThoai'],
                    'DiaChi' => $validated['DiaChi']
                ]);

                return [
                    'taiKhoan' => $taiKhoan,
                    'khachHang' => $khachHang,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Đăng ký tài khoản thành công.',
                'user' => [ // Đổi từ 'data' thành 'user' để đồng bộ với Frontend
                    'ID_TaiKhoan' => $result['taiKhoan']->ID_TaiKhoan,
                    'ID_KhachHang' => $result['khachHang']->ID_KhachHang,
                    'HoTen' => $result['khachHang']->HoTen,
                    'Email' => $result['taiKhoan']->Email,
                    'SoDienThoai' => $result['khachHang']->SoDienThoai,
                    'VaiTro' => $result['taiKhoan']->VaiTro,
                    'TrangThai' => $result['taiKhoan']->TrangThai,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đăng ký thất bại. Vui lòng thử lại.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Đăng nhập bằng API
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $taiKhoan = TaiKhoan::where('Email', $validated['email'])->first();

        // Sử dụng Hash::check là hoàn toàn chính xác
        if (!$taiKhoan || !Hash::check($validated['password'], $taiKhoan->MatKhau)) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng.'
            ], 401);
        }

        if ($taiKhoan->TrangThai === 'Khoa') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa.'
            ], 403);
        }

        // Lấy thông tin khách hàng thông qua relationship
        $khachHang = $taiKhoan->khachHang;

        // Lưu thông tin tài khoản đang đăng nhập vào Session Laravel
        $request->session()->regenerate();
        
        $request->session()->put([
            'ID_TaiKhoan' => $taiKhoan->ID_TaiKhoan,
            'ID_KhachHang' => $khachHang ? $khachHang->ID_KhachHang : null,
            'HoTen' => $khachHang ? $khachHang->HoTen : 'Quản trị viên', // Fallback cho Admin nếu Admin không có dòng trong bảng KhachHang
            'Email' => $taiKhoan->Email,
            'VaiTro' => $taiKhoan->VaiTro,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'user' => [ // Đổi từ 'data' thành 'user' để JS đọc được
                'ID_TaiKhoan' => $taiKhoan->ID_TaiKhoan,
                'ID_KhachHang' => $khachHang ? $khachHang->ID_KhachHang : null,
                'HoTen' => $khachHang ? $khachHang->HoTen : 'Quản trị viên',
                'Email' => $taiKhoan->Email,
                'SoDienThoai' => $khachHang ? $khachHang->SoDienThoai : null,
                'VaiTro' => $taiKhoan->VaiTro,
                'TrangThai' => $taiKhoan->TrangThai,
            ]
        ]);
    }

    // Lấy thông tin người dùng đang đăng nhập
    public function user(Request $request): JsonResponse
    {
        if (!$request->session()->has('ID_TaiKhoan')) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa đăng nhập.'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [ // Đổi từ 'data' thành 'user'
                'ID_TaiKhoan' => $request->session()->get('ID_TaiKhoan'),
                'ID_KhachHang' => $request->session()->get('ID_KhachHang'),
                'HoTen' => $request->session()->get('HoTen'),
                'Email' => $request->session()->get('Email'),
                'VaiTro' => $request->session()->get('VaiTro'),
            ]
        ]);
    }

    // Đăng xuất bằng API
    public function logout(Request $request): JsonResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.'
        ]);
    }
}