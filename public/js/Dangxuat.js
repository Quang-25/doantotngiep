document.addEventListener('DOMContentLoaded', () => {
    const logoutButton = document.getElementById('logoutButton');
    const messageBox = document.getElementById('logoutMessage'); // Nếu bạn có thẻ này ở đâu đó

    if (!logoutButton) return;

    logoutButton.addEventListener('click', async function (e) {
        e.preventDefault();
        // 1. Lấy mã CSRF Token từ thẻ meta đã cài đặt ở header.blade.php
        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';
        try {   // 2. Giao tiếp với Backend
            const response = await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken // Bắt buộc để Laravel không báo lỗi 419
                },
                credentials: 'same-origin' // Bắt buộc để Laravel nhận diện được Session
            });
            const result = await response.json();
            // Xử lý hiển thị thông báo nếu API trả về thành công
            if (response.ok && result.success) {
                if (messageBox) {
                    messageBox.innerHTML = `<div class="alert alert-success">${result.message || 'Đăng xuất thành công.'}</div>`;
                }
            } else {
                console.warn('Server báo lỗi hoặc session đã hết hạn:', result.message);
            }
        } catch (error) {
            console.error('Lỗi khi gọi API đăng xuất:', error);
            if (messageBox) {
                messageBox.innerHTML = `<div class="alert alert-danger">Đã xảy ra lỗi kết nối. Vẫn tiến hành đăng xuất hệ thống.</div>`;
            }
        } finally {
            // 3. FIX QUAN TRỌNG: Đặt ở finally để DÙ CÓ LỖI MẠNG HAY LỖI SERVER, 
            // phía Client (trình duyệt) vẫn xoá sạch dữ liệu và quay về trang Đăng Nhập
            localStorage.removeItem('user');

            setTimeout(() => {
                window.location.href = '/Dangnhap'; 
                // Lưu ý: Có thể đổi thành '/' nếu bạn muốn đăng xuất xong về thẳng Trang chủ
            }, 800);
        }
    });
});