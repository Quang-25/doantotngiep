<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\SanPham;
use App\Models\GioHang;
use Illuminate\Support\Facades\DB;

class GioHangController extends Controller
{
    // =========================================================
    // HÀM 1: LẤY DỮ LIỆU GIỎ HÀNG TỪ DATABASE ĐỂ HIỂN THỊ
    // =========================================================
    public function getCart(Request $request): JsonResponse
    {
        // 1. Xác định giỏ hàng dựa trên ID Khách Hàng hoặc Mã Phiên
        $query = GioHang::query();
        if ($request->filled('ID_KhachHang')) {
            $query->where('ID_KhachHang', $request->ID_KhachHang);
        } elseif ($request->filled('MaPhien')) {
            $query->where('MaPhien', $request->MaPhien);
        } else {
            // Nếu không có thông tin định danh, trả về giỏ rỗng
            return response()->json(['success' => true, 'items' => [], 'totalAmount' => 0, 'totalQuantity' => 0]);
        }
        $gioHang = $query->first();

        if (!$gioHang) {
            return response()->json(['success' => true, 'items' => [], 'totalAmount' => 0, 'totalQuantity' => 0]);
        }

        // 2. Truy vấn chi tiết sản phẩm (JOIN 2 bảng)
        $items = DB::table('ChiTietGioHang')
            ->join('SanPham', 'ChiTietGioHang.ID_SanPham', '=', 'SanPham.ID_SanPham')
            ->where('ChiTietGioHang.ID_GioHang', $gioHang->ID_GioHang)
            ->select(
                'SanPham.ID_SanPham',
                'SanPham.TenSanPham',
                'SanPham.HinhAnh',
                'SanPham.GiaBan',
                'SanPham.GiaKhuyenMai',
                'ChiTietGioHang.SoLuong'
            )->get();

        // 3. Tính toán tổng tiền và format dữ liệu trả về cho Javascript
        $totalAmount = 0;
        $totalQuantity = 0;
        $formattedItems = [];

        foreach ($items as $item) {
            $giaThucTe = ($item->GiaKhuyenMai > 0) ? $item->GiaKhuyenMai : $item->GiaBan;
            $thanhTien = $giaThucTe * $item->SoLuong;

            $totalAmount += $thanhTien;
            $totalQuantity += $item->SoLuong;

            $formattedItems[] = [
                'ID_SanPham' => $item->ID_SanPham,
                'TenSanPham' => $item->TenSanPham,
                'HinhAnh'    => $item->HinhAnh,
                'Gia'        => $giaThucTe,
                'SoLuong'    => $item->SoLuong,
                'ThanhTien'  => $thanhTien
            ];
        }

        return response()->json([
            'success' => true,
            'items' => $formattedItems,
            'totalAmount' => $totalAmount,
            'totalQuantity' => $totalQuantity
        ]);
    }
    // =========================================================
    // HÀM 2: THÊM SẢN PHẨM VÀO DATABASE (Code gốc của bạn)
    // =========================================================
    public function them(Request $request): JsonResponse
    {
        $sanPham = SanPham::find($request->ID_SanPham);

        if (!$sanPham) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.'
            ], 404);
        }

        if ($sanPham->TrangThai !== 'DangBan') {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm ngừng kinh doanh.'
            ], 400);
        }
        $soLuong = (int) ($request->SoLuong ?? 1);
        if ($soLuong <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Số lượng không hợp lệ.'
            ], 400);
        }
        if ($request->filled('ID_KhachHang')) {
            $gioHang = GioHang::firstOrCreate(
                ['ID_KhachHang' => $request->ID_KhachHang],
                ['MaPhien' => null]
            );
        } elseif ($request->filled('MaPhien')) {
            $gioHang = GioHang::firstOrCreate(
                ['MaPhien' => $request->MaPhien],
                ['ID_KhachHang' => null]
            );
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Thiếu ID_KhachHang hoặc MaPhien.'
            ], 400);
        }

        $chiTiet = DB::table('ChiTietGioHang')
            ->where('ID_GioHang', $gioHang->ID_GioHang)
            ->where('ID_SanPham', $sanPham->ID_SanPham)
            ->first();

        $soLuongMoi = $chiTiet
            ? $chiTiet->SoLuong + $soLuong
            : $soLuong;

        if ($soLuongMoi > $sanPham->SoLuongTon) {
            return response()->json([
                'success' => false,
                'message' => 'Số lượng vượt quá tồn kho.'
            ], 400);
        }

        if ($chiTiet) {
            DB::table('ChiTietGioHang')
                ->where('ID_GioHang', $gioHang->ID_GioHang)
                ->where('ID_SanPham', $sanPham->ID_SanPham)
                ->update([
                    'SoLuong' => $soLuongMoi
                ]);
        } else {
            DB::table('ChiTietGioHang')->insert([
                'ID_GioHang' => $gioHang->ID_GioHang,
                'ID_SanPham' => $sanPham->ID_SanPham,
                'SoLuong' => $soLuong,
                'DaChon' => 1
            ]);
        }

        $tongSoLuong = DB::table('ChiTietGioHang')
            ->where('ID_GioHang', $gioHang->ID_GioHang)
            ->count('ID_SanPham'); // Đổi thành count để đếm "số loại sản phẩm"

        return response()->json([
            'success' => true,
            'message' => 'Thêm sản phẩm vào giỏ hàng thành công.',
            'data' => [
                'ID_GioHang' => $gioHang->ID_GioHang,
                'ID_SanPham' => $sanPham->ID_SanPham,
                'TenSanPham' => $sanPham->TenSanPham,
                'SoLuong' => $soLuongMoi,
                'TongSoLuong' => $tongSoLuong
            ]
        ]);
    }
    // =========================================================
    // HÀM 3: CẬP NHẬT SỐ LƯỢNG SẢN PHẨM (Cộng / Trừ)
    // =========================================================
    public function capNhat(Request $request): JsonResponse
    {
        $query = GioHang::query();
        if ($request->filled('ID_KhachHang')) $query->where('ID_KhachHang', $request->ID_KhachHang);
        elseif ($request->filled('MaPhien')) $query->where('MaPhien', $request->MaPhien);
        
        $gioHang = $query->first();

        if ($gioHang && $request->SoLuong > 0) {
            DB::table('ChiTietGioHang')
                ->where('ID_GioHang', $gioHang->ID_GioHang)
                ->where('ID_SanPham', $request->ID_SanPham)
                ->update(['SoLuong' => $request->SoLuong]);
                
            return response()->json(['success' => true, 'message' => 'Cập nhật thành công']);
        }
        return response()->json(['success' => false, 'message' => 'Không thể cập nhật']);
    }
    // =========================================================
    // HÀM 4: XÓA 1 SẢN PHẨM KHỎI GIỎ
    // =========================================================
    public function xoa(Request $request): JsonResponse
    {
        $query = GioHang::query();
        if ($request->filled('ID_KhachHang')) $query->where('ID_KhachHang', $request->ID_KhachHang);
        elseif ($request->filled('MaPhien')) $query->where('MaPhien', $request->MaPhien);
        
        $gioHang = $query->first();

        if ($gioHang) {
            DB::table('ChiTietGioHang')
                ->where('ID_GioHang', $gioHang->ID_GioHang)
                ->where('ID_SanPham', $request->ID_SanPham)
                ->delete();
                
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }
    // =========================================================
    // HÀM 5: XÓA TOÀN BỘ GIỎ HÀNG
    // =========================================================
    public function xoaTatCa(Request $request): JsonResponse
    {
        $query = GioHang::query();
        if ($request->filled('ID_KhachHang')) $query->where('ID_KhachHang', $request->ID_KhachHang);
        elseif ($request->filled('MaPhien')) $query->where('MaPhien', $request->MaPhien);
        $gioHang = $query->first();
        if ($gioHang) {
            DB::table('ChiTietGioHang')
                ->where('ID_GioHang', $gioHang->ID_GioHang)
                ->delete();
                
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false]);
    }
}