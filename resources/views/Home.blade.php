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
        <!-- BANNER -->
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
    </main>

    <!-- SẢN PHẨM BÁN CHẠY -->
    <section class="featured-products" id="san-pham-noi-bat">
        <div class="container">

            <div class="section-title">
                <h2>Sản phẩm bán chạy</h2>
                <div class="title-line">
                    <span></span>
                </div>
            </div>

            <div class="product-tabs">
                <a href="#" class="active">Tất cả</a>
                <a href="#">Vợt Cầu Lông</a>
                <a href="#">Giày Cầu Lông</a>
                <a href="#">Áo Cầu Lông</a>
                <a href="#">Váy cầu lông</a>
                <a href="#">Quần Cầu Lông</a>
            </div>

            <div class="product-wrapper">

                <button type="button" class="product-arrow left" aria-label="Sản phẩm trước">
                    <i class="bi bi-chevron-left"></i>
                </button>

                <div class="product-list" id="product-list">
                    <!-- Dữ liệu được load từ API -->
                </div>

                <button type="button" class="product-arrow right" aria-label="Sản phẩm tiếp theo">
                    <i class="bi bi-chevron-right"></i>
                </button>

            </div>
        </div>
    </section>

    <!-- SALE OFF -->
    <section class="featured sale">
        <div class="container">

            <div class="section-title">
                <h2>Sale off</h2>

                <div class="title-line">
                    <span></span>
                </div>
            </div>

            <div class="product-sale" id="product-sale">
                <!-- Dữ liệu được load từ API -->
            </div>

        </div>
    </section>

    @include('layouts.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/home.js') }}"></script>

</body>
</html>