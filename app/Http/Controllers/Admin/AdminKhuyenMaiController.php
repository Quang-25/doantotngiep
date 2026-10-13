<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminKhuyenMaiController extends Controller
{
    public function quanLyKhuyenMai(Request $request)
    {
        $search = $request->input('search');
        $query = DB::table('khuyenmai');

        if (!empty($search)) {
            $query->where('MaCode', 'LIKE', '%' . $search . '%')
                  ->orWhere('MoTa', 'LIKE', '%' . $search . '%');
        }

        $khuyenMais = $query->orderBy('ID_KhuyenMai', 'desc')->paginate(10);
        return view('admin.quanlykhuyenmai', compact('khuyenMais', 'search'));
    }

    public function themKhuyenMai(Request $request)
    {
        try {
            if (DB::table('khuyenmai')->where('MaCode', $request->MaCode)->exists()) {
                return response()->json(['success' => false, 'message' => 'Mã Code này đã tồn tại!']);
            }

            DB::table('khuyenmai')->insert([
                'MaCode' => strtoupper($request->MaCode),
                'MoTa' => $request->MoTa,
                'LoaiGiam' => $request->LoaiGiam,
                'GiaTriGiam' => $request->GiaTriGiam,
                'GiaTriDonToiThieu' => $request->GiaTriDonToiThieu,
                'NgayBatDau' => $request->NgayBatDau,
                'NgayKetThuc' => $request->NgayKetThuc,
                'SoLuong' => $request->SoLuong,
                'TrangThai' => $request->TrangThai
            ]);
            return response()->json(['success' => true, 'message' => 'Thêm mã khuyến mãi thành công!']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]); }
    }

    public function suaKhuyenMai(Request $request, int $id)
    {
        try {
            DB::table('khuyenmai')->where('ID_KhuyenMai', $id)->update([
                'MoTa' => $request->MoTa,
                'LoaiGiam' => $request->LoaiGiam,
                'GiaTriGiam' => $request->GiaTriGiam,
                'GiaTriDonToiThieu' => $request->GiaTriDonToiThieu,
                'NgayBatDau' => $request->NgayBatDau,
                'NgayKetThuc' => $request->NgayKetThuc,
                'SoLuong' => $request->SoLuong,
                'TrangThai' => $request->TrangThai
            ]);
            return response()->json(['success' => true, 'message' => 'Cập nhật thành công!']);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]); }
    }
    
}