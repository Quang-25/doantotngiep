document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const myModal = new bootstrap.Modal(document.getElementById('modalDonHang'));
    const form = document.getElementById('formDonHang');
    const tbodyItems = document.getElementById('order-items-container');

    // 1. Mở Modal Xem chi tiết Đơn hàng
    document.querySelectorAll('.btn-view-order').forEach(btn => {
        btn.addEventListener('click', async function () {
            const dhId = this.dataset.id;
            
            try {
                const response = await fetch(`/admin/api/don-hang/${dhId}`);
                const result = await response.json();

                if (response.ok && result.success) {
                    const dh = result.donHang;
                    const items = result.chiTiet;

                    document.getElementById('dh_id').value = dh.ID_DonHang;
                    document.getElementById('md_MaDon').textContent = '#' + dh.ID_DonHang;
                    document.getElementById('md_NguoiNhan').textContent = dh.TenNguoiNhan;
                    document.getElementById('md_SDT').textContent = dh.SoDienThoaiNhan;
                    document.getElementById('md_DiaChi').textContent = dh.DiaChiGiaoHang;
                    document.getElementById('md_GhiChu').textContent = dh.GhiChu || 'Không có ghi chú';
                    document.getElementById('TrangThaiDon').value = dh.TrangThaiDon;

                    // Khóa Select nếu đơn đã Hoàn thành hoặc Hủy
                    const isLocked = (dh.TrangThaiDon === 'HoanThanh' || dh.TrangThaiDon === 'DaHuy');
                    document.getElementById('TrangThaiDon').disabled = isLocked;
                    document.getElementById('btnSaveStatus').style.display = isLocked ? 'none' : 'block';

                    // Load Sản phẩm
                    tbodyItems.innerHTML = '';
                    items.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td style="width: 60px;">
                                <img src="${item.HinhAnh || 'https://via.placeholder.com/50'}" class="order-product-img">
                            </td>
                            <td class="align-middle fw-bold">${item.TenSanPham}</td>
                            <td class="align-middle text-center">x${item.SoLuong}</td>
                            <td class="align-middle text-end text-danger fw-bold">
                                ${new Intl.NumberFormat('vi-VN').format(item.GiaMua)}đ
                            </td>
                        `;
                        tbodyItems.appendChild(tr);
                    });

                    // Tính tổng tiền
                    const trTotal = document.createElement('tr');
                    trTotal.classList.add('border-top');
                    trTotal.innerHTML = `
                        <td colspan="3" class="text-end fw-bold pt-3">Khuyến mãi giảm: <br> Tổng thanh toán:</td>
                        <td class="text-end fw-bold pt-3 text-danger fs-5">
                            -${new Intl.NumberFormat('vi-VN').format(dh.TienGiam)}đ <br>
                            ${new Intl.NumberFormat('vi-VN').format(dh.ThanhTien)}đ
                        </td>
                    `;
                    tbodyItems.appendChild(trTotal);

                    myModal.show();
                } else {
                    alert('Lỗi: ' + result.message);
                }
            } catch (error) {
                console.error(error);
                alert('Lỗi kết nối khi lấy chi tiết đơn hàng!');
            }
        });
    });

   // 2. Chức năng CẬP NHẬT NHANH (Duyệt / Hoàn thành)
    document.querySelectorAll('.btn-update-status').forEach(btn => {
        btn.addEventListener('click', async function () {
            const dhId = this.dataset.id;
            const newStatus = this.dataset.status; // Lấy trạng thái muốn chuyển tới
            
            // Tùy chỉnh câu hỏi xác nhận theo trạng thái
            let confirmMsg = '';
            if (newStatus === 'DangGiao') {
                confirmMsg = `Xác nhận DUYỆT đơn hàng #${dhId} và chuyển cho bên giao hàng?`;
            } else if (newStatus === 'HoanThanh') {
                confirmMsg = `Xác nhận đơn hàng #${dhId} đã GIAO THÀNH CÔNG cho khách?`;
            }

            if (!confirm(confirmMsg)) return;

            // Đổi text thành icon loading để tránh bấm 2 lần
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const response = await fetch(`/admin/api/don-hang/${dhId}`, {
                    method: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken, 
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ TrangThaiDon: newStatus })
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    alert('Cập nhật trạng thái thành công!');
                    window.location.reload(); 
                } else {
                    alert('Lỗi: ' + result.message);
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error(error);
                alert('Lỗi kết nối tới Server!');
                this.disabled = false;
                this.innerHTML = originalHtml;
            }
        });
    });
    // 3. Lưu trạng thái mới từ trong Modal
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btnSave = document.getElementById('btnSaveStatus');
        btnSave.disabled = true;
        btnSave.innerHTML = 'Đang lưu...';

        const dh_id = document.getElementById('dh_id').value;
        const trangThai = document.getElementById('TrangThaiDon').value;

        try {
            const response = await fetch(`/admin/api/don-hang/${dh_id}`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': csrfToken, 
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ TrangThaiDon: trangThai })
            });
            const result = await response.json();
            
            if (response.ok && result.success) {
                alert(result.message);
                window.location.reload(); 
            } else {
                alert('Lỗi: ' + result.message);
            }
        } catch (error) {
            console.error(error);
            alert('Lỗi kết nối tới Server!');
        } finally {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Lưu Trạng Thái';
        }
    });
});