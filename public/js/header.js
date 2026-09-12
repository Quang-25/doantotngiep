document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const accountName = document.getElementById('accountName');
    const registerLink = document.getElementById('registerLink');
    const loginLink = document.getElementById('loginLink');
    const logoutButton = document.getElementById('logoutButton');
    /* =========================
       HIỂN THỊ TÊN TÀI KHOẢN
    ========================= */
    const userData = localStorage.getItem('user');
    if (userData) {
        try {
            const user = JSON.parse(userData);
            if (user && user.HoTen) {
                // Hiển thị tên người dùng
                if (accountName) accountName.textContent = user.HoTen;
                // XÓA HẲN phần tử khỏi DOM để tránh bị CSS ghi đè
                if (registerLink) registerLink.remove();
                if (loginLink) loginLink.remove();
                // Ép hiển thị nút Đăng xuất bằng important
                if (logoutButton) logoutButton.style.setProperty('display', 'block', 'important');
            }
        } catch (error) {
            console.error('Lỗi đọc thông tin tài khoản:', error);
            localStorage.removeItem('user');
        }
    }
    /* =========================
       TÌM KIẾM SẢN PHẨM
    ========================= */
    if (searchInput && searchButton) {
        function timKiem() {
            const keyword = searchInput.value.trim();
            if (keyword === '') {
                window.location.href = '/sanpham';
                return;
            }
            window.location.href = '/sanpham?search=' + encodeURIComponent(keyword);
        }
        searchButton.addEventListener('click', timKiem);
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault(); 
                timKiem();
            }
        });
    }
});