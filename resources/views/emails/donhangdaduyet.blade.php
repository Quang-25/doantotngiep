<!DOCTYPE html>
<html>
<head>
    <title>Đơn hàng đã được duyệt</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Xin chào {{ $donHang->TenNguoiNhan }},</h2>
    <p>Đơn hàng <strong>#{{ $donHang->ID_DonHang }}</strong> của bạn tại <strong>Badminton ProShop</strong> đã được shop <strong>duyệt</strong> và đang được giao đến bạn.</p>

    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 0 0 8px;"><strong>Trạng thái:</strong> <span style="color: #0d6efd; font-weight: bold;">Đang giao</span></p>

        @if(count($sanPhams))
            <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                <tr style="background-color: #eee;">
                    <th style="text-align: left; padding: 6px;">Sản phẩm</th>
                    <th style="text-align: center; padding: 6px;">SL</th>
                    <th style="text-align: right; padding: 6px;">Giá</th>
                </tr>
                @foreach($sanPhams as $sp)
                    <tr>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd;">{{ $sp->TenSanPham }}</td>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd; text-align: center;">x{{ $sp->SoLuong }}</td>
                        <td style="padding: 6px; border-bottom: 1px solid #ddd; text-align: right;">{{ number_format($sp->GiaMua, 0, ',', '.') }} VNĐ</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if($donHang->TienGiam > 0)
            <p style="margin: 4px 0;"><strong>Khuyến mãi:</strong> <span style="color: #28a745;">-{{ number_format($donHang->TienGiam, 0, ',', '.') }} VNĐ</span></p>
        @endif
        <p style="margin: 4px 0;"><strong>Tổng thanh toán:</strong> <strong style="color: red; font-size: 16px;">{{ number_format($donHang->ThanhTien, 0, ',', '.') }} VNĐ</strong></p>
        <p style="margin: 4px 0;"><strong>Địa chỉ nhận hàng:</strong> {{ $donHang->DiaChiGiaoHang }}</p>
        <p style="margin: 4px 0;"><strong>Số điện thoại:</strong> {{ $donHang->SoDienThoaiNhan }}</p>
        <p style="margin: 4px 0;"><strong>Thanh toán:</strong>
            @if($daThanhToan)
                Đã thanh toán qua VNPay
            @else
                Thanh toán khi nhận hàng (COD) – vui lòng chuẩn bị {{ number_format($donHang->ThanhTien, 0, ',', '.') }} VNĐ
            @endif
        </p>
    </div>

    <p>Bạn có thể theo dõi đơn hàng tại mục <a href="{{ url('/don-hang') }}">Đơn mua</a> trên website.</p>
    <p style="margin-top: 30px; font-size: 12px; color: #777;">Email này được gửi tự động, vui lòng không trả lời.</p>
</body>
</html>
