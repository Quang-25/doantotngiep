<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        // 1. Chỉ số KPI
        $tongDoanhThu  = DB::table('donhang')->where('TrangThaiDon', 'HoanThanh')->sum('ThanhTien');
        $tongDonHang   = DB::table('donhang')->count();
        $donChoXacNhan = DB::table('donhang')->where('TrangThaiDon', 'ChoXacNhan')->count();
        $tongKhachHang = DB::table('khachhang')->count();

        // 2. Biểu đồ tròn: số đơn theo trạng thái
        $thongKeDonHang = DB::table('donhang')
            ->select('TrangThaiDon', DB::raw('count(*) as SoLuong'))
            ->groupBy('TrangThaiDon')
            ->pluck('SoLuong', 'TrangThaiDon')
            ->toArray();

        // Thứ tự phải khớp labels trong admin.js
        $chartData = [
            (int) ($thongKeDonHang['ChoXacNhan'] ?? 0),
            (int) ($thongKeDonHang['DangGiao'] ?? 0),
            (int) ($thongKeDonHang['HoanThanh'] ?? 0),
            (int) ($thongKeDonHang['DaHuy'] ?? 0),
        ];

        // 3. Cảnh báo tồn kho (< 5)
        $spHetHang = DB::table('sanpham')
            ->where('TrangThai', 'DangBan')
            ->where('SoLuongTon', '<', 5)
            ->orderBy('SoLuongTon', 'asc')
            ->limit(5)
            ->get();

        // 4. 5 đơn hàng mới nhất
        $donHangMoi = DB::table('donhang')
            ->orderBy('NgayDat', 'desc')
            ->limit(5)
            ->get();

        return view('admin.Dashbosh', compact(
        'tongDoanhThu', 'tongDonHang', 'donChoXacNhan', 'tongKhachHang',
        'donHangMoi', 'chartData', 'spHetHang'
));
    }
}