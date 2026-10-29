<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Liên hệ - Badminton Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/lienhe.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
</head>
<body class="contact-body">
    @include('layouts.header')

    <div class="container mt-4 mb-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="{{ url('/') }}" class="text-decoration-none text-muted">Trang chủ</a></li>
                <li class="breadcrumb-item active fw-medium" aria-current="page">Liên hệ</li>
            </ol>
        </nav>

        <div class="contact-hero mb-4">
            <h1>Liên hệ với Badminton Shop</h1>
            <p>Bạn cần tư vấn chọn vợt, hỏi về đơn hàng, đổi trả hay bảo hành? Gửi lời nhắn cho shop, chúng tôi sẽ phản hồi qua email trong thời gian sớm nhất.</p>
        </div>

        <div class="row g-4">
            <!-- Thông tin liên hệ -->
            <div class="col-lg-5">
                <div class="contact-card h-100">
                    <h2 class="contact-card-title">Thông tin cửa hàng</h2>
                    <ul class="contact-info">
                        <li>
                            <span class="contact-icon"><i class="bi bi-geo-alt-fill"></i></span>
                            <div><strong>Địa chỉ</strong><span>Tổ 4, Đền Rồng, Phường Từ Sơn, Thành Phố Bắc Ninh</span></div>
                        </li>
                        <li>
                            <span class="contact-icon"><i class="bi bi-telephone-fill"></i></span>
                            <div><strong>Hotline</strong><a href="tel:0352143569">035 214 3569</a></div>
                        </li>
                        <li>
                            <span class="contact-icon"><i class="bi bi-envelope-fill"></i></span>
                            <div><strong>Email</strong><a href="mailto:Cohoi2512@gmail.com">Cohoi2512@gmail.com</a></div>
                        </li>
                        <li>
                            <span class="contact-icon"><i class="bi bi-person-fill"></i></span>
                            <div><strong>Người liên hệ</strong><span>Trần Minh Quang</span></div>
                        </li>
                        <li>
                            <span class="contact-icon"><i class="bi bi-clock-fill"></i></span>
                            <div><strong>Thời gian hỗ trợ</strong><span>08:00 - 22:00 hằng ngày</span></div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Form gửi phản hồi -->
            <div class="col-lg-7">
                <div class="contact-card h-100">
                    <h2 class="contact-card-title">Gửi phản hồi cho shop</h2>
                    <div id="contactMessage" aria-live="polite"></div>
                    <form id="contactForm" novalidate>
                        <!-- Ô bẫy bot: người dùng không nhìn thấy -->
                        <div class="contact-hp" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="ho_ten" class="form-label">Họ tên <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ho_ten" name="ho_ten" maxlength="100" autocomplete="name" required>
                                <div class="invalid-feedback" data-error="ho_ten"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" maxlength="100" autocomplete="email" required>
                                <div class="invalid-feedback" data-error="email"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="so_dien_thoai" class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" id="so_dien_thoai" name="so_dien_thoai" maxlength="15" autocomplete="tel">
                                <div class="invalid-feedback" data-error="so_dien_thoai"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="chu_de" class="form-label">Chủ đề <span class="text-danger">*</span></label>
                                <select class="form-select" id="chu_de" name="chu_de" required>
                                    <option value="">-- Chọn chủ đề --</option>
                                    <option>Tư vấn sản phẩm</option>
                                    <option>Đơn hàng & giao hàng</option>
                                    <option>Đổi trả & bảo hành</option>
                                    <option>Góp ý website</option>
                                    <option>Khác</option>
                                </select>
                                <div class="invalid-feedback" data-error="chu_de"></div>
                            </div>
                            <div class="col-12">
                                <label for="noi_dung" class="form-label">Nội dung <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="noi_dung" name="noi_dung" rows="5" maxlength="3000" placeholder="Bạn cần shop hỗ trợ điều gì?" required></textarea>
                                <div class="d-flex justify-content-between">
                                    <div class="invalid-feedback d-block" data-error="noi_dung"></div>
                                    <small class="text-muted ms-auto"><span id="noiDungCount">0</span>/3000</small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn contact-submit mt-3" id="contactSubmit">
                            <i class="bi bi-send-fill me-1"></i> <span>Gửi phản hồi</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bản đồ -->
        <div class="contact-card mt-4">
            <h2 class="contact-card-title">Bản đồ đường đi</h2>
            <div class="contact-map">
                <iframe
                    src="https://maps.google.com/maps?q={{ urlencode('Đền Rồng, Từ Sơn, Bắc Ninh') }}&z=15&output=embed"
                    title="Bản đồ Badminton Shop - Đền Rồng, Từ Sơn, Bắc Ninh"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen></iframe>
            </div>
            <a class="contact-map-link" href="https://www.google.com/maps/search/?api=1&query={{ urlencode('Đền Rồng, Từ Sơn, Bắc Ninh') }}" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right"></i> Mở trong Google Maps để chỉ đường
            </a>
        </div>
    </div>

    @include('layouts.footer')
    <script src="{{ asset('js/lienhe.js') }}"></script>
</body>
</html>
