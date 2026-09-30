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
}