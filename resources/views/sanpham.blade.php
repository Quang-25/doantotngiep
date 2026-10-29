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
                                <label class="form-label" for="loc-sapxep">Sắp xếp</label>
                                <select name="sap_xep" id="loc-sapxep" class="form-select">
                                    <option value="">Mới nhất</option>
                                    <option value="gia_tang">Giá: thấp đến cao</option>
                                    <option value="gia_giam">Giá: cao đến thấp</option>
                                    <option value="giam_nhieu">Giảm giá nhiều nhất</option>
                                    <option value="ten">Tên A - Z</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="loc-danhmuc">Danh mục</label>
                                <select name="danhmuc" id="loc-danhmuc" class="form-select">
                                    <option value="">Tất cả danh mục</option>
                                    <option value="1">Vợt cầu lông</option>
                                    <option value="2">Giày cầu lông</option>
                                    <option value="3">Quần áo cầu lông</option>
                                    <option value="5">Váy cầu lông</option>
                                    <option value="4">Phụ kiện</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="loc-thuonghieu">Thương hiệu</label>
                                <select name="thuonghieu" id="loc-thuonghieu" class="form-select">
                                    <option value="">Tất cả thương hiệu</option>
                                    <option value="1">Yonex</option>
                                    <option value="2">Victor</option>
                                    <option value="3">Li-Ning</option>
                                    <option value="4">Mizuno</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="loc-gia">Khoảng giá</label>
                                <select name="gia" id="loc-gia" class="form-select">
                                    <option value="">Tất cả mức giá</option>
                                    <option value="0-500000">Dưới 500.000đ</option>
                                    <option value="500000-1000000">500.000đ - 1 triệu</option>
                                    <option value="1000000-2000000">1 - 2 triệu</option>
                                    <option value="2000000-3000000">2 - 3 triệu</option>
                                    <option value="3000000-5000000">3 - 5 triệu</option>
                                    <option value="5000000-">Trên 5 triệu</option>
                                </select>
                                <div class="filter-price-range mt-2">
                                    <input type="number" name="gia_tu" class="form-control" min="0" step="10000" placeholder="Từ (đ)" aria-label="Giá từ">
                                    <span>-</span>
                                    <input type="number" name="gia_den" class="form-control" min="0" step="10000" placeholder="Đến (đ)" aria-label="Giá đến">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tình trạng</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="giam_gia" id="loc-giamgia" value="1">
                                    <label class="form-check-label" for="loc-giamgia">Đang giảm giá</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="con_hang" id="loc-conhang" value="1">
                                    <label class="form-check-label" for="loc-conhang">Còn hàng</label>
                                </div>
                            </div>

                            <!-- Chỉ hiện khi chọn danh mục Vợt -->
                            <div class="filter-group" data-show-for="1" hidden>
                                <div class="filter-group-title">Thông số vợt</div>
                                <div class="mb-3">
                                    <label class="form-label" for="loc-trinhdo">Trình độ</label>
                                    <select name="trinh_do" id="loc-trinhdo" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="MoiChoi">Người mới chơi</option>
                                        <option value="NangCao">Nâng cao</option>
                                        <option value="ChuyenNghiep">Chuyên nghiệp</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="loc-trongluong">Trọng lượng</label>
                                    <select name="trong_luong" id="loc-trongluong" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="3U">3U (85-89g)</option>
                                        <option value="4U">4U (80-84g)</option>
                                        <option value="5U">5U (75-79g)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="loc-canbang">Điểm cân bằng</label>
                                    <select name="can_bang" id="loc-canbang" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="Đầu nặng">Đầu nặng (tấn công)</option>
                                        <option value="Cân bằng">Cân bằng (toàn diện)</option>
                                        <option value="Đầu nhẹ">Đầu nhẹ (phòng thủ)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="loc-docung">Độ cứng</label>
                                    <select name="do_cung" id="loc-docung" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="Dẻo">Dẻo</option>
                                        <option value="Trung bình">Trung bình</option>
                                        <option value="Cứng">Cứng</option>
                                        <option value="Rất cứng">Rất cứng</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="loc-cocan">Cỡ cán</label>
                                    <select name="co_can" id="loc-cocan" class="form-select">
                                        <option value="">Tất cả</option>
                                        <option value="G4">G4 (cán to)</option>
                                        <option value="G5">G5 (cán nhỏ)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Chỉ hiện khi chọn Giày / Quần áo / Váy -->
                            <div class="filter-group" data-show-for="2,3,5" hidden>
                                <div class="mb-3">
                                    <label class="form-label" for="loc-size">Size</label>
                                    <select name="size" id="loc-size" class="form-select">
                                        <option value="">Tất cả size</option>
                                        <optgroup label="Giày" data-for="2">
                                            <option>39</option><option>40</option><option>41</option>
                                            <option>42</option><option>43</option><option>44</option>
                                        </optgroup>
                                        <optgroup label="Quần áo, váy" data-for="3,5">
                                            <option>XS</option><option>S</option><option>M</option>
                                            <option>L</option><option>XL</option><option>XXL</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i>
                                    Áp dụng lọc
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-xoa-loc">
                                    Xóa lọc
                                </button>
                            </div>
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
    <script src="{{ asset('js/sanpham.js') }}"></script>
</body>
</html>