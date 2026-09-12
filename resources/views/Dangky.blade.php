<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản - Badminton ProShop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
    <link rel="stylesheet" href="{{ asset('css/Dangky.css') }}">

</head>

<body>
 @include('layouts.header')
    <div class="register-page">

        {{-- ==========================================
             BÊN TRÁI
        =========================================== --}}
        <div class="register-left">

            <div class="register-brand">

                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="Badminton ProShop">

                <span>BADMINTON PROSHOP</span>

            </div>


            <div class="register-intro">

                <span class="intro-badge">
                    <i class="bi bi-lightning-fill"></i>
                    Chào mừng bạn
                </span>

                <h1>
                    Tạo tài khoản<br>
                    <span>ngay hôm nay</span>
                </h1>

                <p>
                    Đăng ký tài khoản để mua sắm vợt cầu lông,
                    giày, quần áo và phụ kiện dễ dàng hơn.
                </p>


                <div class="intro-features">

                    <div class="feature-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Mua sắm nhanh chóng</span>
                    </div>

                    <div class="feature-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Theo dõi đơn hàng dễ dàng</span>
                    </div>

                    <div class="feature-item">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Ưu đãi dành riêng cho thành viên</span>
                    </div>

                </div>

            </div>

        </div>


        {{-- ==========================================
             BÊN PHẢI
        =========================================== --}}
        <div class="register-right">

            <div class="register-box">

                <div class="register-header">

                    <h2>Tạo tài khoản</h2>

                    <p>
                        Đăng ký tài khoản để bắt đầu mua sắm
                    </p>

                </div>


                {{-- ==========================================
                     THÔNG BÁO TỪ JAVASCRIPT
                =========================================== --}}
                <div id="registerMessage"></div>


                {{-- ==========================================
                     FORM ĐĂNG KÝ
                     JavaScript sẽ xử lý submit
                =========================================== --}}
                <form id="registerForm">

                    {{-- HỌ TÊN --}}
                    <div class="form-group">

                        <label for="name">
                            Họ và tên
                        </label>

                        <div class="input-box">

                            <i class="bi bi-person"></i>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                placeholder="Nhập họ và tên"
                                autocomplete="name"
                                required>

                        </div>

                    </div>


                    {{-- EMAIL --}}
                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>

                        <div class="input-box">

                            <i class="bi bi-envelope"></i>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Nhập địa chỉ email"
                                autocomplete="email"
                                required>

                        </div>

                    </div>


                    {{-- SỐ ĐIỆN THOẠI --}}
                    <div class="form-group">

                        <label for="SoDienThoai">
                            Số điện thoại
                        </label>

                        <div class="input-box">

                            <i class="bi bi-telephone"></i>

                            <input
                                type="tel"
                                id="SoDienThoai"
                                name="SoDienThoai"
                                placeholder="Nhập số điện thoại"
                                autocomplete="tel"
                                required>

                        </div>

                    </div>
                    <div class="form-group">

                        <label for="DiaChi">
                            Địa chỉ
                        </label>

                        <div class="input-box">

                            <i class="bi bi-geo-alt"></i>

                            <input
                                type="text"
                                id="DiaChi"
                                name="DiaChi"
                                placeholder="Nhập địa chỉ"
                                autocomplete="address-line1"
                                required>

                        </div>
                    </div>

                    {{-- MẬT KHẨU --}}
                    <div class="form-group">

                        <label for="password">
                            Mật khẩu
                        </label>

                        <div class="input-box">

                            <i class="bi bi-lock"></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Nhập mật khẩu"
                                autocomplete="new-password"
                                required>

                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword('password', this)"
                                aria-label="Hiện hoặc ẩn mật khẩu">

                                <i class="bi bi-eye"></i>

                            </button>

                        </div>

                    </div>


                    {{-- NHẬP LẠI MẬT KHẨU --}}
                    <div class="form-group">

                        <label for="password_confirmation">
                            Nhập lại mật khẩu
                        </label>

                        <div class="input-box">

                            <i class="bi bi-lock-fill"></i>

                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                placeholder="Nhập lại mật khẩu"
                                autocomplete="new-password"
                                required>

                            <button
                                type="button"
                                class="show-password"
                                onclick="togglePassword('password_confirmation', this)"
                                aria-label="Hiện hoặc ẩn mật khẩu">

                                <i class="bi bi-eye"></i>

                            </button>

                        </div>

                    </div>


                    {{-- ĐIỀU KHOẢN --}}
                    <div class="register-terms">

                        <input
                            type="checkbox"
                            id="terms"
                            name="terms"
                            required>

                        <label for="terms">
                            Tôi đồng ý với
                            <a href="#">Điều khoản sử dụng</a>
                            và
                            <a href="#">Chính sách bảo mật</a>
                            của Badminton Shop.
                        </label>

                    </div>


                    {{-- NÚT ĐĂNG KÝ --}}
                    <button
                        type="submit"
                        id="registerButton"
                        class="register-btn">

                        <span id="registerButtonText">
                            Đăng ký tài khoản
                        </span>

                        <i
                            id="registerButtonIcon"
                            class="bi bi-arrow-right">
                        </i>

                    </button>

                </form>


                {{-- ĐĂNG NHẬP --}}
                <div class="login-link">

                    <span>
                        Đã có tài khoản?
                    </span>

                    <a href="{{ url('/Dangnhap') }}">
                        Đăng nhập ngay
                    </a>

                </div>


                {{-- QUAY LẠI TRANG CHỦ --}}
                <a
                    href="{{ url('/') }}"
                    class="back-home">

                    <i class="bi bi-arrow-left"></i>

                    Quay lại trang chủ

                </a>

            </div>

        </div>

    </div>

@include('layouts.footer')
    {{-- JavaScript đăng ký --}}
    <script src="{{ asset('js/Dangky.js') }}"></script>

</body>

</html>