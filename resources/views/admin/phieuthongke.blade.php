<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phiếu thống kê {{ $soPhieu }} - Badminton Shop</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e5e7eb;
            font-family: "Times New Roman", Times, serif;
            font-size: 14px;
            color: #111;
        }
        .toolbar {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 14px;
            background: #1e3a8a;
        }
        .toolbar button {
            border: 0;
            border-radius: 6px;
            padding: 9px 20px;
            font-size: 14px;
            font-family: Arial, sans-serif;
            cursor: pointer;
        }
        .btn-print { background: #fbbf24; color: #111; font-weight: bold; }
        .btn-close { background: #fff; color: #111; }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 16mm 15mm;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .15);
        }
        .head {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            font-size: 13px;
        }
        .head .shop b { font-size: 15px; text-transform: uppercase; }
        .head .meta { text-align: right; }
        h1 {
            text-align: center;
            font-size: 22px;
            margin: 22px 0 4px;
            text-transform: uppercase;
        }
        .period { text-align: center; font-style: italic; margin-bottom: 18px; }

        h2 { font-size: 15px; margin: 18px 0 8px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 6px 8px; vertical-align: top; }
        th { background: #f1f5f9; text-align: center; }
        .summary td:first-child { width: 60%; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        tfoot td { font-weight: bold; background: #f8fafc; }
        .note { font-size: 12px; font-style: italic; margin-top: 8px; }

        .signs {
            display: flex;
            justify-content: space-between;
            margin-top: 34px;
            text-align: center;
        }
        .signs > div { width: 48%; }
        .signs .date { font-style: italic; margin-bottom: 4px; white-space: nowrap; }
        .signs b { display: block; text-transform: uppercase; }
        .signs i { font-size: 12px; }
        .signs .name { margin-top: 70px; font-weight: bold; }

        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
        @media screen and (max-width: 820px) {
            .sheet { width: auto; margin: 12px; padding: 16px; min-height: auto; }
            .head { flex-direction: column; }
            .head .meta { text-align: left; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">🖨 In phiếu</button>
        <button type="button" class="btn-close" onclick="window.close()">Đóng</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="shop">
                <b>Badminton Shop</b><br>
                Địa chỉ: Tổ 4, Đền Rồng, Phường Từ Sơn, Thành Phố Bắc Ninh<br>
                Điện thoại: 035 214 3569 - Email: Cohoi2512@gmail.com
            </div>
            <div class="meta">
                Số phiếu: <b>{{ $soPhieu }}</b><br>
                Ngày lập: {{ $ngayLap->format('H:i d/m/Y') }}
            </div>
        </div>

        <h1>Phiếu thống kê doanh thu</h1>
        <div class="period">Từ ngày {{ $tuNgayHienThi }} đến ngày {{ $denNgayHienThi }}</div>

        <h2>I. Tổng quan</h2>
        <table class="summary">
            <tr><td>Số đơn hàng hoàn thành</td><td class="num">{{ number_format($tongDon, 0, ',', '.') }} đơn</td></tr>
            <tr><td>Tổng số sản phẩm bán ra</td><td class="num">{{ number_format($tongSoLuong, 0, ',', '.') }} sản phẩm</td></tr>
            <tr><td>Tổng tiền hàng</td><td class="num">{{ number_format($tongTienHang, 0, ',', '.') }} đ</td></tr>
            <tr><td>Giảm giá từ mã khuyến mãi</td><td class="num">- {{ number_format($tongGiamGia, 0, ',', '.') }} đ</td></tr>
            <tr><td><b>Doanh thu thực nhận</b></td><td class="num"><b>{{ number_format($tongDoanhThu, 0, ',', '.') }} đ</b></td></tr>
            <tr><td>Số đơn khách trả hàng</td><td class="num">{{ number_format($soDonHoanTra, 0, ',', '.') }} đơn</td></tr>
            <tr><td>Giá trị hàng bị trả</td><td class="num">{{ number_format($giaTriHoanTra, 0, ',', '.') }} đ</td></tr>
            <tr><td>Tỷ lệ trả hàng (trên số đơn đã giao thành công)</td><td class="num">{{ number_format($tyLeHoanTra, 1, ',', '.') }}%</td></tr>
        </table>

        <h2>II. Chi tiết sản phẩm bán ra</h2>
        @php $tongDoanhThuSP = (float) $sanPhams->sum('DoanhThuSP'); @endphp
        <table>
            <thead>
                <tr>
                    <th style="width: 6%">STT</th>
                    <th style="width: 10%">Mã SP</th>
                    <th>Tên sản phẩm</th>
                    <th style="width: 12%">SL bán</th>
                    <th style="width: 20%">Doanh thu (đ)</th>
                    <th style="width: 11%">Tỷ trọng</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sanPhams as $i => $sp)
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td class="center">#{{ $sp->ID_SanPham }}</td>
                        <td>{{ $sp->TenSanPham }}</td>
                        <td class="center">{{ number_format($sp->TongSoLuongBan, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($sp->DoanhThuSP, 0, ',', '.') }}</td>
                        <td class="num">{{ $tongDoanhThuSP > 0 ? number_format($sp->DoanhThuSP / $tongDoanhThuSP * 100, 1, ',', '.') : 0 }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="center">Không có sản phẩm nào được bán ra trong khoảng thời gian này.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="center">TỔNG CỘNG</td>
                    <td class="center">{{ number_format($tongSoLuong, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($tongDoanhThuSP, 0, ',', '.') }}</td>
                    <td class="num">{{ $sanPhams->isEmpty() ? 0 : 100 }}%</td>
                </tr>
            </tfoot>
        </table>
        <p class="note">* Chỉ tính đơn đã hoàn thành. Doanh thu sản phẩm tính theo giá bán; doanh thu thực nhận đã trừ giảm giá từ mã khuyến mãi.</p>

        <div class="signs">
            <div>
                <div class="date">&nbsp;</div>
                <b>Người lập phiếu</b>
                <i>(Ký, ghi rõ họ tên)</i>
                <div class="name">{{ $nguoiLap }}</div>
            </div>
            <div>
                <div class="date">Bắc Ninh, ngày {{ $ngayLap->format('d') }} tháng {{ $ngayLap->format('m') }} năm {{ $ngayLap->format('Y') }}</div>
                <b>Quản lý cửa hàng</b>
                <i>(Ký, ghi rõ họ tên)</i>
                <div class="name">&nbsp;</div>
            </div>
        </div>
    </div>

    <script>
        // Mở phiếu là hiện hộp thoại in luôn
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
</body>
</html>
