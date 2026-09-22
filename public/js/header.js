window.laySoLuongGioHangToanCuc = async function() {
    try {
        let idKhachHang = null;
        const userString = localStorage.getItem('user');
        
        if (userString) {
            try {
                const user = JSON.parse(userString);
                if (user && user.ID_KhachHang) {
                    idKhachHang = user.ID_KhachHang;
                }
            } catch (e) {
                console.error(e);
            }
        }

        let maPhien = localStorage.getItem('MaPhien');
        if (!maPhien) {
            maPhien = crypto.randomUUID ? crypto.randomUUID() : 'guest-' + new Date().getTime();
            localStorage.setItem('MaPhien', maPhien);
        }

        let url = '/api/gio-hang?';
        if (idKhachHang) {
            url += `ID_KhachHang=${idKhachHang}`;
        } else {
            url += `MaPhien=${maPhien}`;
        }

        const res = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        const data = await res.json();
        
        if (data.success) {
            const badge = document.getElementById('cart-count');
            if (badge) {
                // CHỈ ĐẾM SỐ MẶT HÀNG KHÁC NHAU
                const soLoaiSanPham = data.items ? data.items.length : 0;
                badge.textContent = soLoaiSanPham;
                badge.style.display = soLoaiSanPham > 0 ? 'flex' : 'none'; 
            }
        }
    } catch (error) {
        console.error(error);
    }
};

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const accountName = document.getElementById('accountName');
    const registerLink = document.getElementById('registerLink');
    const loginLink = document.getElementById('loginLink');
    const logoutButton = document.getElementById('logoutButton');

    const userData = localStorage.getItem('user');
    if (userData) {
        try {
            const user = JSON.parse(userData);
            if (user && user.HoTen) {
                if (accountName) accountName.textContent = user.HoTen;
                if (registerLink) registerLink.remove();
                if (loginLink) loginLink.remove();
                if (logoutButton) logoutButton.style.setProperty('display', 'block', 'important');
            }
        } catch (error) {
            localStorage.removeItem('user');
        }
    }

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

    if (typeof window.laySoLuongGioHangToanCuc === 'function') {
        window.laySoLuongGioHangToanCuc();
    }
});