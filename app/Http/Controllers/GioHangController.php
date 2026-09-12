<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\SanPham;
use App\Models\GioHang;
use Illuminate\Support\Facades\DB;

class GioHangController extends Controller
{
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
                [
                    'ID_KhachHang' => $request->ID_KhachHang
                ],
                [
                    'MaPhien' => null
                ]
            );
        } elseif ($request->filled('MaPhien')) {
            $gioHang = GioHang::firstOrCreate(
                [
                    'MaPhien' => $request->MaPhien
                ],
                [
                    'ID_KhachHang' => null
                ]
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
        ->count('ID_SanPham');

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
}