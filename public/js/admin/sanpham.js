document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const myModal = new bootstrap.Modal(document.getElementById('modalSanPham'));
    const form = document.getElementById('formSanPham');

    // 1. Mở form Thêm mới (Reset trắng dữ liệu)
    document.getElementById('btnOpenAdd').addEventListener('click', () => {
        form.reset();
        document.getElementById('sp_id').value = '';
        document.getElementById('modalTitle').textContent = 'Thêm Sản Phẩm Mới';
    });

    // 2. Mở form Sửa (Lấy dữ liệu từ Table đổ vào Input)
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function () {
            form.reset();
            document.getElementById('sp_id').value = this.dataset.id;
            document.getElementById('TenSanPham').value = this.dataset.ten;
            document.getElementById('ID_DanhMuc').value = this.dataset.danhmuc;
            document.getElementById('ID_ThuongHieu').value = this.dataset.thuonghieu;
            document.getElementById('GiaBan').value = this.dataset.giaban;
            document.getElementById('GiaKhuyenMai').value = this.dataset.giakm || '';
            document.getElementById('SoLuongTon').value = this.dataset.soluong;
            document.getElementById('MoTa').value = this.dataset.mota;
            document.getElementById('HinhAnh').value = this.dataset.hinhanh; // Đổ Link Ảnh
            
            document.getElementById('modalTitle').textContent = 'Cập Nhật Sản Phẩm #' + this.dataset.id;
            myModal.show();
        });
    });

    // 3. Gửi dữ liệu (Thêm hoặc Sửa) lên Server
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btnSave = document.getElementById('btnSave');
        btnSave.disabled = true;
        btnSave.innerHTML = 'Đang xử lý...';

        const sp_id = document.getElementById('sp_id').value;
        const formData = new FormData(form);
        
        // Cấu trúc RESTful: Nếu có ID thì gọi Link Sửa, Không có thì gọi Link Thêm
       const url = sp_id ? `/admin/api/san-pham/${sp_id}` : '/admin/api/san-pham';

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

    // 4. Gọi API Xóa (Gửi method DELETE)
   // 4. Gọi API Xóa (Gửi method DELETE - Thực chất là cập nhật trạng thái)
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async function () {
            const spId = this.dataset.id;
            
            if (!confirm(`Xác nhận chuyển sản phẩm #${spId} sang trạng thái Ngừng kinh doanh?`)) return;

            try {
                const response = await fetch(`/admin/api/san-pham/${spId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    
                    // ĐÃ FIX: Xóa lệnh row.remove(); đi và thay bằng code cập nhật giao diện
                    const row = document.getElementById(`row-${spId}`);
                    
                    // 1. Cập nhật cột Trạng thái (Cột số 7 tính từ 0) thành nhãn màu xám
                    row.cells[7].innerHTML = '<span class="badge bg-secondary">Ngừng Kinh Doanh</span>';
                    
                    // 2. Vô hiệu hóa nút Xóa vừa bấm để không bị bấm nhầm lần 2
                    this.disabled = true; 
                    
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