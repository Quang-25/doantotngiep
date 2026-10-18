<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badminton Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
</head>
<body>
    @include('layouts.header')
    
    <main>
        <div class="banner-slider-wrapper">
            <div class="banner-slider-track" id="bannerTrack">

                <!-- SLIDE 1: BANNER GỐC CỦA BẠN (GIỮ NGUYÊN 100%) -->
                <section class="anhsanpham">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <span class="eyebrow-badge">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    Ưu đãi mùa giải mới
                                </span>
                                
                                <h1>
                                    VỢT CẦU LÔNG<br>
                                    CHÍNH HÃNG<br>
                                    GIẢM ĐẾN 50%
                                </h1>
                                
                                <p class="lead">
                                    Từ người mới tập đến vận động viên phong trào —
                                    chọn đúng vợt, đúng lối chơi, đúng ngân sách.
                                </p>
                                
                                <a href="#san-pham-noi-bat" class="btn btn-cta mt-2">
                                    Mua ngay
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>

                            <div class="col-lg-5 text-center mt-4 mt-lg-0">
                                <div class="hero-price-tag">Chỉ từ 399K</div>
                                <p class="mb-0">Áp dụng cho người mới chơi</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SLIDE 2: DÙNG ẢNH banner1.jpg -->
                <section class="anhsanpham banner-slide-2">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <span class="eyebrow-badge">
                                    <i class="bi bi-fire"></i>
                                    Bộ sưu tập mới nhất
                                </span>
                                
                                <h1>
                                    ĐA DẠNG MẪU MÃ<br>
                                    CÔNG THỦ TOÀN DIỆN<br>
                                    TRỢ LỰC TỐI ĐA
                                </h1>
                                
                                <p class="lead">
                                    Đầy đủ các dòng vợt bền đẹp, căng sẵn lưới chuẩn —
                                    sẵn sàng cùng bạn bùng nổ trên mọi mặt sân.
                                </p>
                                
                                <a href="#san-pham-noi-bat" class="btn btn-cta mt-2">
                                    Khám phá ngay
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>

                            <div class="col-lg-5 text-center mt-4 mt-lg-0">
                                <div class="hero-price-tag">Tặng Quấn Cán</div>
                                <p class="mb-0">Bảo hành chính hãng uy tín</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SLIDE 3: DÙNG ẢNH banner2.jpg -->
                <section class="anhsanpham banner-slide-3">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <span class="eyebrow-badge">
                                    <i class="bi bi-heart-fill"></i>
                                    Đam mê cầu lông
                                </span>
                                
                                <h1>
                                    KẾT NỐI ĐAM MÊ<br>
                                    COMBO VỢT ĐÔI<br>
                                    ƯU ĐÃI CỰC SỐC
                                </h1>
                                
                                <p class="lead">
                                    Mua theo cặp tiết kiệm hơn — lựa chọn tuyệt vời để
                                    rèn luyện sức khỏe cùng bạn bè và người thân.
                                </p>
                                
                                <a href="#san-pham-noi-bat" class="btn btn-cta mt-2">
                                    Xem ưu đãi
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>

                            <div class="col-lg-5 text-center mt-4 mt-lg-0">
                                <div class="hero-price-tag">Freeship Toàn Quốc</div>
                                <p class="mb-0">Áp dụng khi mua từ 2 cây</p>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </main> 
        <section class="policy">
        <div class="container">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="policy-box">
                        <div class="policy-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div class="policy-info">
                            <h6>Vận chuyển TOÀN QUỐC</h6>
                            <p>Thanh toán khi nhận hàng</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-lg-3">
                    <div class="policy-box">
                        <div class="policy-icon">
                            <i class="bi bi-patch-check"></i>
                        </div>
                        <div class="policy-info">
                            <h6>Bảo đảm chất lượng</h6>
                            <p>Sản phẩm bảo đảm chất lượng</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-lg-3">
                    <div class="policy-box">
                        <div class="policy-icon">
                            <i class="bi bi-credit-card"></i>
                        </div>
                        <div class="policy-info">
                            <h6>Tiến hành THANH TOÁN</h6>
                            <p>Với nhiều PHƯƠNG THỨC</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-lg-3">
                    <div class="policy-box">
                        <div class="policy-icon">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <div class="policy-info">
                            <h6>Đổi sản phẩm mới</h6>
                            <p>Nếu sản phẩm lỗi</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="featured-products" id="san-pham-noi-bat">
        <div class="container">
            <div class="section-title">
                <h2>Sản phẩm bán chạy</h2>
                <div class="title-line">
                    <span></span>
                </div>
            </div>
            
            <div class="product-tabs">
                <a href="#" class="active" data-id="">Tất cả</a>
                <a href="#" data-id="1">Vợt Cầu Lông</a>
                <a href="#" data-id="2">Giày Cầu Lông</a>
                <a href="#" data-id="3">Quần Áo</a>
                <a href="#" data-id="4">Phụ kiện</a> 
                <a href="#" data-id="5">Váy</a> 
            </div>

            <div class="product-wrapper">
                <button type="button" class="product-arrow left" aria-label="Sản phẩm trước">
                    <i class="bi bi-chevron-left"></i>
                </button>

                <div class="product-list" id="product-list">
                </div>

                <button type="button" class="product-arrow right" aria-label="Sản phẩm tiếp theo">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>
    
    <section class="featured sale sale-section">
    <div class="container">
        <div class="sale-heading">
            <h2><span>Sale off</span></h2>
            <p>Giá sốc mỗi ngày, số lượng có hạn</p>
        </div>
        <div class="product-sale" id="product-sale">
        </div>
    </div>
</section>
    
    @include('layouts.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/home.js') }}"></script>
</body>
</html>