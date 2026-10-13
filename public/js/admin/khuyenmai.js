const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
let myModal;

document.addEventListener('DOMContentLoaded', function () {
    myModal = new bootstrap.Modal(document.getElementById('modalKhuyenMai'));
    const form = document.getElementById('formKhuyenMai');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('km_id').value;
        const isUpdate = id !== '';
        const url = isUpdate ? `/admin/api/khuyen-mai/${id}` : '/admin/api/khuyen-mai';

        const data = {
            MaCode: document.getElementById('MaCode').value.toUpperCase(),
            MoTa: document.getElementById('MoTa').value,
            LoaiGiam: document.getElementById('LoaiGiam').value,
            GiaTriGiam: document.getElementById('GiaTriGiam').value,
            GiaTriDonToiThieu: document.getElementById('GiaTriDonToiThieu').value || 0,
            NgayBatDau: document.getElementById('NgayBatDau').value,
            NgayKetThuc: document.getElementById('NgayKetThuc').value,
            SoLuong: document.getElementById('SoLuong').value || null,
            TrangThai: document.getElementById('TrangThai').value
        };

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

    document.querySelectorAll('.btn-toggle-status').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            const newStatus = this.dataset.status;
            if (confirm(`Bạn muốn đổi trạng thái mã khuyến mãi này?`)) {
                try {
                    const response = await fetch(`/admin/api/khuyen-mai/trang-thai/${id}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ TrangThai: newStatus })
                    });
                    const result = await response.json();
                    if (result.success) window.location.reload();
                } catch (error) { alert('Lỗi kết nối Server!'); }
            }
        });
    });

    document.querySelectorAll('.btn-edit-km').forEach(btn => {
        btn.addEventListener('click', function () {
            form.reset();
            document.getElementById('km_id').value = this.dataset.id;
            document.getElementById('modalTitle').textContent = 'Cập Nhật Khuyến Mãi';
            
            document.getElementById('MaCode').value = this.dataset.macode;
            document.getElementById('MaCode').disabled = true;
            document.getElementById('MoTa').value = this.dataset.mota;
            document.getElementById('LoaiGiam').value = this.dataset.loaigiam;
            document.getElementById('GiaTriGiam').value = this.dataset.giatrigiam;
            document.getElementById('GiaTriDonToiThieu').value = this.dataset.toithieu;
            document.getElementById('SoLuong').value = this.dataset.soluong;
            document.getElementById('NgayBatDau').value = this.dataset.batdau;
            document.getElementById('NgayKetThuc').value = this.dataset.ketthuc;
            document.getElementById('TrangThai').value = this.dataset.trangthai;
            myModal.show();
        });
    });
});

function openModalAdd() {
    document.getElementById('formKhuyenMai').reset();
    document.getElementById('km_id').value = '';
    document.getElementById('modalTitle').textContent = 'Thêm Mã Khuyến Mãi Mới';
    document.getElementById('MaCode').disabled = false;
    myModal.show();
}