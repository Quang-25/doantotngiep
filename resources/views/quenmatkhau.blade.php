<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Khôi phục mật khẩu - Badminton ProShop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
    <link rel="stylesheet" href="{{ asset('css/Dangnhap.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
</head>

<body>

    @include('layouts.Header')

    <main class="login-page">
        <div class="login-card">

            <section class="login-image">
                <div class="image-content">
                    <div class="logo-wrapper">
                        <img src="{{ asset('images/logo.png') }}" alt="Badminton ProShop" class="login-logo">
                    </div>
                    <h1>KHÔI PHỤC MẬT KHẨU</h1>
                    <p>Đừng lo lắng, chúng tôi sẽ giúp bạn!</p>
                    <div class="image-line"></div>
                    <span>Vui lòng cung cấp đúng Email và Số điện thoại đã đăng ký để đổi mật khẩu mới.</span>
                </div>
            </section>

            <section class="login-right">
                <div class="login-box">
                    <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 20px; text-align: center;">Tạo mật khẩu mới</h2>

                    <form id="formResetPassword">
                        @csrf 

                        <div class="form-group">
                            <label for="email">Email đã đăng ký</label>
                            <div class="input-box">
                                <i class="bi bi-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="Nhập địa chỉ email" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="SoDienThoai">Số điện thoại xác minh</label>
                            <div class="input-box">
                                <i class="bi bi-telephone"></i>
                                <input type="text" id="SoDienThoai" name="SoDienThoai" placeholder="Nhập số điện thoại" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Mật khẩu mới</label>
                            <div class="input-box">
                                <i class="bi bi-lock"></i>
                                <input type="password" id="password" name="password" placeholder="Tối thiểu 8 ký tự" minlength="8" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">Xác nhận mật khẩu</label>
                            <div class="input-box">
                                <i class="bi bi-shield-lock"></i>
                                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Nhập lại mật khẩu mới" required>
                            </div>
                        </div>

                        <button type="submit" class="login-btn" id="btnReset">
                            <span>Cập nhật mật khẩu</span>
                            <i class="bi bi-check-circle"></i>
                        </button>
                    </form>

                    <a href="{{ url('/Dangnhap') }}" class="back-home mt-4" style="display: flex; justify-content: center;">
                        <i class="bi bi-arrow-left"></i>
                        <span>Quay lại đăng nhập</span>
                    </a>

                </div>
            </section>

        </div>
    </main>

    @include('layouts.Footer')

    <!-- Nhúng file JS riêng (Nằm cùng thư mục public/js/ với Dangnhap.js) -->
    <script src="{{ asset('js/quenmatkhau.js') }}"></script>

</body>
</html>