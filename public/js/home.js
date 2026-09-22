document.addEventListener('DOMContentLoaded', function () {
    loadHomeData();
    initTabs();
});

async function loadHomeData() {
    const productList = document.getElementById('product-list');
    const productSale = document.getElementById('product-sale');
    try {
        const response = await fetch('/api/home');

        if (!response.ok) {
            throw new Error('API trả về lỗi ' + response.status);
        }
        const data = await response.json();
        renderProducts(data.sanPhamBanChay);
        renderSaleProducts(data.sanPhamSale);
        initProductSlider();
    } catch (error) {
        console.error('Lỗi tải dữ liệu Home:', error);

        if (productList) {
            productList.innerHTML = `
                <p class="text-danger">
                    Không thể tải sản phẩm.
                </p>
            `;
        }
        if (productSale) {
            productSale.innerHTML = `
                <p class="text-danger">
                    Không thể tải sản phẩm sale.
                </p>
            `;
        }
    }
}

async function loadTabProducts(categoryId) {
    const productList = document.getElementById('product-list');
    try {
        let url = '/api/sanpham';
        if (categoryId) url += `?danhmuc=${categoryId}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            renderProducts(data.data);
            initProductSlider();
        }
    } catch (error) {
        console.error(error);
        if (productList) {
            productList.innerHTML = `<p class="text-danger">Không thể tải sản phẩm.</p>`;
        }
    }
}

function initTabs() {
    const tabs = document.querySelectorAll('.product-tabs a');
    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            const categoryId = tab.getAttribute('data-id') || '';
            loadTabProducts(categoryId);
        });
    });
}

function renderProducts(products) {
    const productList = document.getElementById('product-list');
    if (!productList) return;
    productList.innerHTML = '';
    
    if (products.length === 0) {
        productList.innerHTML = '<p class="text-center w-100 py-4">Chưa có sản phẩm nào trong danh mục này.</p>';
        return;
    }

    products.forEach(function (sp) {
        productList.innerHTML += `
            <div class="product-card">
                <div class="product-image">
                    <img src="${sp.HinhAnh}" alt="${sp.TenSanPham}">
                </div>
                <div class="product-info">
                    <h3>${sp.TenSanPham}</h3>

                    <div class="product-price">
                        ${formatPrice(sp.GiaBan)} đ
                    </div>
                    
                    <button class="btn btn-primary w-100 btn-them-gio mt-3" data-id="${sp.ID_SanPham}">
                        <i class="bi bi-cart-plus"></i> Thêm giỏ hàng
                    </button>
                </div>
            </div>
        `;
    });
    
    ganSuKienThemGio();
}

function renderSaleProducts(products) {
    const productSale = document.getElementById('product-sale');
    if (!productSale) return;
    productSale.innerHTML = '';
    products.forEach(function (sp) {
        productSale.innerHTML += `
            <div class="sale-item">
                <img src="${sp.HinhAnh}" alt="${sp.TenSanPham}">
                <div class="sale-info">
                    <h3>${sp.TenSanPham}</h3>
                    <div class="old-price">
                        ${formatPrice(sp.GiaBan)} đ
                    </div>
                    <div class="sale-price">
                        ${formatPrice(sp.GiaKhuyenMai)} đ
                    </div>
                </div>
            </div>
        `;
    });
}

function formatPrice(price) {
    return Number(price).toLocaleString('vi-VN');
}

function initProductSlider() {
    const productList = document.querySelector('.product-list');
    let leftArrow = document.querySelector('.product-arrow.left');
    let rightArrow = document.querySelector('.product-arrow.right');
    
    if (!productList || !leftArrow || !rightArrow) return;
    
    const newRight = rightArrow.cloneNode(true);
    rightArrow.parentNode.replaceChild(newRight, rightArrow);
    rightArrow = newRight;

    const newLeft = leftArrow.cloneNode(true);
    leftArrow.parentNode.replaceChild(newLeft, leftArrow);
    leftArrow = newLeft;

    function getScrollAmount() {
        const card = productList.querySelector('.product-card');
        if (!card) return 0;
        const styles = getComputedStyle(productList);
        const gap = parseFloat(
            styles.columnGap || styles.gap
        ) || 0;
        return card.offsetWidth + gap;
    }
    
    function updateArrows() {
        const maxScroll =
            productList.scrollWidth - productList.clientWidth;
        leftArrow.classList.toggle(
            'disabled',
            productList.scrollLeft <= 5
        );
        rightArrow.classList.toggle(
            'disabled',
            productList.scrollLeft >= maxScroll - 5
        );
    }
    
    rightArrow.addEventListener('click', function () {
        productList.scrollBy({
            left: getScrollAmount(),
            behavior: 'smooth'
        });
    });
    
    leftArrow.addEventListener('click', function () {
        productList.scrollBy({
            left: -getScrollAmount(),
            behavior: 'smooth'
        });
    });
    
    productList.addEventListener('scroll', updateArrows);
    window.addEventListener('resize', updateArrows);
    updateArrows();
}

function ganSuKienThemGio() {
    const buttons = document.querySelectorAll('.btn-them-gio');
    buttons.forEach(button => {
        button.addEventListener('click', () => {
            themVaoGio(button.dataset.id, button);
        });
    });
}

async function themVaoGio(idSanPham, button) {
    try {
        const user = JSON.parse(localStorage.getItem('user'));
        const body = { ID_SanPham: idSanPham, SoLuong: 1 };

        if (user && user.ID_KhachHang) {
            body.ID_KhachHang = user.ID_KhachHang;
        } else {
            let maPhien = localStorage.getItem('MaPhien');
            if (!maPhien) {
                maPhien = crypto.randomUUID ? crypto.randomUUID() : 'guest-' + new Date().getTime();
                localStorage.setItem('MaPhien', maPhien);
            }
            body.MaPhien = maPhien;
        }

        button.disabled = true;

        const res = await fetch('/api/gio-hang/them', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        });

        const data = await res.json();

        if (data.success) {
            if (typeof window.laySoLuongGioHangToanCuc === 'function') {
                window.laySoLuongGioHangToanCuc();
            }
            alert(data.message);
        } else {
            alert(data.message || 'Không thể thêm sản phẩm.');
        }
    } catch (error) {
        console.error(error);
        alert('Không thể thêm sản phẩm vào giỏ hàng.');
    } finally {
        button.disabled = false;
    }
}