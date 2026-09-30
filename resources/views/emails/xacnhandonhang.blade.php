<!DOCTYPE html>
<html>
<head>
    <title>Xác nhận đơn hàng</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Xin chào {{ $donHang->TenNguoiNhan }},</h2>
    <p>Cảm ơn bạn đã mua sắm tại <strong>Badminton ProShop</strong>. Hệ thống đã ghi nhận đơn hàng của bạn thành công!</p>
    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p><strong>Mã đơn hàng:</strong> #{{ $donHang->ID_DonHang }}</p>
        <!-- BẮT ĐẦU KIỂM TRA KHUYẾN MÃI -->
        @if($donHang->TienGiam > 0)
            <p><strong>Tổng tiền hàng:</strong> {{ number_format($donHang->TongTien, 0, ',', '.') }} VNĐ</p>
            <p><strong>Khuyến mãi:</strong> <span style="color: #28a745;">-{{ number_format($donHang->TienGiam, 0, ',', '.') }} VNĐ</span></p>
            <p><strong>Số tiền thanh toán:</strong> <strong style="color: red; font-size: 16px;">{{ number_format($donHang->ThanhTien, 0, ',', '.') }} VNĐ</strong></p>
        @else
            <p><strong>Số tiền thanh toán:</strong> <strong style="color: red; font-size: 16px;">{{ number_format($donHang->TongTien, 0, ',', '.') }} VNĐ</strong></p>
        @endif
        <!-- KẾT THÚC KIỂM TRA KHUYẾN MÃI -->
        <p><strong>Địa chỉ nhận hàng:</strong> {{ $donHang->DiaChiGiaoHang }}</p>
        <p><strong>Phương thức:</strong> {{ $donHang->PhuongThucThanhToan }}</p>
    </div>
    <p style="margin-top: 30px; font-size: 12px; color: #777;">Email này được gửi tự động, vui lòng không trả lời.</p>
</body>
</html>