<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Badminton Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
</head>
<body>

    <div class="top-bar">
        <div class="container">
            <div class="row">
                <div class="col-lg-4">
                    <i class="bi bi-truck"></i>
                    Miễn phí vận chuyển từ 500.000đ
                </div>

                <div class="col-lg-4 text-center">
                    <i class="bi bi-patch-check"></i>
                    Chính hãng 100%
                </div>

                <div class="col-lg-4 text-end">
                    <i class="bi bi-telephone"></i>
                    Hotline: 0352143569
                </div>
            </div>
        </div>
    </div>

    <header class="header">
        <div class="container">
            <div class="row align-items-center">

                <div class="col-lg-3">
                    <div class="logo">
                        <a href="{{ url('/') }}">
                            <img src="{{ asset('images/logo.png') }}" width="100" height="100" alt="Logo">
                        </a>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Tìm kiếm sản phẩm...">

                        <button type="button" id="searchButton">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="header-right">

                        <div class="account-menu">
                            <a href="#" class="account-main">
                                <i class="bi bi-person"></i>
                                <span id="accountName">Tài khoản</span>
                            </a>

                            <div class="account-dropdown">
                                <a href="{{ url('/Dangky') }}" id="registerLink">Đăng ký</a>
                                <a href="{{ url('/Dangnhap') }}" id="loginLink">Đăng nhập</a>
                                <a href="#" id="logoutButton" style="display: none;">Đăng xuất</a>
                            </div>
                        </div>

                        <a href="#">
                            <i class="bi bi-heart"></i>
                            <span>Yêu thích</span>
                        </a>

                        <a href="{{ url('/giohang') }}" class="cart-link">
                            <i class="bi bi-cart3"></i>
                            <span>Giỏ hàng</span>
                            <span class="cart-badge" id="cart-count">0</span>
                        </a>

                    </div>
                </div>

            </div>
        </div>
    </header>

    <nav class="navbar navbar-expand-lg menu">
        <div class="container">

            <button class="navbar-toggler bg-light" type="button"
                data-bs-toggle="collapse" data-bs-target="#menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav mx-auto">

                    <li class="nav-item">
                        <a class="nav-link active" href="{{ url('/') }}">Trang chủ</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/sanpham') }}">Sản phẩm</a>
                    </li>

                    <!-- MENU VỢT CẦU LÔNG (Danh mục 1) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="{{ url('/sanpham?danh_muc=1') }}" role="button" data-bs-toggle="dropdown">
                            Vợt cầu lông
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=1') }}">Tất cả vợt cầu lông</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=1&thuong_hieu=1') }}">Vợt Yonex</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=1&thuong_hieu=2') }}">Vợt Lining</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=1&thuong_hieu=3') }}">Vợt Victor</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=1&thuong_hieu=4') }}">Vợt Mizuno</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=1&thuong_hieu=5') }}">Vợt Gosen</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=1&trinh_do=MoiChoi') }}">Vợt cho người mới</a></li>
                        </ul>
                    </li>

                    <!-- MENU GIÀY (Danh mục 2) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="{{ url('/sanpham?danh_muc=2') }}" role="button" data-bs-toggle="dropdown">
                            Giày
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=2') }}">Tất cả giày</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=2&thuong_hieu=1') }}">Giày Yonex</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=2&thuong_hieu=2') }}">Giày Lining</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=2&thuong_hieu=3') }}">Giày Victor</a></li>
                        </ul>
                    </li>

                   
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="{{ url('/sanpham?danh_muc=3,5') }}" role="button" data-bs-toggle="dropdown">
                            Quần áo
                        </a>
                        <ul class="dropdown-menu">
                            <!-- Đã ghép ID 3 (Quần áo) và 5 (Váy) để hiện TẤT CẢ -->
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=3,5') }}">Tất cả quần áo & váy</a></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Lọc chính xác Áo hoặc Quần -->
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=3&search=áo') }}">Áo cầu lông</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=3&search=quần') }}">Quần cầu lông</a></li>
                            
                            <!-- Váy cầu lông trỏ về Danh mục số 5 -->
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=5') }}">Váy cầu lông</a></li>
                        </ul>
                    </li>

                    <!-- MENU PHỤ KIỆN (Danh mục 4) -->
                   <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="{{ url('/sanpham?danh_muc=4') }}" role="button" data-bs-toggle="dropdown">
                            Phụ kiện
                        </a>
                        <ul class="dropdown-menu">
                            <!-- Bấm vào đây sẽ ra TOÀN BỘ phụ kiện thuộc danh mục 4 -->
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=4') }}">Tất cả phụ kiện</a></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <!-- Truyền thêm biến search để Controller tự động lọc riêng từng món -->
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=4&search=cước') }}">Cước cầu lông</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=4&search=quấn') }}">Quấn cán</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=4&search=túi') }}">Túi vợt</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=4&search=balo') }}">Balo cầu lông</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?danh_muc=4&search=quả cầu') }}">Quả cầu</a></li>
                        </ul>
                    </li>

                    <!-- MENU THƯƠNG HIỆU -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            Thương hiệu
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ url('/sanpham?thuong_hieu=1') }}">Yonex</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?thuong_hieu=2') }}">Lining</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?thuong_hieu=3') }}">Victor</a></li>
                            <li><a class="dropdown-item" href="{{ url('/sanpham?thuong_hieu=4') }}">Mizuno</a></li>
                        </ul>
                    </li>

                    <!-- MENU KHÁC -->
                    <li class="nav-item">
                        <a class="nav-link sale" href="#">Flash Sale</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('tintuc.index') }}">Tin tức</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Liên hệ</a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/header.js') }}"></script>
    <script src="{{ asset('js/Dangxuat.js') }}"></script>
</body>
</html>