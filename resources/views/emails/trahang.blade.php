<!DOCTYPE html>
<html>
<head>
    <title>Trả hàng đơn #{{ $donHang->ID_DonHang }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    @php $tien = number_format($donHang->ThanhTien, 0, ',', '.') . ' VNĐ'; @endphp

    @if($buoc === 'shop')
        <h2 style="color: #dc2626;">Khách trả hàng</h2>
        <p>Khách <strong>{{ $donHang->TenNguoiNhan }}</strong> ({{ $donHang->SoDienThoaiNhan }}) vừa trả hàng đơn <strong>#{{ $donHang->ID_DonHang }}</strong> ({{ $tien }}, {{ $donHang->PhuongThucThanhToan }}).</p>
        @if($ghiChu)
            <p><strong>Lý do:</strong> {{ $ghiChu }}</p>
        @endif
        <p>Yêu cầu đã được chấp nhận tự động. Khi nhận được hàng, vào <a href="{{ url('/admin/don-hang?trang_thai=TraHangChoNhan') }}">Quản lý đơn hàng</a> bấm <strong>Đã nhận hàng trả</strong>, sau đó hoàn tiền cho khách và bấm <strong>Đã hoàn tiền</strong>.</p>
    @else
        <h2>Xin chào {{ $donHang->TenNguoiNhan }},</h2>

        @if($buoc === 'yeu_cau')
            <p>Badminton ProShop đã <strong style="color:#16a34a;">ghi nhận trả hàng</strong> cho đơn <strong>#{{ $donHang->ID_DonHang }}</strong>.</p>
            @if($ghiChu)
                <p><strong>Lý do bạn gửi:</strong> {{ $ghiChu }}</p>
            @endif
            <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 12px 15px; border-radius: 5px;">
                Bạn vui lòng đóng gói sản phẩm (còn nguyên tem, phụ kiện đi kèm) và gửi về:<br>
                <strong>Badminton Shop - Tổ 4, Đền Rồng, Phường Từ Sơn, Thành Phố Bắc Ninh</strong> - ĐT: 035 214 3569.<br>
                Shop sẽ hoàn <strong>{{ $tien }}</strong> sau khi nhận được hàng
                @if($donHang->PhuongThucThanhToan === 'VNPay')
                    về tài khoản/thẻ bạn đã thanh toán VNPay.
                @else
                    bằng tiền mặt.
                @endif
            </div>
        @elseif($buoc === 'chap_nhan')
            <p>Yêu cầu trả hàng đơn <strong>#{{ $donHang->ID_DonHang }}</strong> đã được <strong style="color:#16a34a;">chấp nhận</strong>.</p>
            <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 12px 15px; border-radius: 5px;">
                Bạn vui lòng đóng gói sản phẩm (còn nguyên tem, phụ kiện đi kèm) và gửi về:<br>
                <strong>Badminton Shop - Tổ 4, Đền Rồng, Phường Từ Sơn, Thành Phố Bắc Ninh</strong> - ĐT: 035 214 3569.<br>
                Shop sẽ hoàn tiền sau khi nhận và kiểm tra hàng.
            </div>
        @elseif($buoc === 'tu_choi')
            <p>Rất tiếc, yêu cầu trả hàng đơn <strong>#{{ $donHang->ID_DonHang }}</strong> <strong style="color:#dc2626;">chưa được chấp nhận</strong>.</p>
            @if($ghiChu)
                <p><strong>Lý do:</strong> {{ $ghiChu }}</p>
            @endif
            <p>Nếu cần hỗ trợ thêm, bạn liên hệ hotline 035 214 3569 hoặc trang <a href="{{ url('/lien-he') }}">Liên hệ</a>.</p>
        @elseif($buoc === 'da_nhan')
            <p>Shop đã <strong>nhận lại hàng</strong> của đơn <strong>#{{ $donHang->ID_DonHang }}</strong>. Shop sẽ hoàn lại <strong>{{ $tien }}</strong> cho bạn trong thời gian sớm nhất.</p>
        @elseif($buoc === 'da_hoan_tien')
            <p>Shop đã <strong style="color:#16a34a;">hoàn tiền {{ $tien }}</strong> cho đơn <strong>#{{ $donHang->ID_DonHang }}</strong>. Cảm ơn bạn đã mua sắm tại Badminton ProShop!</p>
        @endif

        <p>Bạn có thể theo dõi tại mục <a href="{{ url('/don-hang') }}">Đơn mua</a> trên website.</p>
    @endif
    <p style="margin-top: 30px; font-size: 12px; color: #777;">Email này được gửi tự động, vui lòng không trả lời.</p>
</body>
</html>
