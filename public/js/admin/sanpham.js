document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const myModal = new bootstrap.Modal(document.getElementById('modalSanPham'));
    const form = document.getElementById('formSanPham');

    // Khu thông số vợt chỉ hiện khi chọn danh mục Vợt cầu lông (ID_DanhMuc = 1)
    const DANH_MUC_VOT = '1';
    const selectDanhMuc = document.getElementById('ID_DanhMuc');
    const khuThongSoVot = document.getElementById('khuThongSoVot');
    const ghiChuThongSoVot = document.getElementById('ghiChuThongSoVot');
    const capNhatKhuThongSo = () => {
        const laVot = selectDanhMuc.value === DANH_MUC_VOT;
        khuThongSoVot.querySelectorAll('input, select').forEach(o => { o.disabled = !laVot; });
        khuThongSoVot.style.opacity = laVot ? '1' : '.6';
        ghiChuThongSoVot.className = 'd-block mb-3 ' + (laVot ? 'text-success' : 'text-muted');
        ghiChuThongSoVot.textContent = laVot
            ? 'Nhập thông số để khách xem ở trang chi tiết và chatbot tư vấn chính xác hơn.'
            : 'Chọn danh mục "Vợt Cầu Lông" để nhập thông số (sản phẩm khác không cần).';
    };
    capNhatKhuThongSo();
    selectDanhMuc.addEventListener('change', capNhatKhuThongSo);

    // Hình ảnh phụ: xem trước các link đã nhập
    const oAnhPhu = document.getElementById('HinhAnhPhu');
    const xemTruocAnhPhu = document.getElementById('xemTruocAnhPhu');
    const veXemTruocAnhPhu = () => {
        xemTruocAnhPhu.innerHTML = '';
        oAnhPhu.value.split('\n').map(s => s.trim()).filter(s => /^https?:\/\//i.test(s)).forEach((url, i) => {
            const img = document.createElement('img');
            img.src = url;
            img.title = 'Ảnh ' + (i + 1);
            img.style.cssText = 'width:64px;height:64px;object-fit:cover;border:1px solid #dee2e6;border-radius:6px;';
            img.onerror = () => { img.style.borderColor = '#dc3545'; img.alt = 'Lỗi ảnh'; };
            xemTruocAnhPhu.appendChild(img);
        });
    };
    oAnhPhu.addEventListener('input', veXemTruocAnhPhu);

    // 1. Mở form Thêm mới (Reset trắng dữ liệu)
    document.getElementById('btnOpenAdd').addEventListener('click', () => {
        form.reset();
        capNhatKhuThongSo();
        veXemTruocAnhPhu();
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
            document.getElementById('SizeCoSan').value = this.dataset.size || '';
            document.getElementById('HinhAnh').value = this.dataset.hinhanh; // Đổ Link Ảnh

            // Hình ảnh phụ
            let anhPhu = [];
            try { anhPhu = JSON.parse(this.dataset.anhphu || '[]'); } catch (e) { /* bỏ qua */ }
            oAnhPhu.value = anhPhu.join('\n');
            veXemTruocAnhPhu();

            // Thông số vợt
            let thongSo = {};
            try { thongSo = JSON.parse(this.dataset.thongso || '{}'); } catch (e) { /* bỏ qua */ }
            ['TrongLuong', 'ChuViTayCam', 'ChatLieu', 'DiemCanBang', 'DoCung', 'MucCangToiDa', 'TrinhDoPhuHop'].forEach(truong => {
                if (thongSo[truong]) document.getElementById(truong).value = String(thongSo[truong]).trim();
            });
            capNhatKhuThongSo();
            
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
                    
                    // Sản phẩm đã chuyển sang Ngừng kinh doanh (dữ liệu vẫn còn trong database)
                    // => bỏ dòng khỏi trang quản lý ngay.
                    const row = document.getElementById(`row-${spId}`);
                    alert(result.message);
                    row.remove();
                    // Trang hết dòng thì tải lại để lấy các sản phẩm của trang kế
                    if (!document.querySelector('tr[id^="row-"]')) {
                        window.location.reload();
                    }
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