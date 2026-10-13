@extends('admin.layout')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin/admin.css') }}?v={{ time() }}">
@endsection

@section('content')
    <!-- KHỐI 1 & 2: 4 THẺ KPI TỔNG QUAN -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-revenue">
                <div>
                    <h3>{{ number_format($tongDoanhThu ?? 0, 0, ',', '.') }}đ</h3>
                    <span>Tổng Doanh Thu</span>
                </div>
                <i class="fas fa-wallet"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-orders">
                <div>
                    <h3>{{ $tongDonHang ?? 0 }}</h3>
                    <span>Tổng Đơn Hàng</span>
                </div>
                <i class="fas fa-shopping-bag"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-pending">
                <div>
                    <h3>{{ $donChoXacNhan ?? 0 }}</h3>
                    <span>Đơn Chờ Xác Nhận</span>
                </div>
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-customers">
                <div>
                    <h3>{{ $tongKhachHang ?? 0 }}</h3>
                    <span>Tổng Khách Hàng</span>
                </div>
                <i class="fas fa-user-friends"></i>
            </div>
        </div>
    </div>

    <!-- KHỐI 3: BIỂU ĐỒ & CẢNH BÁO TỒN KHO -->
    <div class="row g-3 mb-4">
        <!-- Biểu đồ -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="m-0 fw-bold text-primary"><i class="fas fa-chart-pie"></i> Tỉ Lệ Trạng Thái Đơn Hàng</h6>
                </div>
                <div class="card-body chart-box">
                    <canvas id="orderPieChart" data-values='@json($chartData ?? [])'></canvas>
                </div>
            </div>
        </div>

        <!-- Tình trạng tồn kho -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 card-warning">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-danger"><i class="fas fa-exclamation-triangle"></i> Tình Trạng Tồn Kho</h6>
                    <span class="badge bg-primary">Tất cả sản phẩm</span>
                </div>
                <div class="card-body p-0">
                    <!-- Danh sách có thanh cuộn dọc -->
                    <ul class="list-group list-group-flush" style="max-height: 380px; overflow-y: auto;">
                        @forelse($spHetHang ?? [] as $sp)
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <div class="d-flex align-items-center">
                                    
                                    <!-- HIỂN THỊ HÌNH ẢNH SẢN PHẨM -->
                                    <div class="me-3">
                                        @if(!empty($sp->HinhAnh))
                                            <img src="{{ $sp->HinhAnh }}" alt="{{ $sp->TenSanPham }}" class="rounded border" style="width: 50px; height: 50px; object-fit: cover;">
                                        @else
                                            <div class="d-flex align-items-center justify-content-center bg-light rounded border" style="width: 50px; height: 50px;">
                                                <i class="fas fa-box-open text-muted fs-5"></i>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- THÔNG TIN SẢN PHẨM & CẢNH BÁO -->
                                    <div>
                                        <h6 class="mb-0 text-truncate stock-name" title="{{ $sp->TenSanPham }}" style="max-width: 250px;">{{ $sp->TenSanPham }}</h6>
                                        
                                        @if($sp->SoLuongTon < 5)
                                            <small class="text-danger fw-bold">Chỉ còn: {{ $sp->SoLuongTon }} sản phẩm (Sắp hết)</small>
                                        @else
                                            <small class="text-success fw-bold">Trong kho còn: {{ $sp->SoLuongTon }} sản phẩm</small>
                                        @endif
                                    </div>

                                </div>
                                <a href="/admin/san-pham" class="btn btn-sm btn-outline-primary">Nhập hàng</a>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-success py-5">
                                <i class="fas fa-check-circle fs-3 mb-2"></i><br>
                                Cửa hàng hiện chưa có sản phẩm nào.
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- KHỐI 4: ĐƠN HÀNG MỚI -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary"><i class="fas fa-clipboard-list"></i> Theo Dõi Đơn Hàng Mới Nhất</h6>
            <a href="/admin/don-hang" class="text-decoration-none small">Xem tất cả &rarr;</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã Đơn</th>
                            <th>Khách Hàng</th>
                            <th>Ngày Đặt</th>
                            <th>Tổng Tiền</th>
                            <th>Trạng Thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($donHangMoi ?? [] as $don)
                            <tr>
                                <td class="fw-bold">#{{ $don->ID_DonHang }}</td>
                                <td>
                                    <b>{{ $don->TenNguoiNhan }}</b><br>
                                    <small class="text-muted">{{ $don->SoDienThoaiNhan }}</small>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($don->NgayDat)->format('d/m/Y H:i') }}</td>
                                <td class="fw-bold text-danger">{{ number_format($don->ThanhTien, 0, ',', '.') }}đ</td>
                                
                                <!-- HIỂN THỊ NHÃN TRẠNG THÁI -->
                                <td>
                                    @if($don->TrangThaiDon == 'ChoXacNhan')
                                        <span class="badge bg-warning text-dark py-2 px-3">Chờ xác nhận</span>
                                    @elseif($don->TrangThaiDon == 'DangGiao')
                                        <span class="badge bg-primary py-2 px-3">Đang giao</span>
                                    @elseif($don->TrangThaiDon == 'HoanThanh')
                                        <span class="badge bg-success py-2 px-3">Hoàn thành</span>
                                    @else
                                        <span class="badge bg-secondary py-2 px-3">Đã hủy</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Chưa có đơn hàng nào</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- JS xử lý biểu đồ và các sự kiện -->
    <script src="{{ asset('js/admin/admin.js') }}"></script>
    <script src="{{ asset('js/admin/donhang.js') }}"></script>
@endsection