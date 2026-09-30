// public/js/chitietsanpham.js

document.addEventListener('DOMContentLoaded', function() {
    
    // ==========================================
    // 1. CHỨC NĂNG ĐỔI ẢNH VÀ TĂNG GIẢM SỐ LƯỢNG
    // ==========================================
    window.changeImage = function(element, src) {
        document.getElementById('mainImage').src = src;
        
        // Xóa class active ở tất cả ảnh nhỏ
        document.querySelectorAll('.gallery-thumb').forEach(img => {
            img.classList.remove('active');
        });
        
        // Sáng lên ở ảnh vừa click
        element.classList.add('active');
    };

    window.updateQty = function(change) {
        let input = document.getElementById('inputQty');
        let max = parseInt(input.getAttribute('max'));
        let current = parseInt(input.value);
        let newVal = current + change;
        
        if(newVal >= 1 && newVal <= max) {
            input.value = newVal;
        }
    };

    // ==========================================
    // 2. CHỨC NĂNG THÊM VÀO GIỎ HÀNG & MUA NGAY
    // ==========================================
    const btnThemGio = document.getElementById('btn-them-gio');
    const btnMuaNgay = document.getElementById('btn-mua-ngay');
    const inputSanPham = document.querySelector('input[name="id_sanpham"]');
    const inputSoLuong = document.getElementById('inputQty');

    // Hàm tạo mã phiên ảo cho người dùng chưa đăng nhập
    function layMaPhien() { 
        let maPhien = localStorage.getItem('MaPhien'); 
        if (!maPhien) { 
            maPhien = crypto.randomUUID(); 
            localStorage.setItem('MaPhien', maPhien); 
        } 
        return maPhien; 
    }

    // Hàm xử lý chung khi bấm gọi API
    async function xuLyDatHang(isBuyNow) {
        // Xác định nút nào vừa được bấm
        const button = isBuyNow ? btnMuaNgay : btnThemGio;
        if (!button || button.disabled) return;

        // Lấy dữ liệu
        const idSanPham = inputSanPham.value;
        const soLuong = parseInt(inputSoLuong.value) || 1;

        // Đổi trạng thái nút thành đang loading
        const originalHTML = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
        button.disabled = true;

        try {
            // Chuẩn bị payload dữ liệu 
            const user = JSON.parse(localStorage.getItem('user')); 
            const body = { ID_SanPham: idSanPham, SoLuong: soLuong }; 

            if (user && user.ID_KhachHang) { 
                body.ID_KhachHang = user.ID_KhachHang; 
            } else { 
                body.MaPhien = layMaPhien(); 
            }

            // Gọi API Restful
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
                // Cập nhật lại số lượng giỏ hàng trên thanh Header
                if (typeof window.laySoLuongGioHangToanCuc === 'function') {
                    window.laySoLuongGioHangToanCuc();
                }

                if (isBuyNow) {
                    // Nếu là Mua ngay -> Nhảy sang trang thanh toán
                    window.location.href = '/thanhtoan';
                } else {
                    // Nếu là Thêm giỏ hàng -> Báo thành công
                    alert(data.message || 'Đã thêm sản phẩm vào giỏ hàng thành công!');
                    
                    // Khôi phục nút bấm
                    button.innerHTML = originalHTML;
                    button.disabled = false;
                }
            } else { 
                alert(data.message || 'Không thể thêm sản phẩm.'); 
                button.innerHTML = originalHTML;
                button.disabled = false;
            }

        } catch (error) { 
            console.error('Lỗi gọi API:', error); 
            alert('Lỗi kết nối! Không thể thêm sản phẩm vào giỏ hàng.'); 
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    }

    // Gắn sự kiện lắng nghe (Click) cho 2 nút
    if (btnThemGio) {
        btnThemGio.addEventListener('click', () => xuLyDatHang(false));
    }

    if (btnMuaNgay) {
        btnMuaNgay.addEventListener('click', () => xuLyDatHang(true));
    }
});