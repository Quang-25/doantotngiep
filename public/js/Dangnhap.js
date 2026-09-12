document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('loginForm');
    const messageBox = document.getElementById('loginMessage');
    const button = document.getElementById('loginButton');
    const buttonText = document.getElementById('loginButtonText');
    const buttonIcon = document.getElementById('loginButtonIcon');
    if (!form) return;
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
        // Kiểm tra mật khẩu (Lưu ý: Nếu test với tài khoản cũ pass '123' thì phải tạm đổi số 8 thành 3 nhé)
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
                    'Accept': 'application/json'
                },
                // Cho phép trình duyệt nhận Session Cookie từ Laravel
                credentials: 'same-origin',
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

                // FIX QUAN TRỌNG: Lưu thông tin tài khoản vào LocalStorage 
                // để file header.js có thể lấy ra hiển thị tên người dùng
                if (result.user) {
                    localStorage.setItem('user', JSON.stringify(result.user));
                }
                // Chuyển hướng về trang chủ
                setTimeout(() => {
                    window.location.href = '/';
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