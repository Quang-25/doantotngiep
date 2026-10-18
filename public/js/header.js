
/* ========================================
   BADMINTON PROSHOP - HEADER.JS
======================================== */

/* ========================================
   CẬP NHẬT SỐ LƯỢNG GIỎ HÀNG
======================================== */
window.laySoLuongGioHangToanCuc = async function () {
    try {
        let idKhachHang = null;
        const userString = localStorage.getItem('user');

        if (userString) {
            try {
                const user = JSON.parse(userString);

                if (user && user.ID_KhachHang) {
                    idKhachHang = user.ID_KhachHang;
                }
            } catch (error) {
                console.error('Không đọc được thông tin người dùng:', error);
            }
        }

        let maPhien = localStorage.getItem('MaPhien');

        if (!maPhien) {
            maPhien = (
                window.crypto &&
                typeof window.crypto.randomUUID === 'function'
            )
                ? window.crypto.randomUUID()
                : 'guest-' + Date.now();

            localStorage.setItem('MaPhien', maPhien);
        }

        const params = new URLSearchParams();

        if (idKhachHang) {
            params.set('ID_KhachHang', idKhachHang);
        } else {
            params.set('MaPhien', maPhien);
        }

        const response = await fetch('/api/gio-hang?' + params.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Không thể tải thông tin giỏ hàng.');
        }

        const data = await response.json();

        if (data.success) {
            const badge = document.getElementById('cart-count');

            if (badge) {
                const soLoaiSanPham = Array.isArray(data.items)
                    ? data.items.length
                    : 0;

                badge.textContent = soLoaiSanPham;
                badge.style.display = soLoaiSanPham > 0 ? 'flex' : 'none';
            }
        }
    } catch (error) {
        console.error('Lỗi cập nhật giỏ hàng:', error);
    }
};


/* ========================================
   KHỞI TẠO HEADER
======================================== */
document.addEventListener('DOMContentLoaded', function () {

    /* ------------------------------------
       1. CÁC PHẦN TỬ HEADER
    ------------------------------------ */
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');

    const accountName = document.getElementById('accountName');
    const registerLink = document.getElementById('registerLink');
    const loginLink = document.getElementById('loginLink');
    const logoutButton = document.getElementById('logoutButton');

    const menuToggle = document.getElementById('mobileMenuToggle');
    const menu = document.getElementById('menu');

    /* ------------------------------------
       2. TÀI KHOẢN
    ------------------------------------ */
    const userData = localStorage.getItem('user');

    if (userData) {
        try {
            const user = JSON.parse(userData);

            if (user && user.HoTen) {
                if (accountName) {
                    accountName.textContent = user.HoTen;
                }

                if (registerLink) {
                    registerLink.remove();
                }

                if (loginLink) {
                    loginLink.remove();
                }

                if (logoutButton) {
                    logoutButton.style.display = 'block';
                }
            }
        } catch (error) {
            console.error('Dữ liệu tài khoản không hợp lệ:', error);
            localStorage.removeItem('user');
        }
    }

    /* ------------------------------------
       3. TÌM KIẾM SẢN PHẨM
    ------------------------------------ */
    function timKiem() {
        if (!searchInput) return;

        const keyword = searchInput.value.trim();

        if (keyword === '') {
            window.location.href = '/sanpham';
            return;
        }

        window.location.href =
            '/sanpham?search=' + encodeURIComponent(keyword);
    }

    if (searchButton && searchInput) {
        searchButton.addEventListener('click', timKiem);

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                timKiem();
            }
        });
    }

    /* ------------------------------------
       4. MENU 3 SỌC TRÊN ĐIỆN THOẠI
    ------------------------------------ */
    if (menuToggle && menu) {

        function dongMenuMobile() {
            menu.classList.remove('show');

            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Mở menu');
        }

        function moMenuMobile() {
            menu.classList.add('show');

            menuToggle.setAttribute('aria-expanded', 'true');
            menuToggle.setAttribute('aria-label', 'Đóng menu');
        }

        menuToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (menu.classList.contains('show')) {
                dongMenuMobile();
            } else {
                moMenuMobile();
            }
        });

        /* Đóng menu sau khi chọn mục con */
        menu.querySelectorAll('.dropdown-item').forEach(function (item) {
            item.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    dongMenuMobile();
                }
            });
        });

        /* Đóng menu sau khi chọn mục chính không có dropdown */
        menu.querySelectorAll('.nav-link:not(.dropdown-toggle)').forEach(
            function (item) {
                item.addEventListener('click', function () {
                    if (window.innerWidth < 992) {
                        dongMenuMobile();
                    }
                });
            }
        );

        /* Đóng menu khi bấm ra ngoài */
        document.addEventListener('click', function (event) {
            if (window.innerWidth >= 992) return;
            if (!menu.classList.contains('show')) return;

            const nav = menu.closest('.menu');

            if (nav && !nav.contains(event.target)) {
                dongMenuMobile();
            }
        });

        /* Khi quay về desktop, bỏ trạng thái mobile */
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                dongMenuMobile();
            }
        });
    }

    /* ------------------------------------
       5. CẬP NHẬT GIỎ HÀNG
    ------------------------------------ */
    if (typeof window.laySoLuongGioHangToanCuc === 'function') {
        window.laySoLuongGioHangToanCuc();
    }
});
