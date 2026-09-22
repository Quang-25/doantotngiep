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
        <p><strong>Số tiền thanh toán:</strong> {{ number_format($donHang->TongTien, 0, ',', '.') }} VNĐ</p>
        <p><strong>Địa chỉ nhận hàng:</strong> {{ $donHang->DiaChiGiaoHang }}</p>
        <p><strong>Phương thức:</strong> {{ $donHang->PhuongThucThanhToan }}</p>
    </div>

    <p>Sau khi nhận được hàng từ shipper, bạn vui lòng bấm vào nút bên dưới để xác nhận đã nhận hàng và tiến hành đánh giá sản phẩm nhé:</p>
    
    <a href="{{ url('/xac-nhan-hoan-thanh/' . $donHang->ID_DonHang) }}" style="display: inline-block; padding: 10px 20px; background-color: #0056b3; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">Đã nhận được hàng & Đánh giá</a>

    <p style="margin-top: 30px; font-size: 12px; color: #777;">Email này được gửi tự động, vui lòng không trả lời.</p>
</body>
</html>