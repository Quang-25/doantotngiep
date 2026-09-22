document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.querySelector('.customer-info-form');
    const submitBtn = document.querySelector('.btn-dat-hang');

    if(checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang xử lý...';
            submitBtn.disabled = true;

            const formData = new FormData(checkoutForm);

            fetch(checkoutForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        alert(data.message);
                        window.location.href = '/'; 
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