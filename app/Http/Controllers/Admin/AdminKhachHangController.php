<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminKhachHangController extends Controller
{
    // 1. [READ] & SEARCH
    public function quanLyKhachHang(Request $request)
    {
        $search = $request->input('search');

        // SỬA: Dùng JOIN để nối bảng khachhang với taikhoan lấy Email và TrangThai
        $query = DB::table('khachhang')
            ->join('taikhoan', 'khachhang.ID_TaiKhoan', '=', 'taikhoan.ID_TaiKhoan')
            ->select(
                'khachhang.ID_KhachHang', 
                'khachhang.ID_TaiKhoan', 
                'khachhang.HoTen', 
                'khachhang.SoDienThoai', 
                'khachhang.DiaChi',
                'taikhoan.Email',
                'taikhoan.TrangThai'
            );

        if (!empty($search)) {
            // SỬA: Phải chỉ định rõ tên bảng trước tên cột khi dùng JOIN
            $query->where('khachhang.HoTen', 'LIKE', '%' . $search . '%')
                  ->orWhere('khachhang.SoDienThoai', 'LIKE', '%' . $search . '%')
                  ->orWhere('taikhoan.Email', 'LIKE', '%' . $search . '%'); // Tìm được cả bằng Email
        }

        $khachHangs = $query->orderBy('khachhang.ID_KhachHang', 'desc')->paginate(10);
        $khachHangs->appends(['search' => $search]);

        return view('admin.quanlykhachhang', compact('khachHangs', 'search'));
    }

    // 2. [UPDATE] - Sửa thông tin khách hàng
    public function suaKhachHang(Request $request, int $id)
    {
        try {
            $phone = ltrim($request->SoDienThoai, '0');

            // Cập nhật thông tin cơ bản bên bảng khachhang
            DB::table('khachhang')->where('ID_KhachHang', $id)->update([
                'HoTen' => $request->HoTen,
                'SoDienThoai' => $phone,
                'DiaChi' => $request->DiaChi
            ]);

            return response()->json(['success' => true, 'message' => 'Cập nhật thành công!'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    // 3. [DELETE] - Xóa vĩnh viễn (Hard Delete)
    public function xoaKhachHang(int $id)
    {
        try {
            // Bước 1: Tìm ra ID_TaiKhoan của khách hàng này
            $khachHang = DB::table('khachhang')->where('ID_KhachHang', $id)->first();
            
            if ($khachHang) {
                // Bước 2: Chỉ cần xóa ở bảng taikhoan. 
                // Do bạn cài khóa ngoại ON DELETE CASCADE, CSDL sẽ TỰ ĐỘNG xóa luôn dòng tương ứng bên bảng khachhang!
                DB::table('taikhoan')->where('ID_TaiKhoan', $khachHang->ID_TaiKhoan)->delete();
            }

            return response()->json(['success' => true, 'message' => 'Đã xóa vĩnh viễn tài khoản và khách hàng!'], 200);
        } catch (\Exception $e) {
            // Bắt lỗi khóa ngoại nếu khách hàng này đã từng đặt Đơn hàng hoặc có trong Giỏ hàng
            return response()->json(['success' => false, 'message' => 'Lỗi: Khách hàng này đang có đơn hàng hoặc giỏ hàng, không thể xóa!'], 500);
        }
    }
}