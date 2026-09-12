document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('registerForm');
    const messageBox = document.getElementById('registerMessage');

    const button = document.getElementById('registerButton');
    const buttonText = document.getElementById('registerButtonText');
    const buttonIcon = document.getElementById('registerButtonIcon');

    // Kiểm tra các phần tử cần thiết
    if (
        !form ||
        !messageBox ||
        !button ||
        !buttonText ||
        !buttonIcon
    ) {
        console.error('Không tìm thấy các phần tử của form đăng ký.');
        return;
    }


    // =========================================================
    // SUBMIT FORM
    // =========================================================

    form.addEventListener('submit', async function (event) {

        // Không cho trình duyệt submit form theo cách mặc định
        event.preventDefault();

        // Xóa thông báo cũ
        messageBox.innerHTML = '';


        // =========================================================
        // LẤY DỮ LIỆU FORM
        // =========================================================

        const name =
            document.getElementById('name').value.trim();

        const email =
            document.getElementById('email').value.trim();

        const phone =
            document.getElementById('SoDienThoai').value.trim();

        const address =
            document.getElementById('DiaChi').value.trim();

        const password =
            document.getElementById('password').value;

        const passwordConfirmation =
            document.getElementById('password_confirmation').value;


        // =========================================================
        // VALIDATION PHÍA FRONTEND
        // =========================================================

        // Họ tên
        if (name.length < 2) {

            showMessage(
                'Vui lòng nhập họ và tên.',
                'danger'
            );

            return;
        }


        // Email
       const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailRegex.test(email)) {

            showMessage(
                'Email không hợp lệ.',
                'danger'
            );

            return;
        }


        // Số điện thoại
        // Chấp nhận:
        // 0912345678
        // +84912345678
        const phoneRegex =
            /^(0|\+84)[0-9]{9,10}$/;

        if (!phoneRegex.test(phone)) {

            showMessage(
                'Số điện thoại không hợp lệ.',
                'danger'
            );

            return;
        }
        const addressRegex =
             /^[\p{L}\p{N}\s,.'-]{3,}$/u;
        if (!addressRegex.test(address)) {

            showMessage(
                'Địa chỉ không hợp lệ.',
                'danger'
            );

            return;
        }

        // Mật khẩu
        if (password.length < 8) {

            showMessage(
                'Mật khẩu phải có ít nhất 8 ký tự.',
                'danger'
            );

            return;
        }


        // Xác nhận mật khẩu
        if (password !== passwordConfirmation) {

            showMessage(
                'Mật khẩu nhập lại không khớp.',
                'danger'
            );

            return;
        }


        // =========================================================
        // BẮT ĐẦU GỬI API
        // =========================================================

        setLoading(true);


        try {

            const response = await fetch('/api/register', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },

                body: JSON.stringify({

                    name: name,

                    email: email,

                    SoDienThoai: phone,
                    DiaChi: address,
                    password: password,

                    password_confirmation:
                        passwordConfirmation
                })
            });


            // =====================================================
            // ĐỌC RESPONSE
            // =====================================================

            const responseText = await response.text();

            let result = {};

            try {
                result = responseText
                    ? JSON.parse(responseText)
                    : {};
            } catch (jsonError) {

                console.error(
                    'Server không trả về JSON:',
                    responseText
                );

                throw new Error(
                    'Server trả về dữ liệu không hợp lệ.'
                );
            }


            console.log('API response:', result);


            // =====================================================
            // ĐĂNG KÝ THÀNH CÔNG
            // =====================================================

            if (response.ok && result.success) {

                showMessage(
                    result.message ||
                    'Đăng ký tài khoản thành công.',
                    'success'
                );
                setTimeout(function () {
                    messageBox.innerHTML = '';
                }, 2000);


                // Xóa dữ liệu form
                form.reset();


                // Chuyển sang trang đăng nhập
                setTimeout(function () {

                    window.location.href = '/Dangnhap';

                }, 2000);


                return;
            }


            // =====================================================
            // ĐĂNG KÝ THẤT BẠI
            // =====================================================

            let message =
                result.message ||
                'Đăng ký tài khoản thất bại.';


            // Laravel Validation Error
            if (result.errors) {

                const errors =
                    Object.values(result.errors).flat();

                message =
                    errors.join('<br>');
            }


            showMessage(
                message,
                'danger'
            );

        } catch (error) {

            console.error(
                'Register error:',
                error
            );


            showMessage(
                error.message ||
                'Không thể kết nối đến máy chủ.',
                'danger'
            );

        } finally {

            setLoading(false);
        }

    });


    // =========================================================
    // HIỂN THỊ THÔNG BÁO
    // =========================================================

    function showMessage(message, type) {

        const icon =
            type === 'success'
                ? 'bi-check-circle-fill'
                : 'bi-exclamation-circle-fill';


        messageBox.innerHTML = `
            <div class="alert alert-${type}" role="alert">
                <i class="bi ${icon}"></i>
                <span>${message}</span>
            </div>
        `;
    }


    // =========================================================
    // TRẠNG THÁI BUTTON
    // =========================================================

    function setLoading(loading) {

        button.disabled = loading;


        if (loading) {

            buttonText.textContent =
                'Đang đăng ký...';

            buttonIcon.className =
                'spinner-border spinner-border-sm';

        } else {

            buttonText.textContent =
                'Đăng ký tài khoản';

            buttonIcon.className =
                'bi bi-arrow-right';
        }
    }

});


// =============================================================
// HIỆN / ẨN MẬT KHẨU
// =============================================================

function togglePassword(inputId, button) {

    const input =
        document.getElementById(inputId);

    const icon =
        button.querySelector('i');


    if (!input || !icon) {
        return;
    }


    if (input.type === 'password') {

        input.type = 'text';

        icon.classList.remove('bi-eye');

        icon.classList.add('bi-eye-slash');

    } else {

        input.type = 'password';

        icon.classList.remove('bi-eye-slash');

        icon.classList.add('bi-eye');
    }
}