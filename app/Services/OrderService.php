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