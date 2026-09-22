<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Giỏ hàng - Badminton Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/giohang.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
</head>
<body class="bg-light-gray">
    @include('layouts.header')
    <div class="container mt-4 mb-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="{{ url('/') }}" class="text-decoration-none text-muted">Trang chủ</a></li>
                <li class="breadcrumb-item active theme-text fw-medium" aria-current="page">Giỏ hàng</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-4 text-uppercase" style="font-size: 20px;">Giỏ hàng của bạn</h4>

        <div class="row">
            <!-- Cột trái: Vùng chứa danh sách sản phẩm (Dữ liệu động) -->
            <div class="col-lg-8 mb-4">
                <div class="card cart-card border-0">
                    <div class="card-body p-0" id="cart-items">
                        <!-- Trạng thái Loading chờ API trả dữ liệu -->
                        <div class="text-center p-5 text-muted">
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h6 class="fw-medium">Đang tải dữ liệu giỏ hàng...</h6>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Cột phải: Tóm tắt đơn hàng -->
            <div class="col-lg-4">
                <div class="card cart-card border-0 sticky-top" style="top: 20px; z-index: 1;">
                    <div class="card-body p-4 p-xl-5">
                        <h5 class="fw-bold border-bottom pb-3 mb-4 text-dark text-uppercase">Tóm tắt giỏ hàng</h5>
                        
                        <!-- Các con số đã được đưa về 0 để JS tính toán và ghi đè -->
                        <div class="d-flex justify-content-between align-items-center mb-4 pt-2">
                            <span class="text-muted fw-medium" style="font-size: 15px;">Tổng cộng (0 sản phẩm)</span>
                            <span class="theme-text fs-3 fw-bold" id="final-total">0 ₫</span>
                        </div>

                        <a href="{{ url('/thanhtoan') }}" class="btn btn-primary w-100 fw-semibold py-3" id="checkout-btn">
                            <i class="bi bi-credit-card-2-front me-1"></i>
                            Tiến hành thanh toán
                        </a>

                        <divclass="text-center mb-4">
                            <a href="{{ url('/sanpham') }}" class="text-decoration-none text-muted fw-medium continue-shopping">
                                <i class="bi bi-arrow-left me-1"></i> Tiếp tục mua hàng
                            </a>
                        </divclass=>
                        <div class="alert-note mt-2">
                            <i class="bi bi-info-circle-fill theme-text me-2 fs-5 float-start"></i>
                            <span style="display: block; margin-left: 30px;">Giá trên chưa bao gồm phí vận chuyển. Phí vận chuyển sẽ được tính khi xác nhận đơn.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('layouts.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- JavaScript gọi RESTful API -->
    <script src="{{ asset('js/giohang.js') }}"></script>
</body>
</html>