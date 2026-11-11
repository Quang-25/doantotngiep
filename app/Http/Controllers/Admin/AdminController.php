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
        // Doanh thu: đơn hoàn thành, đã trừ đơn hủy và đơn khách trả hàng.
        $tongDoanhThu  = app(\App\Services\OrderService::class)->locDonTinhDoanhThu(DB::table('donhang'))->sum('donhang.ThanhTien');
        $tongDonHang   = DB::table('donhang')->count();
        $donChoXacNhan = DB::table('donhang')->where('TrangThaiDon', 'ChoXacNhan')->count();
        $tongKhachHang = DB::table('khachhang')->count();

        // 2. Biểu đồ tròn: số đơn theo trạng thái
        $thongKeDonHang = DB::table('donhang')
            ->select('TrangThaiDon', DB::raw('count(*) as SoLuong'))
            ->groupBy('TrangThaiDon')
            ->pluck('SoLuong', 'TrangThaiDon')
            ->toArray();

        // Cùng cách phân loại với trang Báo cáo thống kê: đơn hoàn thành được tính doanh thu, đơn bị hủy, đơn khách trả hàng.
        // (Đơn trả hàng đã nhận lại có trạng thái "Đã hủy" trong database nhưng được tách riêng để không lẫn với đơn hủy.)
        $orderService = app(\App\Services\OrderService::class);
        // Thứ tự phải khớp labels trong admin.js
        $chartData = [
            (int) ($thongKeDonHang['ChoXacNhan'] ?? 0),
            (int) ($thongKeDonHang['DangGiao'] ?? 0),
            $orderService->locDonTinhDoanhThu(DB::table('donhang'))->count(),
            $orderService->locDonBiHuy(DB::table('donhang'))->count(),
            $orderService->locDonTraHang(DB::table('donhang'))->count(),
        ];

        // 3. Cảnh báo tồn kho (< 5)
       $spHetHang = DB::table('sanpham')
            ->where('TrangThai', 'DangBan')
            ->orderBy('SoLuongTon', 'asc') // Sắp xếp tăng dần theo số lượng
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