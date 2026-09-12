document.addEventListener('DOMContentLoaded', function () {
    loadHomeData();
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
function renderProducts(products) {
    const productList = document.getElementById('product-list');
    if (!productList) return;
    productList.innerHTML = '';
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
                </div>
            </div>
        `;
    });
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
    const leftArrow = document.querySelector('.product-arrow.left');
    const rightArrow = document.querySelector('.product-arrow.right');
    if (!productList || !leftArrow || !rightArrow) return;
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