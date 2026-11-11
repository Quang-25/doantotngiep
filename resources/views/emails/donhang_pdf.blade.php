<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đơn hàng #{{ $donHang->ID_DonHang }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
        .dau { border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 14px; }
        .dau .shop { font-size: 17px; font-weight: bold; color: #0d6efd; }
        .dau .diachi { color: #555; font-size: 10px; margin-top: 3px; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; text-transform: uppercase; }
        .ma { text-align: center; color: #555; margin-bottom: 14px; }
        .ma b { color: #dc3545; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        .thongtin td { padding: 4px 0; vertical-align: top; }
        .thongtin td.nhan { width: 120px; color: #555; }
        .sp th { background: #0d6efd; color: #fff; padding: 7px 6px; text-align: left; font-size: 10.5px; }
        .sp td { border-bottom: 1px solid #ddd; padding: 7px 6px; }
        .sp .so { text-align: right; white-space: nowrap; }
        .sp .giua { text-align: center; }
        .tong { margin-top: 12px; width: 55%; margin-left: 45%; }
        .tong td { padding: 4px 6px; }
        .tong .so { text-align: right; }
        .tong .cuoi td { border-top: 2px solid #222; font-size: 13px; font-weight: bold; color: #dc3545; padding-top: 7px; }
        .luuy { margin-top: 18px; padding: 9px 11px; background: #f1f6ff; border-left: 4px solid #0d6efd; color: #333; font-size: 10.5px; }
        .chan { margin-top: 22px; text-align: center; color: #777; font-size: 10px; }
    </style>
</head>
<body>
    <div class="dau">
        <div class="shop">BADMINTON PROSHOP</div>
        <div class="diachi">Tổ 4, Đền Rồng, Phường Từ Sơn, Thành Phố Bắc Ninh · Hotline: 035 214 3569</div>
    </div>

    <h1>Phiếu xác nhận đơn hàng</h1>
    <div class="ma">Mã đơn hàng: <b>#{{ $donHang->ID_DonHang }}</b> · Ngày đặt: {{ $ngayDat }}</div>

    <table class="thongtin">
        <tr><td class="nhan">Người nhận</td><td><b>{{ $donHang->TenNguoiNhan }}</b></td></tr>
        <tr><td class="nhan">Số điện thoại</td><td>{{ $donHang->SoDienThoaiNhan }}</td></tr>
        <tr><td class="nhan">Địa chỉ giao hàng</td><td>{{ $donHang->DiaChiGiaoHang }}</td></tr>
        <tr><td class="nhan">Thanh toán</td><td>{{ $phuongThucText }}</td></tr>
    </table>

    <table class="sp" style="margin-top: 14px;">
        <thead>
            <tr>
                <th style="width: 28px;">STT</th>
                <th>Sản phẩm</th>
                <th style="width: 38px;" class="giua">SL</th>
                <th style="width: 82px; text-align:right;">Đơn giá</th>
                <th style="width: 88px; text-align:right;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sanPhams as $i => $sp)
                <tr>
                    <td class="giua">{{ $i + 1 }}</td>
                    <td>{{ $sp->TenSanPham }}@if($sp->Size)<br><span style="color:#0d6efd;">Size: {{ $sp->Size }}</span>@endif</td>
                    <td class="giua">{{ $sp->SoLuong }}</td>
                    <td class="so">{{ number_format($sp->GiaMua, 0, ',', '.') }}đ</td>
                    <td class="so">{{ number_format($sp->ThanhTien, 0, ',', '.') }}đ</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tong">
        <tr><td>Tổng tiền hàng</td><td class="so">{{ number_format($donHang->TongTien, 0, ',', '.') }}đ</td></tr>
        @if($donHang->TienGiam > 0)
            <tr><td>Giảm giá (mã khuyến mãi)</td><td class="so">- {{ number_format($donHang->TienGiam, 0, ',', '.') }}đ</td></tr>
        @endif
        <tr class="cuoi"><td>Tổng thanh toán</td><td class="so">{{ number_format($donHang->ThanhTien, 0, ',', '.') }}đ</td></tr>
    </table>

    <div class="luuy">
        @if($laVnPay && $daThanhToan)
            <b>Đã thanh toán online qua VNPay.</b> Shop sẽ xác nhận và giao hàng cho bạn sớm nhất.
        @elseif($laVnPay)
            Đơn hàng đang chờ thanh toán qua VNPay.
        @else
            <b>Thanh toán khi nhận hàng (COD):</b> bạn vui lòng chuẩn bị {{ number_format($canThanhToan, 0, ',', '.') }}đ khi nhận hàng.
        @endif
        <br>Bạn có thể theo dõi trạng thái đơn tại mục <b>Đơn mua</b> trên website. Cần hỗ trợ, vui lòng gọi hotline 035 214 3569.
    </div>

    <div class="chan">Cảm ơn bạn đã mua sắm tại Badminton ProShop!</div>
</body>
</html>
