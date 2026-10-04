<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDonHangController extends Controller
{
    // 1. Xem danh sách và Tìm kiếm
    public function quanLyDonHang(Request $request)
    {
        $search = $request->input('search');

        $query = DB::table('donhang')->select('*');

        if (!empty($search)) {
            $query->where('ID_DonHang', 'LIKE', '%' . $search . '%')
                  ->orWhere('TenNguoiNhan', 'LIKE', '%' . $search . '%')
                  ->orWhere('SoDienThoaiNhan', 'LIKE', '%' . $search . '%');
        }

        $donHangs = $query->orderBy('ID_DonHang', 'desc')->paginate(10);
        $donHangs->appends(['search' => $search]);

        return view('admin.quanlydonhang', compact('donHangs', 'search'));
    }

    // 2. Lấy chi tiết đơn hàng (Dùng cho Modal)
    public function chiTietDonHang(int $id)
    {
        $donHang = DB::table('donhang')->where('ID_DonHang', $id)->first();
        
        if (!$donHang) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng!']);
        }

        // Lấy danh sách sản phẩm trong đơn hàng
        $chiTiet = DB::table('chitietdonhang')
            ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->where('chitietdonhang.ID_DonHang', $id)
            ->select('sanpham.TenSanPham', 'sanpham.HinhAnh', 'chitietdonhang.SoLuong', 'chitietdonhang.GiaMua')
            ->get();

        return response()->json([
            'success' => true, 
            'donHang' => $donHang, 
            'chiTiet' => $chiTiet
        ]);
    }

    // 3. Cập nhật trạng thái đơn hàng
    public function capNhatTrangThai(Request $request, int $id)
    {
        try {
            DB::table('donhang')->where('ID_DonHang', $id)->update([
                'TrangThaiDon' => $request->TrangThaiDon
            ]);

            return response()->json(['success' => true, 'message' => 'Cập nhật trạng thái thành công!'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }
}