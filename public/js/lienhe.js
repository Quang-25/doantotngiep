/* ========================================
   LIÊN HỆ - gửi phản hồi về Gmail của shop (không lưu database)
======================================== */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('contactForm');
    const messageBox = document.getElementById('contactMessage');
    const submitButton = document.getElementById('contactSubmit');
    const content = document.getElementById('noi_dung');
    const counter = document.getElementById('noiDungCount');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    if (!form) return;

    // Khách đã đăng nhập: điền sẵn họ tên, email.
    try {
        const user = JSON.parse(localStorage.getItem('user') || 'null');
        if (user) {
            if (user.HoTen && !form.ho_ten.value) form.ho_ten.value = user.HoTen;
            if (user.Email && !form.email.value) form.email.value = user.Email;
            if (user.SoDienThoai && !form.so_dien_thoai.value) form.so_dien_thoai.value = user.SoDienThoai;
        }
    } catch (e) { /* bỏ qua dữ liệu hỏng */ }

    content.addEventListener('input', () => { counter.textContent = content.value.length; });

    function showMessage(text, type) {
        messageBox.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type;
        alert.setAttribute('role', 'alert');
        alert.textContent = text;
        messageBox.appendChild(alert);
    }

    function clearErrors() {
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('[data-error]').forEach(el => { el.textContent = ''; });
    }

    function setError(field, text) {
        const input = form.elements[field];
        const feedback = form.querySelector('[data-error="' + field + '"]');
        if (input) input.classList.add('is-invalid');
        if (feedback) feedback.textContent = text;
    }

    function validate() {
        let ok = true;
        if (!form.ho_ten.value.trim()) { setError('ho_ten', 'Vui lòng nhập họ tên.'); ok = false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.value.trim())) { setError('email', 'Email không hợp lệ.'); ok = false; }
        const phone = form.so_dien_thoai.value.trim();
        if (phone && !/^[0-9+\s.\-]{8,15}$/.test(phone)) { setError('so_dien_thoai', 'Số điện thoại không hợp lệ.'); ok = false; }
        if (!form.chu_de.value) { setError('chu_de', 'Vui lòng chọn chủ đề.'); ok = false; }
        if (content.value.trim().length < 10) { setError('noi_dung', 'Nội dung cần ít nhất 10 ký tự.'); ok = false; }
        return ok;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearErrors();
        messageBox.innerHTML = '';
        if (!validate()) return;

        const label = submitButton.querySelector('span');
        submitButton.disabled = true;
        label.textContent = 'Đang gửi...';

        try {
            const response = await fetch('/lien-he', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    ho_ten: form.ho_ten.value.trim(),
                    email: form.email.value.trim(),
                    so_dien_thoai: form.so_dien_thoai.value.trim(),
                    chu_de: form.chu_de.value,
                    noi_dung: content.value.trim(),
                    website: form.website.value,
                }),
            });
            const result = await response.json().catch(() => ({}));

            if (response.ok && result.success) {
                showMessage(result.message, 'success');
                form.chu_de.value = '';
                content.value = '';
                counter.textContent = '0';
                return;
            }
            if (response.status === 422 && result.errors) {
                Object.entries(result.errors).forEach(([field, errors]) => setError(field, errors[0]));
                showMessage('Vui lòng kiểm tra lại thông tin.', 'danger');
                return;
            }
            if (response.status === 419) {
                showMessage('Phiên làm việc đã hết hạn, vui lòng tải lại trang rồi gửi lại.', 'warning');
                return;
            }
            showMessage(result.message || 'Chưa gửi được phản hồi, vui lòng thử lại sau.', 'danger');
        } catch (error) {
            showMessage('Không thể kết nối đến máy chủ, vui lòng thử lại.', 'danger');
        } finally {
            submitButton.disabled = false;
            label.textContent = 'Gửi phản hồi';
        }
    });
});
