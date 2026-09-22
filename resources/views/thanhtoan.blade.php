<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - Badminton Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
    <link rel="stylesheet" href="{{ asset('css/thanhtoan.css') }}">
</head>
<body>
    @include('layouts.header')
    
    <main class="checkout-page pt-2 pb-5">
        <div class="container">
            <div class="checkout-header text-center mb-4 mt-0">
                <i class="bi bi-credit-card-2-front text-dark" style="font-size: 2.2rem;"></i>
                <h3 class="mt-1 fw-bold text-dark">Thanh toán</h3>
                <p class="text-muted mb-0">Vui lòng kiểm tra thông tin Khách hàng, thông tin Giỏ hàng trước khi Đặt hàng.</p>
            </div>

            <form action="{{ route('thanhtoan.vnpay') }}" method="POST" class="customer-info-form">
                @csrf
                <!-- Truyền ID khách hàng ngầm để Server lưu vào bảng DonHang -->
                <input type="hidden" name="id_khachhang" value="{{ $khachHang->ID_KhachHang ?? 0 }}">
                
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h4 class="mb-4 text-dark fw-semibold">Thông tin khách hàng</h4>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Họ tên</label>
                            <input type="text" name="ten_nguoi_nhan" value="{{ $khachHang->HoTen }}" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Địa chỉ</label>
                            <input type="text" name="dia_chi" class="form-control bg-light border-0" value="{{ $khachHang->DiaChi ?? '' }}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Điện thoại</label>
                            <input type="text" name="so_dien_thoai" class="form-control bg-light border-0" value="{{ $khachHang->SoDienThoai ?? '' }}" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Email</label>
                            <input type="email" name="email" class="form-control bg-light border-0" value="{{ $khachHang->Email ?? '' }}" readonly>
                        </div>
                    </div>
                    
                    <div class="col-lg-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0 text-dark fw-semibold">Giỏ hàng</h4>
                            <span class="badge bg-secondary rounded-pill fs-6">{{ isset($cartItems) ? count($cartItems) : 0 }}</span>
                        </div>

                        <div class="cart-summary border rounded-3 overflow-hidden mb-4 bg-white">
                            <ul class="list-group list-group-flush">
                                
                                @if(isset($cartItems) && count($cartItems) > 0)
                                    @foreach($cartItems as $index => $item)
                                        <!-- Đóng gói dữ liệu mảng chi tiết sản phẩm để gửi lên Server lưu bảng ChiTietDonHang -->
                                        <input type="hidden" name="cart_items[{{ $index }}][id_sanpham]" value="{{ $item->ID_SanPham }}">
                                        <input type="hidden" name="cart_items[{{ $index }}][so_luong]" value="{{ $item->SoLuong }}">
                                        <input type="hidden" name="cart_items[{{ $index }}][gia_mua]" value="{{ $item->GiaThucTe }}">

                                        <li class="list-group-item d-flex justify-content-between lh-sm p-3">
                                            <div>
                                                <h6 class="my-0 text-dark">{{ $item->TenSanPham }}</h6>
                                                <small class="text-muted">{{ number_format($item->GiaThucTe, 0, ',', '.') }}đ x {{ $item->SoLuong }}</small>
                                            </div>
                                            <span class="text-muted">{{ number_format($item->GiaThucTe * $item->SoLuong, 0, ',', '.') }}đ</span>
                                        </li>
                                    @endforeach
                                @else
                                    <li class="list-group-item p-3 text-center text-muted">Giỏ hàng trống</li>
                                @endif

                                <li class="list-group-item d-flex justify-content-between bg-light p-3 align-items-center">
                                    <span class="fw-medium text-dark">Tổng thành tiền</span>
                                    <strong class="text-danger fs-5">{{ number_format($tongTien ?? 0, 0, ',', '.') }}đ</strong>
                                </li>
                            </ul>

                            <input type="hidden" name="tong_tien" value="{{ $tongTien ?? 0 }}">

                            <div class="p-3 border-bottom">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Mã khuyến mãi">
                                    <button type="button" class="btn btn-secondary px-4">Xác nhận</button>
                                </div>
                            </div>

                            <div class="p-4">
                                <h5 class="mb-3 text-dark fw-semibold">Hình thức thanh toán</h5>
                                <div class="payment-methods">
                                    <div class="form-check mb-2">
                                        <!-- Đổi tên thành phuong_thuc để khớp biến $request->phuong_thuc -->
                                        <input class="form-check-input" type="radio" name="phuong_thuc" value="COD" id="cod" checked>
                                        <label class="form-check-label text-muted" for="cod">Thanh toán khi nhận hàng (COD)</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="phuong_thuc" value="VNPay" id="vnpay">
                                        <label class="form-check-label text-muted" for="vnpay">Thanh toán qua VNPay</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5 btn-dat-hang">Xác nhận Đặt hàng</button>
                    </div>
                </div>
            </form>
            
        </div>
    </main>
    @include('layouts.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/header.js') }}"></script>
    <script src="{{ asset('js/thanhtoan.js') }}"></script>
</body>
</html>