<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Xác nhận đơn hàng</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Xin chào {{ $donHang->TenNguoiNhan }},</h2>
    <p>Cảm ơn bạn đã mua sắm tại <strong>Badminton ProShop</strong>. Hệ thống đã ghi nhận đơn hàng của bạn thành công!
        Phiếu đơn hàng chi tiết được <strong>đính kèm trong email này (file PDF)</strong>.</p>

    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin-top: 0;"><strong>Mã đơn hàng:</strong> #{{ $donHang->ID_DonHang }}</p>
        @if(!empty($chiTiet['ngayDat']))
            <p><strong>Ngày đặt:</strong> {{ $chiTiet['ngayDat'] }}</p>
        @endif
        <p><strong>Người nhận:</strong> {{ $donHang->TenNguoiNhan }} - {{ $donHang->SoDienThoaiNhan }}</p>
        <p><strong>Địa chỉ nhận hàng:</strong> {{ $donHang->DiaChiGiaoHang }}</p>
        <p style="margin-bottom: 0;"><strong>Phương thức thanh toán:</strong>
            {{ $chiTiet['phuongThucText'] ?? $donHang->PhuongThucThanhToan }}
        </p>
    </div>

    @if(!empty($chiTiet['sanPhams']) && count($chiTiet['sanPhams']) > 0)
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
            <tr style="background-color: #0d6efd; color: #fff;">
                <th style="text-align: left; padding: 8px;">Sản phẩm</th>
                <th style="text-align: center; padding: 8px;">SL</th>
                <th style="text-align: right; padding: 8px;">Thành tiền</th>
            </tr>
            @foreach($chiTiet['sanPhams'] as $sp)
                <tr>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd;">
                        {{ $sp->TenSanPham }}
                        @if($sp->Size)<br><span style="color: #0d6efd; font-size: 13px;">Size: {{ $sp->Size }}</span>@endif
                    </td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: center;">x{{ $sp->SoLuong }}</td>
                    <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">{{ number_format($sp->ThanhTien, 0, ',', '.') }} VNĐ</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px;">
        @if($donHang->TienGiam > 0)
            <p style="margin-top: 0;"><strong>Tổng tiền hàng:</strong> {{ number_format($donHang->TongTien, 0, ',', '.') }} VNĐ</p>
            <p><strong>Khuyến mãi:</strong> <span style="color: #28a745;">-{{ number_format($donHang->TienGiam, 0, ',', '.') }} VNĐ</span></p>
        @endif
        <p style="margin: 0;"><strong>Số tiền thanh toán:</strong>
            <strong style="color: red; font-size: 16px;">{{ number_format($donHang->ThanhTien, 0, ',', '.') }} VNĐ</strong></p>
    </div>

    @if(!empty($chiTiet['laVnPay']) && !empty($chiTiet['daThanhToan']))
        <p style="color: #28a745;"><strong>Bạn đã thanh toán thành công qua VNPay.</strong> Shop sẽ xác nhận và giao hàng sớm nhất.</p>
    @elseif(empty($chiTiet['laVnPay']))
        <p>Bạn vui lòng chuẩn bị <strong>{{ number_format($donHang->ThanhTien, 0, ',', '.') }} VNĐ</strong> để thanh toán khi nhận hàng.</p>
    @endif

    <p>Bạn có thể theo dõi trạng thái đơn tại mục <a href="{{ url('/don-hang') }}">Đơn mua</a> trên website.</p>
    <p style="margin-top: 30px; font-size: 12px; color: #777;">Email này được gửi tự động, vui lòng không trả lời.</p>
</body>
</html>
