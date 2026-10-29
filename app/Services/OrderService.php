<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderService
{
    public function traCuuDonHang( string $idDonHang)
    {
        $donHang = DB::table('donhang')->where('ID_DonHang', $idDonHang)->first();
        
        if ($donHang) {
            $tien = number_format($donHang->ThanhTien, 0, ',', '.');
            return "📦 <b>Thông tin đơn hàng #{$idDonHang}:</b><br>"
                 . "- Trạng thái: <span class='text-primary fw-bold'>{$donHang->TrangThaiDon}</span><br>"
                 . "- Người nhận: {$donHang->TenNguoiNhan}<br>"
                 . "- Tổng tiền: {$tien} ₫";
        }
        
        return null; // Không tìm thấy
    }

    // Luồng trạng thái đơn: Chờ xác nhận -> (admin duyệt) Đang giao -> (admin xác nhận) Hoàn thành.
    // Hủy được khi đơn chưa hoàn thành.
    public const CHUYEN_TRANG_THAI_HOP_LE = [
        'ChoXacNhan' => ['DangGiao', 'DaHuy'],
        'DangGiao' => ['HoanThanh', 'DaHuy'],
        'HoanThanh' => [],
        'DaHuy' => [],
    ];

    public function coTheChuyenTrangThai(string $tuTrangThai, string $sangTrangThai): bool
    {
        return in_array($sangTrangThai, self::CHUYEN_TRANG_THAI_HOP_LE[$tuTrangThai] ?? [], true);
    }

    // ----- Trả hàng: khách đã nhận hàng (đơn Hoàn thành) rồi yêu cầu trả lại -----
    // Không thêm bảng/trạng thái mới: các bước được ghi thành từng dòng trong GhiChu của đơn.
    // Luồng: khách yêu cầu -> shop chấp nhận / từ chối -> shop nhận lại hàng (hoàn kho, đơn sang DaHuy) -> shop hoàn tiền.
    public const SO_NGAY_DUOC_TRA_HANG = 3;
    public const GHI_CHU_GIAO_THANH_CONG = 'Giao thành công lúc ';
    public const TRA_HANG_YEU_CAU = '[Trả hàng] Khách yêu cầu trả hàng';
    public const TRA_HANG_CHAP_NHAN = '[Trả hàng] Shop chấp nhận yêu cầu';
    public const TRA_HANG_TU_CHOI = '[Trả hàng] Shop từ chối yêu cầu';
    public const TRA_HANG_DA_NHAN = '[Trả hàng] Shop đã nhận lại hàng';
    public const TRA_HANG_DA_HOAN_TIEN = '[Trả hàng] Đã hoàn tiền cho khách';

    public const LY_DO_TRA_HANG = [
        'Sản phẩm bị lỗi / hư hỏng',
        'Giao sai mẫu, sai size hoặc sai màu',
        'Sản phẩm không giống mô tả',
        'Thiếu sản phẩm / phụ kiện đi kèm',
    ];

    public const TEN_TRANG_THAI_TRA_HANG = [
        'cho_duyet' => 'Chờ shop duyệt trả hàng',
        'tu_choi' => 'Shop từ chối trả hàng',
        'cho_nhan_hang' => 'Chờ gửi hàng trả về shop',
        'cho_hoan_tien' => 'Đã trả hàng - chờ hoàn tiền',
        'da_hoan_tien' => 'Đã trả hàng - đã hoàn tiền',
    ];

    // Bước trả hàng hiện tại của đơn (null nếu chưa có yêu cầu).
    public function trangThaiTraHang(object $donHang): ?string
    {
        $note = (string) ($donHang->GhiChu ?? '');
        if (!str_contains($note, self::TRA_HANG_YEU_CAU)) {
            return null;
        }

        return match (true) {
            str_contains($note, self::TRA_HANG_DA_HOAN_TIEN) => 'da_hoan_tien',
            str_contains($note, self::TRA_HANG_DA_NHAN) => 'cho_hoan_tien',
            str_contains($note, self::TRA_HANG_TU_CHOI) => 'tu_choi',
            str_contains($note, self::TRA_HANG_CHAP_NHAN) => 'cho_nhan_hang',
            default => 'cho_duyet',
        };
    }

    // Nội dung ghi kèm một bước (lý do khách / lý do từ chối), lấy sau "Lý do: ".
    public function ghiChuBuocTraHang(object $donHang, string $buoc): ?string
    {
        return preg_match('/^' . preg_quote($buoc, '/') . '.*?Lý do: (.+)$/mu', (string) $donHang->GhiChu, $matches)
            ? trim($matches[1])
            : null;
    }

    // Thời điểm giao thành công (ghi lúc admin bấm Hoàn thành); đơn cũ chưa có thì lấy ngày đặt.
    public function thoiDiemGiaoThanhCong(object $donHang): \Carbon\Carbon
    {
        if (preg_match('/' . preg_quote(self::GHI_CHU_GIAO_THANH_CONG, '/') . '(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2})/u', (string) $donHang->GhiChu, $matches)) {
            return \Carbon\Carbon::createFromFormat('d/m/Y H:i', $matches[1]);
        }

        return \Carbon\Carbon::parse($donHang->NgayDat);
    }

    public function hanTraHang(object $donHang): \Carbon\Carbon
    {
        return $this->thoiDiemGiaoThanhCong($donHang)->copy()->addDays(self::SO_NGAY_DUOC_TRA_HANG);
    }

    public function coTheYeuCauTraHang(object $donHang): bool
    {
        return $donHang->TrangThaiDon === 'HoanThanh'
            && $this->trangThaiTraHang($donHang) === null
            && now()->lessThanOrEqualTo($this->hanTraHang($donHang));
    }

    // Thêm một dòng vào ghi chú đơn.
    public function ghiThemGhiChu(int $idDonHang, string $dong): void
    {
        $ghiChu = DB::table('donhang')->where('ID_DonHang', $idDonHang)->value('GhiChu');
        DB::table('donhang')->where('ID_DonHang', $idDonHang)->update([
            'GhiChu' => trim(($ghiChu ? $ghiChu . "\n" : '') . $dong),
        ]);
    }

    // Shop nhận lại hàng trả: cộng lại kho, đơn chuyển sang DaHuy (không còn tính doanh thu).
    public function nhanLaiHangTra(int $idDonHang): bool
    {
        return DB::transaction(function () use ($idDonHang) {
            $donHang = DB::table('donhang')->where('ID_DonHang', $idDonHang)->lockForUpdate()->first();
            if (!$donHang || $donHang->TrangThaiDon !== 'HoanThanh' || $this->trangThaiTraHang($donHang) !== 'cho_nhan_hang') {
                return false;
            }
            foreach (DB::table('chitietdonhang')->where('ID_DonHang', $idDonHang)->get() as $item) {
                $sanPham = DB::table('sanpham')->where('ID_SanPham', $item->ID_SanPham)->first();
                $update = ['SoLuongTon' => DB::raw('SoLuongTon + ' . (int) $item->SoLuong)];
                if ($sanPham && $sanPham->SoLuongTon <= 0 && $sanPham->TrangThai === 'NgungKinhDoanh') {
                    $update['TrangThai'] = 'DangBan';
                }
                DB::table('sanpham')->where('ID_SanPham', $item->ID_SanPham)->update($update);
            }
            DB::table('donhang')->where('ID_DonHang', $idDonHang)->update(['TrangThaiDon' => 'DaHuy']);
            $this->ghiThemGhiChu($idDonHang, self::TRA_HANG_DA_NHAN . ' (' . now()->format('d/m/Y H:i') . ')');

            return true;
        });
    }

    // Đơn VNPay đã thanh toán thành công chưa.
    public function daThanhToanVNPay(int $idDonHang): bool
    {
        return DB::table('thanhtoan')
            ->where('ID_DonHang', $idDonHang)
            ->where('TrangThaiGiaoDich', 'ThanhCong')
            ->exists();
    }

    /**
     * Hủy đơn: hoàn lại kho (nếu kho đã bị trừ), trả lượt mã giảm giá, đánh dấu thanh toán chờ là thất bại,
     * ghi lý do vào GhiChu. Trả về false nếu đơn đã hoàn thành / đã hủy.
     */
    public function huyDon(int $idDonHang, string $ghiChu): bool
    {
        return DB::transaction(function () use ($idDonHang, $ghiChu) {
            // Khóa dòng đơn để tránh hủy trùng khi bấm nhiều lần.
            $donHang = DB::table('donhang')->where('ID_DonHang', $idDonHang)->lockForUpdate()->first();
            if (!$donHang || !$this->coTheChuyenTrangThai($donHang->TrangThaiDon, 'DaHuy')) {
                return false;
            }

            $thanhToan = DB::table('thanhtoan')->where('ID_DonHang', $idDonHang)->orderByDesc('ID_ThanhToan')->first();
            // Kho bị trừ lúc đặt với đơn COD, lúc thanh toán thành công với đơn VNPay.
            $daTruKho = $donHang->PhuongThucThanhToan !== 'VNPay' || ($thanhToan && $thanhToan->TrangThaiGiaoDich === 'ThanhCong');
            if ($daTruKho) {
                foreach (DB::table('chitietdonhang')->where('ID_DonHang', $idDonHang)->get() as $item) {
                    $sanPham = DB::table('sanpham')->where('ID_SanPham', $item->ID_SanPham)->first();
                    $update = ['SoLuongTon' => DB::raw('SoLuongTon + ' . (int) $item->SoLuong)];
                    // Hệ thống tự ngừng bán khi hết kho => có hàng lại thì mở bán lại.
                    if ($sanPham && $sanPham->SoLuongTon <= 0 && $sanPham->TrangThai === 'NgungKinhDoanh') {
                        $update['TrangThai'] = 'DangBan';
                    }
                    DB::table('sanpham')->where('ID_SanPham', $item->ID_SanPham)->update($update);
                }
            }

            if ($donHang->ID_KhuyenMai) {
                DB::table('khuyenmai')->where('ID_KhuyenMai', $donHang->ID_KhuyenMai)->whereNotNull('SoLuong')->increment('SoLuong');
            }
            if ($thanhToan && $thanhToan->TrangThaiGiaoDich === 'ChoThanhToan') {
                DB::table('thanhtoan')->where('ID_ThanhToan', $thanhToan->ID_ThanhToan)->update(['TrangThaiGiaoDich' => 'ThatBai']);
            }

            DB::table('donhang')->where('ID_DonHang', $idDonHang)->update([
                'TrangThaiDon' => 'DaHuy',
                'GhiChu' => trim(($donHang->GhiChu ? $donHang->GhiChu . "\n" : '') . $ghiChu),
            ]);

            return true;
        });
    }
}