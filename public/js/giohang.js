document.addEventListener('DOMContentLoaded', function () {
    let idKhachHang = null;
    try {
        const user = JSON.parse(localStorage.getItem('user'));
        if (user && user.ID_KhachHang) {
            idKhachHang = user.ID_KhachHang;
        }
    } catch (error) {
        console.error(error);
    }

    let maPhien = localStorage.getItem('MaPhien');
    if (!maPhien) {
        maPhien = crypto.randomUUID ? crypto.randomUUID() : 'guest-' + new Date().getTime();
        localStorage.setItem('MaPhien', maPhien);
    }

    loadCartData();

    function formatCurrency(number) {
        return new Intl.NumberFormat('vi-VN').format(number) + ' ₫';
    }

    async function loadCartData() {
        const cartContainer = document.getElementById('cart-items');
        try {
            let url = '/api/gio-hang?';
            if (idKhachHang) {
                url += `ID_KhachHang=${idKhachHang}`;
            } else {
                url += `MaPhien=${maPhien}`;
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin' 
            });

            const result = await response.json();
            if (response.ok && result.success) {
                renderCart(result);
            } else {
                cartContainer.innerHTML = '<div class="text-center p-5 text-danger">Chưa thể lấy dữ liệu giỏ hàng.</div>';
            }
        } catch (error) {
            cartContainer.innerHTML = '<div class="text-center p-5 text-danger">Lỗi kết nối đến máy chủ.</div>';
        }
    }

    async function sendCartAction(url, data) {
        try {
            if (idKhachHang) {
                data.ID_KhachHang = idKhachHang;
            } else {
                data.MaPhien = maPhien; 
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                loadCartData();
            }
        } catch (error) {
            console.error(error);
        }
    }

    document.getElementById('cart-items').addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-minus')) {
            const id = e.target.getAttribute('data-id');
            const input = e.target.nextElementSibling;
            let qty = parseInt(input.value) - 1;
            if (qty > 0) {
                input.value = qty;
                sendCartAction('/api/gio-hang/cap-nhat', { ID_SanPham: id, SoLuong: qty });
            }
        }

        if (e.target.classList.contains('btn-plus')) {
            const id = e.target.getAttribute('data-id');
            const input = e.target.previousElementSibling;
            let qty = parseInt(input.value) + 1;
            input.value = qty;
            sendCartAction('/api/gio-hang/cap-nhat', { ID_SanPham: id, SoLuong: qty });
        }

        const btnDelete = e.target.closest('.btn-delete-item');
        if (btnDelete) {
            e.preventDefault();
            const id = btnDelete.getAttribute('data-id');
            sendCartAction('/api/gio-hang/xoa', { ID_SanPham: id });
        }

        const btnClear = e.target.closest('.btn-clear-all');
        if (btnClear) {
            e.preventDefault();
            if(confirm('Bạn có chắc chắn muốn xóa toàn bộ sản phẩm trong giỏ?')) {
                sendCartAction('/api/gio-hang/xoa-tat-ca', {});
            }
        }
    });

    function renderCart(data) {
        const cartContainer = document.getElementById('cart-items');
        const finalTotalEl = document.getElementById('final-total');
        const totalCountText = finalTotalEl.previousElementSibling;
        
        if (typeof window.laySoLuongGioHangToanCuc === 'function') {
            window.laySoLuongGioHangToanCuc();
        }

        if (!data.items || data.items.length === 0) {
            cartContainer.innerHTML = `
                <div class="text-center p-5">
                    <i class="bi bi-cart-x text-muted" style="font-size: 3rem;"></i>
                    <h6 class="mt-3 text-muted">Giỏ hàng của bạn đang trống</h6>
                </div>
            `;
            finalTotalEl.textContent = '0 ₫';
            totalCountText.textContent = 'Tổng cộng (0 sản phẩm)';
            return;
        }

        let htmlContent = '';
        
        data.items.forEach(item => {
            htmlContent += `
                <div class="cart-item-row d-flex align-items-center border-bottom" data-id="${item.ID_SanPham}">
                    <img src="${item.HinhAnh}" alt="Sản phẩm" class="cart-product-img me-3">
                    
                    <div class="cart-item-info pe-3">
                        <h6 class="text-dark fw-medium mb-2">${item.TenSanPham}</h6>
                        <a href="#" class="text-decoration-none btn-delete-item" data-id="${item.ID_SanPham}">
                            <i class="bi bi-trash3 me-1"></i>Xóa
                        </a>
                    </div>
                    
                    <div class="theme-text fw-bold price-col">${formatCurrency(item.Gia)}</div>
                    
                    <div class="quantity-group mx-4">
                        <button class="qty-btn btn-minus" type="button" data-id="${item.ID_SanPham}">-</button>
                        <input type="text" class="qty-input" value="${item.SoLuong}" readonly>
                        <button class="qty-btn btn-plus" type="button" data-id="${item.ID_SanPham}">+</button>
                    </div>
                    
                    <div class="fw-bold subtotal-col text-dark">${formatCurrency(item.ThanhTien)}</div>
                </div>
            `;
        });

        htmlContent += `
            <div class="d-flex justify-content-between align-items-center p-4 bg-white rounded-bottom-3">
                <a href="#" class="text-decoration-none btn-clear-all fw-medium">
                    Xóa toàn bộ sản phẩm
                </a>
                <span class="text-dark" style="font-size: 15px;">
                    Tạm tính đơn này: <span class="theme-text fw-bold ms-2" style="font-size: 20px;">${formatCurrency(data.totalAmount)}</span>
                </span>
            </div>
        `;

        cartContainer.innerHTML = htmlContent;
        finalTotalEl.textContent = formatCurrency(data.totalAmount);
        totalCountText.textContent = `Tổng cộng (${data.items.length} sản phẩm)`;
    }
});