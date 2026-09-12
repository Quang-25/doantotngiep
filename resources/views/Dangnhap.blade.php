<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Đăng nhập - Badminton ProShop</title>

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
                        <img
                            src="{{ asset('images/logo.png') }}"
                            alt="Badminton ProShop"
                            class="login-logo">
                    </div>

                    <h1>BADMINTON PROSHOP</h1>

                    <p>Chào mừng bạn đến với Badminton ProShop!</p>

                    <div class="image-line"></div>

                    <span>
                        Đăng nhập để trải nghiệm mua sắm tuyệt vời tại Badminton ProShop!
                    </span>

                </div>
            </section>

            <section class="login-right">
                <div class="login-box">

                    <div id="loginMessage"></div>

                    <form id="loginForm">

                        <div class="form-group">
                            <label for="email">Email</label>

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

                        <div class="form-group">
                            <label for="password">Mật khẩu</label>

                            <div class="input-box">
                                <i class="bi bi-lock"></i>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Nhập mật khẩu"
                                    autocomplete="current-password"
                                    required>

                                <button
                                    type="button"
                                    class="show-password"
                                    onclick="toggleLoginPassword()"
                                    aria-label="Hiện hoặc ẩn mật khẩu">

                                    <i id="passwordIcon" class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="login-options">
                            <label class="remember-me">
                                <input type="checkbox" id="remember">
                                <span>Ghi nhớ đăng nhập</span>
                            </label>

                            <a href="#">Quên mật khẩu?</a>
                        </div>

                        <button
                            type="submit"
                            class="login-btn"
                            id="loginButton">

                            <span id="loginButtonText">Đăng nhập</span>

                            <i
                                class="bi bi-arrow-right"
                                id="loginButtonIcon">
                            </i>
                        </button>

                    </form>

                    <div class="register-link">
                        <span>Chưa có tài khoản?</span>
                        <a href="{{ url('/Dangky') }}">Đăng ký ngay</a>
                    </div>

                    <a href="{{ url('/') }}" class="back-home">
                        <i class="bi bi-arrow-left"></i>
                        <span>Quay lại trang chủ</span>
                    </a>

                </div>
            </section>

        </div>
    </main>

    @include('layouts.Footer')

    <script src="{{ asset('js/Dangnhap.js') }}"></script>

</body>
</html>