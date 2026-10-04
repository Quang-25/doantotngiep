<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Bắt buộc phải có để gọi CSDL

class AdminSanPhamController extends Controller
{
    // ... (Giữ nguyên hàm dashboard của bạn nếu có)

    // ==========================================
    // 1. READ & SEARCH (Hiển thị & Tìm kiếm)
    // ==========================================
    public function quanLySanPham(Request $request)
    {
        $search = $request->input('search');

        // JOIN 3 bảng: sanpham, danhmuc, thuonghieu
        $query = DB::table('sanpham')
            ->leftJoin('danhmuc', 'sanpham.ID_DanhMuc', '=', 'danhmuc.ID_DanhMuc')
            ->leftJoin('thuonghieu', 'sanpham.ID_ThuongHieu', '=', 'thuonghieu.ID_ThuongHieu')
            ->select('sanpham.*', 'danhmuc.TenDanhMuc', 'thuonghieu.TenThuongHieu')
            ->where('sanpham.TrangThai', '!=', 'NgungKinhDoanh');
             

        if (!empty($search)) {
            $query->where('sanpham.TenSanPham', 'LIKE', '%' . $search . '%')
                  ->orWhere('sanpham.ID_SanPham', $search);
        }

        $sanPhams = $query->orderBy('sanpham.ID_SanPham', 'desc')->paginate(10);
        $sanPhams->appends(['search' => $search]); // Giữ từ khóa khi sang trang 2, 3...

        // Lấy dữ liệu cho Thẻ Select (Combobox) trong form Thêm/Sửa
        $danhMuc = DB::table('danhmuc')->get(); 
        $thuongHieu = DB::table('thuonghieu')->get();

        return view('admin.quanlysanpham', compact('sanPhams', 'danhMuc', 'thuongHieu', 'search'));
    }

    // ==========================================
    // 2. CREATE (API Thêm)
    // ==========================================
    public function themSanPham(Request $request)
    {
        try {
            DB::table('sanpham')->insert([
                'TenSanPham' => $request->TenSanPham,
                'ID_DanhMuc' => $request->ID_DanhMuc,
                'ID_ThuongHieu' => $request->ID_ThuongHieu,
                'GiaBan' => $request->GiaBan,
                'GiaKhuyenMai' => $request->GiaKhuyenMai ?: null,
                'SoLuongTon' => $request->SoLuongTon,
                'MoTa' => $request->MoTa,
                'HinhAnh' => $request->HinhAnh, // Lưu trực tiếp Link URL
                'TrangThai' => 'DangBan'
            ]);

            return response()->json(['success' => true, 'message' => 'Thêm sản phẩm thành công!'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 3. UPDATE (API Sửa)
    // ==========================================
    public function suaSanPham(Request $request, int $id)
    {
        try {
            DB::table('sanpham')->where('ID_SanPham', $id)->update([
                'TenSanPham' => $request->TenSanPham,
                'ID_DanhMuc' => $request->ID_DanhMuc,
                'ID_ThuongHieu' => $request->ID_ThuongHieu,
                'GiaBan' => $request->GiaBan,
                'GiaKhuyenMai' => $request->GiaKhuyenMai ?: null,
                'SoLuongTon' => $request->SoLuongTon,
                'MoTa' => $request->MoTa,
                'HinhAnh' => $request->HinhAnh // Cập nhật bằng Link URL mới
            ]);

            return response()->json(['success' => true, 'message' => 'Cập nhật thành công!'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 4. DELETE (API Xóa - Ngừng kinh doanh)
    // ==========================================
    public function xoaSanPham(int $id)
    {
        try {
            // Đổi trạng thái thay vì xóa cứng để bảo toàn hóa đơn cũ
            DB::table('sanpham')->where('ID_SanPham', $id)->update(['TrangThai' => 'NgungKinhDoanh']);
            return response()->json(['success' => true, 'message' => 'Đã ngừng kinh doanh sản phẩm!'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}