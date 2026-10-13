<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminTaiKhoanController extends Controller
{
    // 1. Xem danh sách & Tìm kiếm
    public function quanLyTaiKhoan(Request $request)
    {
        $search = $request->input('search');
        $query = DB::table('taikhoan');

        if (!empty($search)) {
            $query->where('Email', 'LIKE', '%' . $search . '%');
        }

        $taiKhoans = $query->orderBy('ID_TaiKhoan', 'desc')->paginate(10);
        $taiKhoans->appends(['search' => $search]);

        return view('admin.quanlytaikhoan', compact('taiKhoans', 'search'));
    }

    // 2. Thêm mới tài khoản
    public function themTaiKhoan(Request $request)
    {
        try {
            $checkEmail = DB::table('taikhoan')->where('Email', $request->Email)->exists();
            if ($checkEmail) {
                return response()->json(['success' => false, 'message' => 'Email này đã tồn tại!']);
            }

            DB::table('taikhoan')->insert([
                'Email' => $request->Email,
                'MatKhau' => Hash::make($request->MatKhau), // Mã hóa mật khẩu
                'VaiTro' => $request->VaiTro,
                'TrangThai' => $request->TrangThai,
                'NgayTao' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Tạo tài khoản thành công!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
    }

    // 3. Sửa thông tin & Phân quyền
    public function suaTaiKhoan(Request $request, int $id)
    {
        try {
            $dataUpdate = [
                'VaiTro' => $request->VaiTro,
                'TrangThai' => $request->TrangThai
            ];

            if (!empty($request->MatKhau)) {
                $dataUpdate['MatKhau'] = Hash::make($request->MatKhau);
            }

            DB::table('taikhoan')->where('ID_TaiKhoan', $id)->update($dataUpdate);
            return response()->json(['success' => true, 'message' => 'Cập nhật tài khoản thành công!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
        }
    }

    // 4. Khóa/Mở khóa nhanh (Ban/Unban)
    public function khoaTaiKhoan(Request $request, int $id)
    {
        try {
            DB::table('taikhoan')->where('ID_TaiKhoan', $id)->update(['TrangThai' => $request->TrangThai]);
            $msg = $request->TrangThai == 'Khoa' ? 'Đã khóa tài khoản!' : 'Đã mở khóa tài khoản!';
            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
    }
}