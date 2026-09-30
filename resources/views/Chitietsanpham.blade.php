<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sanPham->TenSanPham }} - Badminton Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
    <link rel="stylesheet" href="{{ asset('css/chitietsanpham.css') }}">
</head>
<body style="background-color: #f5f5f5;">
    @include('layouts.header')

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-3">
                <li class="breadcrumb-item"><a href="/">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="#">{{ $sanPham->TenDanhMuc }}</a></li>
                <li class="breadcrumb-item active text-secondary">{{ $sanPham->TenSanPham }}</li>
            </ol>
        </nav>

        <!-- BAO BỌC TOÀN BỘ VÀO 1 KHỐI TRẮNG DUY NHẤT -->
        <div class="bg-white shadow-sm rounded-3 mb-4">
            
            <!-- PHẦN 1: THÔNG TIN SẢN PHẨM (Bên trên) -->
            <div class="row p-4 border-bottom m-0">
                <!-- Cột Trái: Hình Ảnh -->
                <div class="col-md-5 p-0 pe-md-3">
                    @php 
                        $mainImgSrc = (strpos($sanPham->HinhAnh, 'http') === 0) ? $sanPham->HinhAnh : asset('images/' . $sanPham->HinhAnh);
                    @endphp
                    
                    <div class="main-image-container mb-2">
                        <img id="mainImage" src="{{ $mainImgSrc }}" class="main-image" alt="{{ $sanPham->TenSanPham }}">
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                        <img src="{{ $mainImgSrc }}" class="gallery-thumb active" onclick="changeImage(this, '{{ $mainImgSrc }}')">
                        @foreach($hinhAnhGallery as $anh)
                            @php 
                                $thumbSrc = (strpos($anh->DuongDan, 'http') === 0) ? $anh->DuongDan : asset('images/' . $anh->DuongDan);
                            @endphp
                            <img src="{{ $thumbSrc }}" class="gallery-thumb" onclick="changeImage(this, '{{ $thumbSrc }}')">
                        @endforeach
                    </div>
                </div>

                <!-- Cột Phải: Chi Tiết & Đặt Hàng -->
                <div class="col-md-7 ps-md-4 mt-4 mt-md-0">
                    <h2 class="text-dark mb-3" style="font-weight: 400;">{{ $sanPham->TenSanPham }}</h2>
                    
                    <div class="d-flex align-items-center mb-3 fs-6">
                        <span class="me-3 border-end pe-3 text-muted">Thương hiệu: <a href="#" class="text-decoration-none fw-bold" style="color: #0056b3;">{{ $sanPham->TenThuongHieu }}</a></span>
                        <span class="text-muted">Tình trạng: 
                            @if($sanPham->SoLuongTon > 0)
                                <span class="text-success fw-bold">Còn {{ $sanPham->SoLuongTon }} SP</span>
                            @else
                                <span class="text-danger fw-bold">Hết hàng</span>
                            @endif
                        </span>
                    </div>

                    <div class="price-box mb-4">
                        @if($sanPham->GiaKhuyenMai && $sanPham->GiaKhuyenMai < $sanPham->GiaBan)
                            <span class="old-price">{{ number_format($sanPham->GiaBan, 0, ',', '.') }}đ</span>
                            <span class="current-price">{{ number_format($sanPham->GiaKhuyenMai, 0, ',', '.') }}đ</span>
                            <span class="discount-badge">Giảm {{ round((($sanPham->GiaBan - $sanPham->GiaKhuyenMai) / $sanPham->GiaBan) * 100) }}%</span>
                        @else
                            <span class="current-price">{{ number_format($sanPham->GiaBan, 0, ',', '.') }}đ</span>
                        @endif
                    </div>

                    <form id="formThemGioHang" class="mb-4">
                        @csrf
                        <input type="hidden" name="id_sanpham" value="{{ $sanPham->ID_SanPham }}">
                        
                        <div class="d-flex align-items-center mb-4">
                            <label class="me-4 text-muted fw-medium">Số lượng</label>
                            <div class="input-group" style="width: 140px;">
                                <button class="btn btn-outline-secondary px-3" type="button" onclick="updateQty(-1)">-</button>
                                <input type="number" name="so_luong" id="inputQty" class="form-control text-center fw-bold" value="1" min="1" max="{{ $sanPham->SoLuongTon }}">
                                <button class="btn btn-outline-secondary px-3" type="button" onclick="updateQty(1)">+</button>
                            </div>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="button" id="btn-them-gio" class="btn btn-primary px-4 py-2 fw-bold rounded-1" style="background-color: #0d6efd; border-color: #0d6efd;" {{ $sanPham->SoLuongTon <= 0 ? 'disabled' : '' }}>
                            <i class="bi bi-cart-plus me-2 fs-5"></i>Thêm Vào Giỏ Hàng
                            </button>
                            <button type="button" id="btn-mua-ngay" class="btn btn-primary px-5 py-2 fw-bold rounded-1" style="background-color: #0d6efd; border-color: #0d6efd;" {{ $sanPham->SoLuongTon <= 0 ? 'disabled' : '' }}>
                                Mua Ngay
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PHẦN 2: MÔ TẢ SẢN PHẨM (Bên dưới, chung nền trắng) -->
            <div class="row p-4 m-0">
                <div class="col-md-12 p-0">
                    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold text-dark" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc" type="button" role="tab">Mô Tả Sản Phẩm</button>
                        </li>
                        @if($thongSoVot)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold text-dark" id="spec-tab" data-bs-toggle="tab" data-bs-target="#spec" type="button" role="tab">Thông Số Kỹ Thuật</button>
                        </li>
                        @endif
                    </ul>
                    
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="desc" role="tabpanel">
                            <div class="product-description text-dark" style="line-height: 1.8; font-size: 15px;">
                                {!! nl2br(e($sanPham->MoTa)) !!}
                            </div>
                        </div>
                        
                        @if($thongSoVot)
                        <div class="tab-pane fade" id="spec" role="tabpanel">
                            <table class="table table-bordered spec-table w-75">
                                <tbody>
                                    @if($thongSoVot->TrongLuong)<tr><th>Trọng lượng</th><td>{{ $thongSoVot->TrongLuong }}</td></tr>@endif
                                    @if($thongSoVot->ChuViTayCam)<tr><th>Chu vi tay cầm</th><td>{{ $thongSoVot->ChuViTayCam }}</td></tr>@endif
                                    @if($thongSoVot->DiemCanBang)<tr><th>Điểm cân bằng</th><td>{{ $thongSoVot->DiemCanBang }}</td></tr>@endif
                                    @if($thongSoVot->DoCung)<tr><th>Độ cứng</th><td>{{ $thongSoVot->DoCung }}</td></tr>@endif
                                    @if($thongSoVot->MucCangToiDa)<tr><th>Mức căng tối đa</th><td>{{ $thongSoVot->MucCangToiDa }}</td></tr>@endif
                                    @if($thongSoVot->TrinhDoPhuHop)<tr><th>Trình độ phù hợp</th><td>{{ $thongSoVot->TrinhDoPhuHop }}</td></tr>@endif
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            
        </div> <!-- KẾT THÚC KHỐI TRẮNG -->
    </div>

    @include('layouts.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/chitietsanpham.js') }}"></script>
</body>
</html>