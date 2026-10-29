<!DOCTYPE html>
<html>
<head>
    <title>Phản hồi từ khách hàng</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #1D4ED8;">Phản hồi mới từ trang Liên hệ</h2>
    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p style="margin: 4px 0;"><strong>Họ tên:</strong> {{ $lienHe['ho_ten'] }}</p>
        <p style="margin: 4px 0;"><strong>Email:</strong> {{ $lienHe['email'] }}</p>
        <p style="margin: 4px 0;"><strong>Số điện thoại:</strong> {{ $lienHe['so_dien_thoai'] ?: 'Không cung cấp' }}</p>
        <p style="margin: 4px 0;"><strong>Chủ đề:</strong> {{ $lienHe['chu_de'] }}</p>
        <p style="margin: 4px 0;"><strong>Thời gian:</strong> {{ now()->format('H:i d/m/Y') }}</p>
    </div>
    <p><strong>Nội dung:</strong></p>
    <div style="white-space: pre-line; border-left: 3px solid #1D4ED8; padding-left: 12px;">{{ $lienHe['noi_dung'] }}</div>
    <p style="margin-top: 30px; font-size: 12px; color: #777;">Bấm "Trả lời" để phản hồi trực tiếp cho khách qua email {{ $lienHe['email'] }}.</p>
</body>
</html>
