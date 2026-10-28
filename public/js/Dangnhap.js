document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('loginForm');
    const messageBox = document.getElementById('loginMessage');
    const button = document.getElementById('loginButton');
    const buttonText = document.getElementById('loginButtonText');
    const buttonIcon = document.getElementById('loginButtonIcon');
    if (!form) return;

    // Trang cần đăng nhập gửi sang: ?het_phien=1&redirect=/don-hang
    const params = new URLSearchParams(window.location.search);
    const redirectParam = params.get('redirect') || '';
    // Chỉ cho quay về đường dẫn nội bộ (chặn chuyển hướng sang trang ngoài).
    const redirectSauDangNhap = /^\/(?!\/)/.test(redirectParam) ? redirectParam : '';
    if (params.get('het_phien') === '1') {
        // Phiên trên server đã hết: xóa thông tin cũ trong trình duyệt để giao diện không còn hiện "đã đăng nhập".
        localStorage.removeItem('user');
        setTimeout(() => showMessage('Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại để tiếp tục.', 'warning'), 0);
    }

    form.addEventListener('submit', async function (e) {
        // Ngăn chặn reload trang mặc định của form
        e.preventDefault();
        messageBox.innerHTML = '';
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        
        // Kiểm tra email
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showMessage('Email không hợp lệ.', 'danger');
            return;
        }
        // Kiểm tra mật khẩu
        if (password.length < 8) {
            showMessage('Mật khẩu phải có ít nhất 8 ký tự.', 'danger');
            return;
        }

        button.disabled = true;
        buttonText.textContent = 'Đang đăng nhập...';
        buttonIcon.className = 'spinner-border spinner-border-sm';

        try {
            const response = await fetch('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    // ĐỌC TOKEN BẢO MẬT TỪ THẺ META (TRÁNH LỖI 419)
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                // Cho phép trình duyệt nhận Session Cookie từ Laravel
                credentials: 'include',
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            });

            const result = await response.json();
            console.log('API login:', result);

            if (response.ok && result.success) {

                showMessage(
                    result.message || 'Đăng nhập thành công.',
                    'success'
                );

                // Lưu thông tin tài khoản vào LocalStorage 
                if (result.user) {
                    localStorage.setItem('user', JSON.stringify(result.user));
                }
                
                // KIỂM TRA QUYỀN VÀ CHUYỂN HƯỚNG
                setTimeout(() => {
                    if (redirectSauDangNhap && result.user && result.user.VaiTro !== 'Admin') {
                        window.location.href = redirectSauDangNhap;
                    } else if (result.user && result.user.VaiTro === 'Admin') {
                        // Nếu là Admin -> Vào Dashboard
                        window.location.href = '/admin/dashboard';
                    } else {
                        // Nếu là Khách Hàng -> Ra trang chủ
                        window.location.href = '/';
                    }
                }, 1000);

                return;
            }
            
            // Xử lý lỗi trả về từ server
            let message = result.message || 'Đăng nhập thất bại.';
            if (result.errors) {
                message = Object.values(result.errors)
                    .flat()
                    .join('<br>');
            }
            showMessage(message, 'danger');
        } catch (error) {
            console.error('Login error:', error);
            showMessage('Không thể kết nối đến máy chủ.', 'danger');
        } finally {
            // Khôi phục trạng thái nút bấm
            button.disabled = false;
            buttonText.textContent = 'Đăng nhập';
            buttonIcon.className = 'bi bi-arrow-right';

        }
    });

    function showMessage(message, type) {
        const icon = type === 'success'
            ? 'bi-check-circle-fill'
            : 'bi-exclamation-circle-fill';
        messageBox.innerHTML = `
            <div class="alert alert-${type}" role="alert">
                <i class="bi ${icon}"></i>
                <span>${message}</span>
            </div>
        `;
    }
});

function toggleLoginPassword() {
    const password = document.getElementById('password');
    const icon = document.getElementById('passwordIcon');
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}