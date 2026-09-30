document.addEventListener('DOMContentLoaded', function() {
    
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

            // Hiệu ứng Loading
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang xử lý...';
            submitBtn.disabled = true;

            // Đóng gói toàn bộ dữ liệu form (Bao gồm cả id_khuyenmai ẩn phía trên)
            const formData = new FormData(checkoutForm);

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
                        window.location.href = '/'; // chuyển về trang home
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