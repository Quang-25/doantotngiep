const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let myModal;

document.addEventListener('DOMContentLoaded', function () {
    myModal = new bootstrap.Modal(document.getElementById('modalTaiKhoan'));
    const form = document.getElementById('formTaiKhoan');

    // 1. Submit form (Thêm hoặc Sửa)
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('tk_id').value;
        const isUpdate = id !== '';
        const url = isUpdate ? `/admin/api/tai-khoan/${id}` : '/admin/api/tai-khoan';

        const data = {
            Email: document.getElementById('Email').value,
            MatKhau: document.getElementById('MatKhau').value,
            VaiTro: document.getElementById('VaiTro').value,
            TrangThai: document.getElementById('TrangThai').value
        };

        if (!isUpdate && !data.MatKhau) {
            alert('Vui lòng nhập mật khẩu cho tài khoản mới!');
            return;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            
            if (response.ok && result.success) {
                alert(result.message);
                window.location.reload();
            } else {
                alert(result.message);
            }
        } catch (error) {
            alert('Lỗi kết nối Server!');
        }
    });

    // 2. Khóa / Mở Khóa nhanh ngoài bảng
    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            const newStatus = this.dataset.status;
            const actionText = newStatus === 'Khoa' ? 'Khóa (Ban)' : 'Mở khóa';

            if (confirm(`Bạn chắc chắn muốn ${actionText} tài khoản này?`)) {
                try {
                    const response = await fetch(`/admin/api/tai-khoan/khoa/${id}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ TrangThai: newStatus })
                    });
                    const result = await response.json();
                    if (result.success) {
                        window.location.reload();
                    } else {
                        alert(result.message);
                    }
                } catch (error) {
                    alert('Lỗi kết nối Server!');
                }
            }
        });
    });

    // 3. Sự kiện mở Modal Sửa Tài Khoản (Thay thế hoàn toàn cho hàm openModalEdit cũ)
    document.querySelectorAll('.btn-edit-account').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('formTaiKhoan').reset();
            
            // Lấy dữ liệu từ các thuộc tính data- của nút bấm
            document.getElementById('tk_id').value = this.dataset.id;
            document.getElementById('modalTitle').textContent = 'Cập Nhật & Phân Quyền';
            
            document.getElementById('Email').value = this.dataset.email;
            document.getElementById('Email').disabled = true; // Không cho đổi Email
            document.getElementById('VaiTro').value = this.dataset.vaitro;
            document.getElementById('TrangThai').value = this.dataset.trangthai;
            
            document.getElementById('pass-hint').textContent = '(Bỏ trống nếu không muốn đổi pass)';
            myModal.show();
        });
    });
});

// 4. Mở Modal Thêm (Giữ nguyên vì nó không truyền tham số phức tạp)
function openModalAdd() {
    document.getElementById('formTaiKhoan').reset();
    document.getElementById('tk_id').value = '';
    document.getElementById('modalTitle').textContent = 'Thêm Tài Khoản Mới';
    document.getElementById('Email').disabled = false;
    document.getElementById('pass-hint').textContent = '(Bắt buộc nhập)';
    myModal.show();
}