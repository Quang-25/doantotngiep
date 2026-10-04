document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const myModal = new bootstrap.Modal(document.getElementById('modalKhachHang'));
    const form = document.getElementById('formKhachHang');

    // 1. Mở form Sửa
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function () {
            form.reset();
            document.getElementById('kh_id').value = this.dataset.id;
            document.getElementById('HoTen').value = this.dataset.hoten;
            document.getElementById('SoDienThoai').value = this.dataset.sdt;
            document.getElementById('DiaChi').value = this.dataset.diachi;
            
            document.getElementById('modalTitle').textContent = 'Cập Nhật Khách Hàng #' + this.dataset.id;
            myModal.show();
        });
    });

    // 2. Gửi dữ liệu Sửa lên Server
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btnSave = document.getElementById('btnSave');
        btnSave.disabled = true;
        btnSave.innerHTML = 'Đang xử lý...';

        const kh_id = document.getElementById('kh_id').value;
        const formData = new FormData(form);
        const url = `/admin/api/khach-hang/${kh_id}`;

        try {
            const response = await fetch(url, {
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData
            });
            const result = await response.json();
            
            if (response.ok && result.success) {
                alert(result.message);
                window.location.reload(); 
            } else {
                alert('Lỗi: ' + (result.message || 'Dữ liệu không hợp lệ'));
            }
        } catch (error) {
            console.error(error);
            alert('Lỗi kết nối tới Server!');
        } finally {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Lưu Dữ Liệu';
        }
    });

    // 3. Xóa vĩnh viễn khách hàng
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async function () {
            const khId = this.dataset.id;
            
            if (!confirm(`Xác nhận XÓA VĨNH VIỄN khách hàng #${khId}? Hành động này không thể hoàn tác!`)) return;

            try {
                const response = await fetch(`/admin/api/khach-hang/${khId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    const row = document.getElementById(`row-${khId}`);
                    row.remove(); // Xóa hẳn dòng khỏi bảng
                    alert(result.message);
                } else {
                    alert('Thất bại: ' + result.message);
                }
            } catch (error) {
                console.error(error);
                alert('Lỗi kết nối Server');
            }
        });
    });
});