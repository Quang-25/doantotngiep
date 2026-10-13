document.addEventListener('DOMContentLoaded', function() {
    const formReset = document.getElementById('formResetPassword');
    const btnReset = document.getElementById('btnReset');
    
    if (formReset && btnReset) {
        formReset.addEventListener('submit', function(e) {
            e.preventDefault(); 
            
            const originalHTML = btnReset.innerHTML;
            btnReset.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Đang xử lý...';
            btnReset.disabled = true;

            const formData = new FormData(formReset);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            // Gọi vào /api/reset-password vì route đặt trong routes/api.php
            fetch('/api/reset-password', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = '/Dangnhap'; 
                } else {
                    // Xử lý hiển thị lỗi chi tiết nếu Laravel trả về lỗi validate (ví dụ mật khẩu không khớp)
                    let errorMsg = data.message || 'Dữ liệu không hợp lệ, vui lòng kiểm tra lại!';
                    if (data.errors) {
                        const firstErrorKey = Object.keys(data.errors)[0];
                        errorMsg = data.errors[firstErrorKey][0];
                    }
                    alert(errorMsg);
                    btnReset.innerHTML = originalHTML;
                    btnReset.disabled = false;
                }
            })
            .catch(error => {
                console.error('Lỗi API:', error);
                alert('Lỗi kết nối máy chủ. Vui lòng thử lại sau.');
                btnReset.innerHTML = originalHTML;
                btnReset.disabled = false;
            });
        });
    }
});