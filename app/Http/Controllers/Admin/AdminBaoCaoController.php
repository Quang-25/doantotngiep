<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
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
            ['Tổng giá trị các đơn (hoàn thành + hủy + trả hàng) (VNĐ)', $data['tongGiaTriDon'], 'total_money'],
            ['Trừ: giá trị đơn bị hủy (VNĐ)', -$data['giaTriHuy'], 'total_money'],
            ['Trừ: giá trị đơn khách trả hàng (VNĐ)', -$data['giaTriHoanTra'], 'total_money'],
            ['Doanh thu thực nhận (VNĐ)', $data['tongDoanhThu'], 'total_money'],
            ['Số đơn hoàn thành được tính doanh thu', $data['tongDon'], 'total_int'],
            ['Số đơn bị hủy', $data['soDonHuy'], 'total_int'],
            ['Số đơn khách trả hàng', $data['soDonHoanTra'], 'total_int'],
            ['Tổng số sản phẩm bán ra', $data['tongSoLuong'], 'total_int'],
            ['Tổng tiền hàng (VNĐ)', $data['tongTienHang'], 'total_money'],
            ['Giảm giá từ mã khuyến mãi (VNĐ)', $data['tongGiamGia'], 'total_money'],
            ['Tỷ lệ trả hàng', $data['tyLeHoanTra'], 'total_percent'],
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
        $row = $xlsx->addRow([['v' => '* Doanh thu sản phẩm tính theo giá bán; doanh thu thực nhận đã trừ giảm giá từ mã khuyến mãi. Doanh thu chỉ tính đơn đã hoàn thành, đã trừ đơn bị hủy và đơn khách trả hàng.', 's' => 'note']]);
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

        $orderService = app(OrderService::class);

        // Doanh thu chỉ tính đơn đã hoàn thành và KHÔNG bị hủy / không nằm trong quy trình trả hàng.
        $donTinhDoanhThu = $orderService->locDonTinhDoanhThu(DB::table('donhang'))->whereBetween('donhang.NgayDat', $khoang);
        $tongQuan = (clone $donTinhDoanhThu)
            ->selectRaw('COUNT(*) as SoDon, COALESCE(SUM(ThanhTien), 0) as DoanhThu, COALESCE(SUM(TongTien), 0) as TienHang, COALESCE(SUM(TienGiam), 0) as GiamGia')
            ->first();

        // Đơn khách trả hàng (từ lúc khách gửi yêu cầu) và đơn bị hủy: hai khoản bị trừ khỏi doanh thu.
        $hoanTra = $orderService->locDonTraHang(DB::table('donhang'))->whereBetween('donhang.NgayDat', $khoang)
            ->selectRaw('COUNT(*) as SoDon, COALESCE(SUM(ThanhTien), 0) as GiaTri')
            ->first();
        $biHuy = $orderService->locDonBiHuy(DB::table('donhang'))->whereBetween('donhang.NgayDat', $khoang)
            ->selectRaw('COUNT(*) as SoDon, COALESCE(SUM(ThanhTien), 0) as GiaTri')
            ->first();
        $soDonHoanTra = (int) $hoanTra->SoDon;

        // Hàng khách đã hoàn về shop: đơn trả hàng mà shop đã bấm "Đã nhận hàng trả" (hàng được nhập lại kho).
        $donHoanVe = DB::table('donhang')
            ->where('TrangThaiDon', 'DaHuy')
            ->where('GhiChu', 'like', '%' . OrderService::TRA_HANG_DA_NHAN . '%')
            ->whereBetween('NgayDat', $khoang);
        $hoanVe = (clone $donHoanVe)->selectRaw('COUNT(*) as SoDon, COALESCE(SUM(ThanhTien), 0) as GiaTri')->first();
        $soSanPhamHoanVe = (int) DB::table('chitietdonhang')
            ->whereIn('ID_DonHang', (clone $donHoanVe)->select('ID_DonHang'))
            ->sum('SoLuong');
        // Tỷ lệ trả hàng = đơn bị trả / (đơn đang hoàn thành + đơn bị trả) = trên tổng đơn đã giao thành công
        $daGiao = (int) $tongQuan->SoDon + $soDonHoanTra;

        $sanPhams = $orderService->locDonTinhDoanhThu(
            DB::table('chitietdonhang')
                ->join('donhang', 'chitietdonhang.ID_DonHang', '=', 'donhang.ID_DonHang')
                ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
        )
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
            'soDonHoanTra' => $soDonHoanTra,
            'giaTriHoanTra' => (float) $hoanTra->GiaTri,
            'soDonHoanVe' => (int) $hoanVe->SoDon,
            'giaTriHoanVe' => (float) $hoanVe->GiaTri,
            'soSanPhamHoanVe' => $soSanPhamHoanVe,
            'soDonHuy' => (int) $biHuy->SoDon,
            'giaTriHuy' => (float) $biHuy->GiaTri,
            // Tổng giá trị các đơn đã có kết quả (hoàn thành + hủy + trả hàng); trừ đơn hủy, đơn trả hàng ra là doanh thu thực.
            'tongGiaTriDon' => (float) $tongQuan->DoanhThu + (float) $biHuy->GiaTri + (float) $hoanTra->GiaTri,
            'tyLeHoanTra' => $daGiao > 0 ? round($soDonHoanTra / $daGiao * 100, 1) : 0.0,
            'sanPhams' => $sanPhams,
            'nguoiLap' => (string) (session('HoTen') ?: 'Quản trị viên'),
        ];
    }
}
