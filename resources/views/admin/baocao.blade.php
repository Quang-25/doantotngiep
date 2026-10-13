@extends('admin.layout')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold m-0">Báo Cáo & Thống Kê</h3>
        
        <!-- NÚT XUẤT EXCEL -->
        <a href="/admin/bao-cao/xuat-excel?tu_ngay={{ $tuNgay }}&den_ngay={{ $denNgay }}" class="btn btn-success fw-bold">
            <i class="fas fa-file-excel"></i> Xuất Báo Cáo Excel
        </a>
    </div>

    <!-- BỘ LỌC NGÀY THÁNG -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body">
            <form action="/admin/bao-cao" method="GET" class="d-flex align-items-end gap-3">
                <div>
                    <label class="fw-bold mb-1 text-muted">Từ ngày:</label>
                    <input type="date" name="tu_ngay" class="form-control fw-bold" value="{{ $tuNgay }}" required>
                </div>
                <div>
                    <label class="fw-bold mb-1 text-muted">Đến ngày:</label>
                    <input type="date" name="den_ngay" class="form-control fw-bold" value="{{ $denNgay }}" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-filter"></i> Lọc Dữ Liệu</button>
                    <a href="/admin/bao-cao" class="btn btn-outline-secondary ms-1"><i class="fas fa-sync"></i> Tháng này</a>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI TỔNG QUAN -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card bg-primary text-white shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2">Tổng Doanh Thu</h6>
                        <h2 class="fw-bold m-0">{{ number_format($tongDoanhThu, 0, ',', '.') }} đ</h2>
                    </div>
                    <i class="fas fa-hand-holding-usd fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-info text-white shadow-sm border-0 h-100">
                <div class="card-body p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2">Số Đơn Hoàn Thành</h6>
                        <h2 class="fw-bold m-0">{{ $tongDon }} đơn</h2>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- BẢNG SẢN PHẨM BÁN CHẠY -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-fire text-danger"></i> Top Sản Phẩm Bán Chạy (Theo khoảng thời gian lọc)</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã SP</th>
                        <th>Tên Sản Phẩm</th>
                        <th class="text-center">Số Lượng Đã Bán</th>
                        <th class="text-end">Doanh Thu Mang Lại</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topSanPham as $sp)
                    <tr>
                        <td class="fw-bold">#{{ $sp->ID_SanPham }}</td>
                        <td class="fw-bold text-primary">{{ $sp->TenSanPham }}</td>
                        <td class="text-center">
                            <span class="badge bg-success fs-6">{{ $sp->TongSoLuongBan }}</span>
                        </td>
                        <td class="text-end fw-bold text-danger">
                            {{ number_format($sp->DoanhThuSP, 0, ',', '.') }}đ
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="fas fa-box-open fs-1 mb-3 opacity-50"></i><br>
                            Không có sản phẩm nào được bán ra trong thời gian này.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection