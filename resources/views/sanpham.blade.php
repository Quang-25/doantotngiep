<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sanpham.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
    <title>Sản phẩm - Badminton Shop</title>
</head>
<body>
    @include('layouts.header')

    <section class="sanpham-page">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="filter-section">
                        <h4>Lọc sản phẩm</h4>

                        <form id="form-loc">
                            <div class="mb-3">
                                <label class="form-label">Danh mục</label>
                                <select name="danhmuc" class="form-select">
                                    <option value="" selected disabled>Chọn danh mục</option>
                                    <option value="1">Vợt cầu lông </option>
                                    <option value="2">Giày cầu lông</option>
                                    <option value="3">Quần áo cầu lông</option>
                                    <option value="4">Phụ kiện</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Thương hiệu</label>
                                <select name="thuonghieu" class="form-select">
                                    <option value="" selected disabled>Chọn thương hiệu</option>
                                    <option value="1">Yonex</option>
                                    <option value="2">Victor</option>
                                    <option value="3">Li-Ning</option>
                                    <option value="4">Mizuno</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Khoảng giá</label>
                                <select name="gia" class="form-select">
                                    <option value="" selected disabled>Chọn khoảng giá</option>
                                    <option value="1">Dưới 1 triệu</option>
                                    <option value="2">1 - 5 triệu</option>
                                    <option value="3">Trên 5 triệu</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-funnel"></i>
                                Áp dụng lọc
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="product-header">
                        <h2>Sản phẩm</h2>
                        <span id="product-count">0 sản phẩm</span>
                    </div>

                    <div class="row g-4 product-list" id="product-list">
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('layouts.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/sanpham.js') }}"></script>
</body>
</html>