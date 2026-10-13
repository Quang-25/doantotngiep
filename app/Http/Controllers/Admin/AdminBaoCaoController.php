<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBaoCaoController extends Controller
{
    // 1. Hàm hiển thị giao diện Thống Kê
    public function thongKeBaoCao(Request $request)
    {
        // Mặc định lấy từ đầu tháng đến ngày hiện tại
        $tuNgay = $request->input('tu_ngay', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $denNgay = $request->input('den_ngay', Carbon::now()->format('Y-m-d'));

        // Tổng quan (Đã sửa thành TrangThaiDon)
        $tongDoanhThu = DB::table('donhang')
            ->where('TrangThaiDon', 'HoanThanh')
            ->whereBetween('NgayDat', [$tuNgay . ' 00:00:00', $denNgay . ' 23:59:59'])
            ->sum('ThanhTien');

        $tongDon = DB::table('donhang')
            ->where('TrangThaiDon', 'HoanThanh')
            ->whereBetween('NgayDat', [$tuNgay . ' 00:00:00', $denNgay . ' 23:59:59'])
            ->count();

        // Top sản phẩm bán chạy (Đã sửa thành TrangThaiDon và GiaMua)
        $topSanPham = DB::table('chitietdonhang')
            ->join('donhang', 'chitietdonhang.ID_DonHang', '=', 'donhang.ID_DonHang')
            ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->where('donhang.TrangThaiDon', 'HoanThanh')
            ->whereBetween('donhang.NgayDat', [$tuNgay . ' 00:00:00', $denNgay . ' 23:59:59'])
            ->select(
                'sanpham.ID_SanPham', 
                'sanpham.TenSanPham',
                DB::raw('SUM(chitietdonhang.SoLuong) as TongSoLuongBan'),
                DB::raw('SUM(chitietdonhang.SoLuong * chitietdonhang.GiaMua) as DoanhThuSP')
            )
            ->groupBy('sanpham.ID_SanPham', 'sanpham.TenSanPham')
            ->orderByDesc('TongSoLuongBan')
            ->limit(10)
            ->get();

        return view('admin.baocao', compact('tuNgay', 'denNgay', 'tongDoanhThu', 'tongDon', 'topSanPham'));
    }

    // 2. Hàm Xuất Excel trực tiếp
    public function xuatExcel(Request $request)
    {
        $tuNgay = $request->input('tu_ngay');
        $denNgay = $request->input('den_ngay');

        // (Đã sửa thành TrangThaiDon và GiaMua)
        $topSanPham = DB::table('chitietdonhang')
            ->join('donhang', 'chitietdonhang.ID_DonHang', '=', 'donhang.ID_DonHang')
            ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->where('donhang.TrangThaiDon', 'HoanThanh')
            ->whereBetween('donhang.NgayDat', [$tuNgay . ' 00:00:00', $denNgay . ' 23:59:59'])
            ->select(
                'sanpham.ID_SanPham', 
                'sanpham.TenSanPham',
                DB::raw('SUM(chitietdonhang.SoLuong) as TongSoLuongBan'),
                DB::raw('SUM(chitietdonhang.SoLuong * chitietdonhang.GiaMua) as DoanhThuSP')
            )
            ->groupBy('sanpham.ID_SanPham', 'sanpham.TenSanPham')
            ->orderByDesc('TongSoLuongBan')
            ->get();

        $fileName = 'Bao_Cao_Doanh_Thu_' . date('Ymd_His') . '.csv';

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($topSanPham, $tuNgay, $denNgay) {
            $file = fopen('php://output', 'w');
            
            // Chèn BOM để Excel đọc tiếng Việt UTF-8 không bị lỗi font
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); 

            // Viết dòng tiêu đề báo cáo
            fputcsv($file, ['BÁO CÁO DOANH THU SẢN PHẨM']);
            fputcsv($file, ['Từ ngày: ' . $tuNgay, 'Đến ngày: ' . $denNgay]);
            fputcsv($file, []); // Dòng trống

            // Viết tiêu đề cột
            fputcsv($file, ['Mã SP', 'Tên Sản Phẩm', 'Số Lượng Đã Bán', 'Doanh Thu Mang Lại (VNĐ)']);

            // Viết dữ liệu
            foreach ($topSanPham as $sp) {
                fputcsv($file, [
                    $sp->ID_SanPham,
                    $sp->TenSanPham,
                    $sp->TongSoLuongBan,
                    $sp->DoanhThuSP
                ]);
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, $headers);
    }
}