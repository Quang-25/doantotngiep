<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard - Admin Cầu Lông</title>

    <!-- Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS Admin (Đã thêm ?v={{ time() }} để trình duyệt KHÔNG BAO GIỜ bị lưu cache cũ) -->
    <link rel="stylesheet" href="{{ asset('css/admin/admin.css') }}?v={{ time() }}">
    
    <!-- Nơi nhúng CSS riêng -->
    @yield('css')

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4 class="text-center fw-bold mb-4">Badminton ProSHOP</h4>
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->is('admin/dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Tổng Quan</a>
        <a href="{{ url('/admin/san-pham') }}" class="{{ request()->is('admin/san-pham') ? 'active' : '' }}"><i class="fas fa-box"></i> Quản lý Sản phẩm</a>
        <a href="{{ url('/admin/khach-hang') }}" class="{{ request()->is('admin/khach-hang') ? 'active' : '' }}"><i class="fas fa-users"></i> Quản lý Khách hàng</a>
        <a href="{{ url('/admin/don-hang') }}" class="{{ request()->is('admin/don-hang') ? 'active' : '' }}"><i class="fas fa-shopping-cart"></i> Quản lý Đơn hàng</a>
        <a href="#"><i class="fas fa-tags"></i> Quản lý Khuyến mãi</a>
        <a href="#"><i class="fas fa-chart-bar"></i> Thống kê - Báo cáo</a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- TOPBAR -->
        <div class="topbar">
            <h5 class="m-0 fw-bold text-secondary">Bảng điều khiển &amp; Báo cáo hệ thống</h5>
            <div>
                <span class="me-3">Xin chào, <b>Quản Trị Viên</b></span>
                <button id="logoutButton" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </button>
            </div>
        </div>

        <!-- NƠI NHÚNG NỘI DUNG CÁC TRANG -->
        <div class="content-wrapper">
            @yield('content')
        </div>

    </div>

    <!-- JS Dùng Chung -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/Dangxuat.js') }}"></script>
    
    <!-- Nơi nhúng JS riêng -->
    @yield('scripts')
    
</body>
</html>