<?php

namespace App\Services;

use App\Support\SizeSanPham;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;

/**
 * Tạo dữ liệu chi tiết đơn hàng và file PDF "Phiếu xác nhận đơn hàng" để gửi kèm email cho khách.
 */
class DonHangPdf
{
    /** Thông tin đơn dùng chung cho email và PDF; null nếu không có đơn. */
    public function layThongTin(int $idDonHang): ?array
    {
        $donHang = DB::table('donhang')->where('ID_DonHang', $idDonHang)->first();
        if (!$donHang) {
            return null;
        }

        $sizes = SizeSanPham::tuGhiChu($donHang->GhiChu ?? null);
        $sanPhams = DB::table('chitietdonhang')
            ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->where('chitietdonhang.ID_DonHang', $idDonHang)
            ->select('sanpham.TenSanPham', 'chitietdonhang.SoLuong', 'chitietdonhang.GiaMua')
            ->get()
            ->map(function ($sp) use ($sizes) {
                $sp->Size = $sizes[$sp->TenSanPham] ?? null;
                $sp->ThanhTien = (float) $sp->GiaMua * (int) $sp->SoLuong;

                return $sp;
            });

        $laVnPay = $donHang->PhuongThucThanhToan === 'VNPay';
        $daThanhToan = $laVnPay && DB::table('thanhtoan')
            ->where('ID_DonHang', $idDonHang)->where('TrangThaiGiaoDich', 'ThanhCong')->exists();

        return [
            'donHang' => $donHang,
            'sanPhams' => $sanPhams,
            'laVnPay' => $laVnPay,
            'daThanhToan' => $daThanhToan,
            'phuongThucText' => $laVnPay
                ? 'Thanh toán online qua VNPay' . ($daThanhToan ? ' (đã thanh toán)' : ' (chờ thanh toán)')
                : 'Thanh toán khi nhận hàng (COD)',
            'canThanhToan' => $daThanhToan ? 0.0 : (float) $donHang->ThanhTien,
            'ngayDat' => \Carbon\Carbon::parse($donHang->NgayDat)->format('H:i d/m/Y'),
        ];
    }

    /** Nội dung file PDF (binary). */
    public function taoPdf(int $idDonHang): ?string
    {
        $thongTin = $this->layThongTin($idDonHang);
        if ($thongTin === null) {
            return null;
        }

        $options = new Options();
        // DejaVu Sans có sẵn trong dompdf và hiển thị đầy đủ dấu tiếng Việt.
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('emails.donhang_pdf', $thongTin)->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function tenFile(int $idDonHang): string
    {
        return 'Don-hang-' . $idDonHang . '.pdf';
    }
}
