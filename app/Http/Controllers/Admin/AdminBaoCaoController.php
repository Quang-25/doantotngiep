<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBaoCaoController extends Controller
{
    // 1. Giao diện Thống kê
    public function thongKeBaoCao(Request $request)
    {
        $baoCao = $this->layDuLieuBaoCao($request);
        $baoCao['topSanPham'] = $baoCao['sanPhams']->take(10);

        return view('admin.baocao', $baoCao);
    }

    // 2. Xuất Excel (.xlsx thật: có định dạng tiền, kẻ bảng, dòng tổng cộng)
    public function xuatExcel(Request $request)
    {
        $data = $this->layDuLieuBaoCao($request);
        $sanPhams = $data['sanPhams'];
        $tongDoanhThuSP = (float) $sanPhams->sum('DoanhThuSP');

        $xlsx = new XlsxWriter('Bao cao doanh thu');
        $xlsx->setColumnWidths([7, 10, 48, 16, 22, 14]);

        $xlsx->addRow([['v' => 'BÁO CÁO DOANH THU SẢN PHẨM', 's' => 'title']], 28);
        $xlsx->merge('A1:F1');
        $xlsx->addRow([['v' => 'Badminton Shop - Tổ 4, Đền Rồng, Phường Từ Sơn, Thành Phố Bắc Ninh', 's' => 'subtitle']]);
        $xlsx->merge('A2:F2');
        $xlsx->addRow([['v' => 'Từ ngày ' . $data['tuNgayHienThi'] . ' đến ngày ' . $data['denNgayHienThi'], 's' => 'subtitle']]);
        $xlsx->merge('A3:F3');
        $xlsx->addRow([]);

        // Tổng quan
        $tongQuan = [
            ['Số đơn hoàn thành', $data['tongDon'], 'total_int'],
            ['Tổng số sản phẩm bán ra', $data['tongSoLuong'], 'total_int'],
            ['Tổng tiền hàng (VNĐ)', $data['tongTienHang'], 'total_money'],
            ['Giảm giá từ mã khuyến mãi (VNĐ)', $data['tongGiamGia'], 'total_money'],
            ['Doanh thu thực nhận (VNĐ)', $data['tongDoanhThu'], 'total_money'],
        ];
        foreach ($tongQuan as [$label, $value, $style]) {
            $row = $xlsx->addRow([['v' => $label, 's' => 'label'], null, null, null, ['v' => (float) $value, 's' => $style]]);
            $xlsx->merge("A{$row}:D{$row}");
        }
        $xlsx->addRow([]);

        // Bảng sản phẩm
        $xlsx->addRow([
            ['v' => 'STT', 's' => 'header'], ['v' => 'Mã SP', 's' => 'header'], ['v' => 'Tên sản phẩm', 's' => 'header'],
            ['v' => 'Số lượng bán', 's' => 'header'], ['v' => 'Doanh thu (VNĐ)', 's' => 'header'], ['v' => 'Tỷ trọng', 's' => 'header'],
        ], 22);
        foreach ($sanPhams->values() as $i => $sp) {
            $xlsx->addRow([
                ['v' => $i + 1, 's' => 'int'],
                ['v' => '#' . $sp->ID_SanPham, 's' => 'int'],
                ['v' => $sp->TenSanPham, 's' => 'text'],
                ['v' => (int) $sp->TongSoLuongBan, 's' => 'int'],
                ['v' => (float) $sp->DoanhThuSP, 's' => 'money'],
                ['v' => $tongDoanhThuSP > 0 ? round($sp->DoanhThuSP / $tongDoanhThuSP * 100, 1) : 0, 's' => 'percent'],
            ]);
        }
        if ($sanPhams->isEmpty()) {
            $row = $xlsx->addRow([['v' => 'Không có sản phẩm nào được bán ra trong khoảng thời gian này.', 's' => 'text'], null, null, null, null, null]);
            $xlsx->merge("A{$row}:F{$row}");
        }
        $row = $xlsx->addRow([
            ['v' => 'TỔNG CỘNG', 's' => 'total_label'], ['v' => '', 's' => 'total_label'], ['v' => '', 's' => 'total_label'],
            ['v' => (int) $data['tongSoLuong'], 's' => 'total_int'],
            ['v' => $tongDoanhThuSP, 's' => 'total_money'],
            ['v' => $sanPhams->isEmpty() ? 0 : 100, 's' => 'total_percent'],
        ]);
        $xlsx->merge("A{$row}:C{$row}");

        $xlsx->addRow([]);
        $row = $xlsx->addRow([['v' => '* Doanh thu sản phẩm tính theo giá bán; doanh thu thực nhận đã trừ giảm giá từ mã khuyến mãi. Chỉ tính đơn đã hoàn thành.', 's' => 'note']]);
        $xlsx->merge("A{$row}:F{$row}");
        $row = $xlsx->addRow([['v' => 'Ngày xuất: ' . now()->format('H:i d/m/Y') . ' - Người xuất: ' . $data['nguoiLap'], 's' => 'note']]);
        $xlsx->merge("A{$row}:F{$row}");

        $path = tempnam(sys_get_temp_dir(), 'baocao_');
        $xlsx->save($path);
        $fileName = 'Bao_Cao_Doanh_Thu_' . $data['tuNgay'] . '_den_' . $data['denNgay'] . '.xlsx';

        return response()->download($path, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // 3. Phiếu thống kê để in
    public function inPhieu(Request $request)
    {
        $data = $this->layDuLieuBaoCao($request);
        $data['soPhieu'] = 'TK-' . now()->format('Ymd-His');
        $data['ngayLap'] = now();

        return view('admin.phieuthongke', $data);
    }

    // Dữ liệu dùng chung cho trang thống kê, file Excel và phiếu in.
    private function layDuLieuBaoCao(Request $request): array
    {
        $ngay = function (?string $value, Carbon $default): Carbon {
            try {
                return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::createFromFormat('Y-m-d', $value) : $default;
            } catch (\Throwable $e) {
                return $default;
            }
        };
        $tu = $ngay($request->input('tu_ngay'), Carbon::now()->startOfMonth());
        $den = $ngay($request->input('den_ngay'), Carbon::now());
        if ($tu->gt($den)) {
            [$tu, $den] = [$den, $tu];
        }
        $khoang = [$tu->format('Y-m-d') . ' 00:00:00', $den->format('Y-m-d') . ' 23:59:59'];

        $donHoanThanh = DB::table('donhang')
            ->where('TrangThaiDon', 'HoanThanh')
            ->whereBetween('NgayDat', $khoang);
        $tongQuan = (clone $donHoanThanh)
            ->selectRaw('COUNT(*) as SoDon, COALESCE(SUM(ThanhTien), 0) as DoanhThu, COALESCE(SUM(TongTien), 0) as TienHang, COALESCE(SUM(TienGiam), 0) as GiamGia')
            ->first();

        $sanPhams = DB::table('chitietdonhang')
            ->join('donhang', 'chitietdonhang.ID_DonHang', '=', 'donhang.ID_DonHang')
            ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->where('donhang.TrangThaiDon', 'HoanThanh')
            ->whereBetween('donhang.NgayDat', $khoang)
            ->select(
                'sanpham.ID_SanPham',
                'sanpham.TenSanPham',
                DB::raw('SUM(chitietdonhang.SoLuong) as TongSoLuongBan'),
                DB::raw('SUM(chitietdonhang.SoLuong * chitietdonhang.GiaMua) as DoanhThuSP')
            )
            ->groupBy('sanpham.ID_SanPham', 'sanpham.TenSanPham')
            ->orderByDesc('TongSoLuongBan')
            ->orderByDesc('DoanhThuSP')
            ->get();

        return [
            'tuNgay' => $tu->format('Y-m-d'),
            'denNgay' => $den->format('Y-m-d'),
            'tuNgayHienThi' => $tu->format('d/m/Y'),
            'denNgayHienThi' => $den->format('d/m/Y'),
            'tongDoanhThu' => (float) $tongQuan->DoanhThu,
            'tongDon' => (int) $tongQuan->SoDon,
            'tongTienHang' => (float) $tongQuan->TienHang,
            'tongGiamGia' => (float) $tongQuan->GiamGia,
            'tongSoLuong' => (int) $sanPhams->sum('TongSoLuongBan'),
            'sanPhams' => $sanPhams,
            'nguoiLap' => (string) (session('HoTen') ?: 'Quản trị viên'),
        ];
    }
}
