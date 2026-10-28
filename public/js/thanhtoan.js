document.addEventListener('DOMContentLoaded', function() {
    
    // ==========================================
    // BƯỚC BẢO MẬT: KIỂM TRA ĐĂNG NHẬP
    // ==========================================
    const user = JSON.parse(localStorage.getItem('user')); 
    if (!user || !user.ID_KhachHang) {
        alert('Vui lòng đăng nhập để tiến hành thanh toán!');
        window.location.href = '/Dangnhap'; // Chuyển hướng về trang đăng nhập
        return; // Dừng toàn bộ script nếu chưa đăng nhập
    }

    // ==========================================
    // PHẦN 1: XỬ LÝ POPUP CHỌN MÃ KHUYẾN MÃI
    // ==========================================
    const tongTienInput = document.querySelector('input[name="tong_tien"]');
    const tongTienGoc = tongTienInput ? parseInt(tongTienInput.value) : 0; 
    
    const elTongThanhTien = document.getElementById('hienThiTongTien'); 
    const modal = document.getElementById('modalVoucher');
    const btnMoModal = document.getElementById('btnMoPopupVoucher');
    const btnDongModal = document.getElementById('dongModal');
    
    const khuVucVoucherDaChon = document.getElementById('voucherDaChon');
    const textMaCode = document.getElementById('textMaCode');
    const textTienGiam = document.getElementById('textTienGiam');
    const inputHiddenIdKhuyenMai = document.getElementById('inputHiddenIdKhuyenMai');
    const btnHuyVoucher = document.getElementById('btnHuyVoucher');

    function formatVND(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
    }

    if (btnMoModal && modal) {
        btnMoModal.addEventListener('click', () => modal.style.display = 'block');
        btnDongModal.addEventListener('click', () => modal.style.display = 'none');
        window.addEventListener('click', (e) => { if(e.target == modal) modal.style.display = 'none'; });
    }

    document.querySelectorAll('.btnApDungVoucher').forEach(button => {
        button.addEventListener('click', function() {
            const idKm = this.getAttribute('data-id');
            const maCode = this.getAttribute('data-code');
            const loaiGiam = this.getAttribute('data-loai');
            const giaTriGiam = parseInt(this.getAttribute('data-giatri'));

            let tienGiam = (loaiGiam === 'PhanTram') ? (tongTienGoc * giaTriGiam) / 100 : giaTriGiam;
            if(tienGiam > tongTienGoc) tienGiam = tongTienGoc; 

            if(elTongThanhTien) elTongThanhTien.innerText = formatVND(tongTienGoc - tienGiam);
            
            if(textMaCode) textMaCode.innerText = 'Đã áp mã: ' + maCode;
            if(textTienGiam) textTienGiam.innerText = '- ' + formatVND(tienGiam);
            if(khuVucVoucherDaChon) khuVucVoucherDaChon.style.display = 'block';
            
            if(inputHiddenIdKhuyenMai) inputHiddenIdKhuyenMai.value = idKm;
            
            if(btnMoModal) btnMoModal.style.display = 'none';
            if(modal) modal.style.display = 'none';
        });
    });

    if (btnHuyVoucher) {
        btnHuyVoucher.addEventListener('click', function() {
            if(elTongThanhTien) elTongThanhTien.innerText = formatVND(tongTienGoc);
            if(inputHiddenIdKhuyenMai) inputHiddenIdKhuyenMai.value = '';
            if(khuVucVoucherDaChon) khuVucVoucherDaChon.style.display = 'none';
            if(btnMoModal) btnMoModal.style.display = 'inline-block';
        });
    }

    // ==========================================
    // PHẦN 2: XỬ LÝ SUBMIT ĐẶT HÀNG (AJAX)
    // ==========================================
    const checkoutForm = document.querySelector('.customer-info-form');
    const submitBtn = document.querySelector('.btn-dat-hang');

    if (checkoutForm && submitBtn) {
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Kiểm tra lại user một lần nữa đề phòng khách hàng mở nhiều tab và vô tình đăng xuất
            const currentUser = JSON.parse(localStorage.getItem('user'));
            if (!currentUser || !currentUser.ID_KhachHang) {
                alert('Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại!');
                window.location.href = '/Dangnhap'; // Chuyển hướng về trang đăng nhập
                return;
            }

            // Hiệu ứng Loading
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang xử lý...';
            submitBtn.disabled = true;

            // Đóng gói toàn bộ dữ liệu form (Bao gồm cả id_khuyenmai ẩn phía trên)
            const formData = new FormData(checkoutForm);

            // BỔ SUNG QUAN TRỌNG: Gắn ID_KhachHang vào gói dữ liệu gửi lên Backend
            formData.append('ID_KhachHang', currentUser.ID_KhachHang);

            fetch(checkoutForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                    // Lưu ý: Không set 'Content-Type' khi dùng FormData, trình duyệt sẽ tự động set boundary cho multipart/form-data
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.redirect_url) {
                        // Chuyển hướng sang VNPay
                        window.location.href = data.redirect_url;
                    } else {
                        // Đặt hàng COD thành công
                        alert(data.message);
                        window.location.href = '/don-hang'; // chuyển tới trang Đơn mua để theo dõi đơn
                    }
                } else {
                    alert(data.message || 'Có lỗi xảy ra, vui lòng kiểm tra lại!');
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Lỗi:', error);
                alert('Lỗi kết nối hệ thống. Vui lòng thử lại sau!');
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            });
        });
    }
});