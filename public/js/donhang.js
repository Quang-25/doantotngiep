/* ========================================
   ĐƠN MUA - theo dõi trạng thái đơn hàng (giống mục "Đơn mua" của Shopee)
======================================== */
document.addEventListener('DOMContentLoaded', function () {
    const orderList = document.getElementById('order-list');
    const tabs = document.querySelectorAll('.order-tab');
    const detailModal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    const detailBody = document.getElementById('orderDetailBody');
    const detailTitle = document.getElementById('orderDetailTitle');
    const cancelError = document.getElementById('cancelError');
    const otherReason = document.getElementById('cancelOtherReason');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let currentStatus = '';
    let cancellingOrderId = null;

    const stepIcons = {
        DatHang: 'bi-receipt',
        ChoXacNhan: 'bi-hourglass-split',
        DangGiao: 'bi-truck',
        HoanThanh: 'bi-box-seam',
        DaHuy: 'bi-x-lg',
        TraHang: 'bi-arrow-return-left',
    };

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    function formatMoney(amount) {
        return Number(amount || 0).toLocaleString('vi-VN') + 'đ';
    }

    function formatDate(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        return isNaN(date) ? escapeHtml(value) : date.toLocaleString('vi-VN', {
            hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric'
        });
    }

    // Thông tin khách lưu lúc đăng nhập (giống giỏ hàng).
    let currentUser = null;
    try { currentUser = JSON.parse(localStorage.getItem('user') || 'null'); } catch (e) { currentUser = null; }
    const loginUrl = '/Dangnhap?redirect=' + encodeURIComponent('/don-hang');
    if (!currentUser || !currentUser.ID_KhachHang) {
        window.location.href = loginUrl;
        return;
    }
    const authHeaders = currentUser.MaXacThuc
        ? { 'X-Khach-Hang-Id': String(currentUser.ID_KhachHang), 'X-Khach-Hang-Token': currentUser.MaXacThuc }
        : {};

    async function callApi(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, ...authHeaders, ...(options.headers || {}) },
        });
        if (response.status === 401) {
            // Không xác thực được (đăng nhập từ bản cũ, chưa có mã) => đăng nhập lại một lần.
            localStorage.removeItem('user');
            window.location.href = '/Dangnhap?het_phien=1&redirect=' + encodeURIComponent('/don-hang');
            throw new Error('Chưa đăng nhập');
        }
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Có lỗi xảy ra, vui lòng thử lại.');
        }
        return data;
    }

    // ---------- Danh sách đơn ----------

    function renderItems(items) {
        return items.map(item => `
            <div class="order-item">
                <img src="${escapeHtml(item.HinhAnh)}" alt="${escapeHtml(item.TenSanPham)}" onerror="this.src='/images/no-image.jpg'">
                <div>
                    <a href="/san-pham/${Number(item.ID_SanPham)}" class="order-item-name text-dark text-decoration-none">${escapeHtml(item.TenSanPham)}</a>
                    <div class="text-muted small">x${Number(item.SoLuong)}</div>
                </div>
                <div class="order-item-price">${formatMoney(item.GiaMua)}</div>
            </div>`).join('');
    }

    function renderOrder(order) {
        const buttons = [
            `<button type="button" class="btn btn-outline-primary btn-sm" data-action="detail" data-id="${order.ID_DonHang}">Xem chi tiết</button>`,
        ];
        if (order.CoTheHuy) {
            buttons.push(`<button type="button" class="btn btn-outline-danger btn-sm" data-action="cancel" data-id="${order.ID_DonHang}">Hủy đơn</button>`);
        }
        if (order.CoTheTraHang) {
            buttons.push(`<button type="button" class="btn btn-outline-warning btn-sm" data-action="return" data-id="${order.ID_DonHang}" data-deadline="${escapeHtml(order.HanTraHang || '')}">Trả hàng</button>`);
        }
        if (order.TrangThai === 'HoanThanh' || order.TrangThai === 'DaHuy') {
            buttons.push(`<button type="button" class="btn btn-primary btn-sm" data-action="rebuy" data-id="${order.ID_DonHang}">Mua lại</button>`);
        }

        return `
            <div class="order-card" data-order='${escapeHtml(JSON.stringify(order.SanPhams.map(i => ({ id: i.ID_SanPham, qty: i.SoLuong }))))}'>
                <div class="order-card-header">
                    <div><b>Đơn #${order.ID_DonHang}</b> <span class="text-muted ms-2">${formatDate(order.NgayDat)}</span></div>
                    <div class="order-status status-${escapeHtml(order.TrangThai)}">${escapeHtml(order.TenTrangThai)}</div>
                </div>
                ${renderItems(order.SanPhams)}
                <div class="order-card-footer">
                    <div class="d-flex gap-2 flex-wrap">${buttons.join('')}</div>
                    <div>Thành tiền: <span class="order-total">${formatMoney(order.ThanhTien)}</span></div>
                </div>
            </div>`;
    }

    async function loadOrders() {
        orderList.innerHTML = `<div class="text-center p-5 text-muted"><div class="spinner-border text-primary mb-3"></div><h6>Đang tải đơn hàng...</h6></div>`;
        try {
            const params = currentStatus ? '?trang_thai=' + encodeURIComponent(currentStatus) : '';
            const data = await callApi('/api/don-hang' + params);

            // Số đơn trên từng tab
            let total = 0;
            document.querySelectorAll('.tab-count').forEach(el => {
                const key = el.dataset.count;
                if (key) {
                    const count = Number(data.soLuongTheoTrangThai[key] || 0);
                    total += count;
                    el.textContent = count > 0 ? count : '';
                }
            });
            document.querySelector('.tab-count[data-count=""]').textContent = total > 0 ? total : '';

            if (!data.donHangs.length) {
                orderList.innerHTML = `
                    <div class="order-card text-center p-5 text-muted">
                        <i class="bi bi-receipt fs-1 d-block mb-2"></i>
                        Chưa có đơn hàng nào${currentStatus ? ' ở trạng thái này' : ''}.
                        <div class="mt-3"><a href="/sanpham" class="btn btn-primary btn-sm">Tiếp tục mua sắm</a></div>
                    </div>`;
                return;
            }
            orderList.innerHTML = data.donHangs.map(renderOrder).join('');
        } catch (error) {
            orderList.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
        }
    }

    // ---------- Chi tiết đơn ----------

    function renderProgress(steps) {
        return `<div class="order-progress">` + steps.map(step => `
            <div class="progress-step ${escapeHtml(step.trangThai)}">
                <div class="progress-icon"><i class="bi ${stepIcons[step.ma] || 'bi-circle'}"></i></div>
                <div class="step-title">${escapeHtml(step.ten)}</div>
                ${step.thoiGian ? `<div class="step-time">${formatDate(step.thoiGian)}</div>` : ''}
            </div>`).join('') + `</div>`;
    }

    async function openDetail(id) {
        detailTitle.textContent = 'Chi tiết đơn hàng #' + id;
        detailBody.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary"></div></div>';
        detailModal.show();
        try {
            const { donHang: order } = await callApi('/api/don-hang/' + id);
            const current = order.TienTrinh.find(step => step.trangThai === 'hienTai' || step.trangThai === 'huy')
                || order.TienTrinh[order.TienTrinh.length - 1];
            const cancelled = order.TrangThai === 'DaHuy';

            detailBody.innerHTML = `
                ${renderProgress(order.TienTrinh)}
                <div class="order-current-status ${cancelled ? 'cancelled' : ''}">
                    <b>${escapeHtml(current.ten)}</b><br><span class="small">${escapeHtml(current.moTa)}</span>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="order-info-box">
                            <div class="fw-bold mb-1"><i class="bi bi-geo-alt text-danger"></i> Địa chỉ nhận hàng</div>
                            <div>${escapeHtml(order.TenNguoiNhan)} – ${escapeHtml(order.SoDienThoaiNhan)}</div>
                            <div class="text-muted small">${escapeHtml(order.DiaChiGiaoHang)}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="order-info-box">
                            <div class="fw-bold mb-1"><i class="bi bi-credit-card text-primary"></i> Thanh toán</div>
                            <div>${escapeHtml(order.TrangThaiThanhToan)}</div>
                            <div class="text-muted small">Đặt lúc ${formatDate(order.NgayDat)}</div>
                        </div>
                    </div>
                </div>
                <div class="border rounded mb-3">${renderItems(order.SanPhams)}</div>
                <div class="d-flex justify-content-between"><span>Tổng tiền hàng</span><span>${formatMoney(order.TongTien)}</span></div>
                ${order.TienGiam > 0 ? `<div class="d-flex justify-content-between text-success"><span>Giảm giá${order.MaGiamGia ? ' (' + escapeHtml(order.MaGiamGia) + ')' : ''}</span><span>-${formatMoney(order.TienGiam)}</span></div>` : ''}
                <div class="d-flex justify-content-between fw-bold fs-5 mt-1"><span>Thành tiền</span><span class="order-total">${formatMoney(order.ThanhTien)}</span></div>
                ${order.CoTheHuy ? `<div class="text-end mt-3"><button type="button" class="btn btn-outline-danger" data-action="cancel" data-id="${order.ID_DonHang}">Hủy đơn hàng</button></div>` : ''}
                ${order.CoTheTraHang ? `<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <span class="small text-muted">Bạn có thể yêu cầu trả hàng đến ${escapeHtml(order.HanTraHang)}.</span>
                    <button type="button" class="btn btn-outline-warning" data-action="return" data-id="${order.ID_DonHang}" data-deadline="${escapeHtml(order.HanTraHang || '')}">Yêu cầu trả hàng</button>
                </div>` : ''}`;
        } catch (error) {
            detailBody.innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
        }
    }

    // ---------- Hủy đơn ----------

    function openCancel(id) {
        cancellingOrderId = id;
        document.getElementById('cancelOrderId').textContent = '#' + id;
        cancelError.classList.add('d-none');
        document.getElementById('lyDo1').checked = true;
        otherReason.classList.add('d-none');
        otherReason.value = '';
        detailModal.hide();
        cancelModal.show();
    }

    document.getElementById('cancelReasons').addEventListener('change', () => {
        const other = document.getElementById('lyDo5').checked;
        otherReason.classList.toggle('d-none', !other);
        if (other) otherReason.focus();
    });

    confirmCancelBtn.addEventListener('click', async () => {
        const selected = document.querySelector('input[name="lyDoHuy"]:checked').value;
        const reason = selected === 'khac' ? otherReason.value.trim() : selected;
        if (!reason) {
            cancelError.textContent = 'Vui lòng nhập lý do hủy đơn.';
            cancelError.classList.remove('d-none');
            return;
        }
        confirmCancelBtn.disabled = true;
        try {
            const data = await callApi('/api/don-hang/' + cancellingOrderId + '/huy', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ly_do: reason }),
            });
            cancelModal.hide();
            alert(data.message);
            loadOrders();
        } catch (error) {
            cancelError.textContent = error.message;
            cancelError.classList.remove('d-none');
        } finally {
            confirmCancelBtn.disabled = false;
        }
    });

    // ---------- Trả hàng (đơn đã nhận, trong thời hạn) ----------

    const returnModal = new bootstrap.Modal(document.getElementById('returnOrderModal'));
    const returnDetail = document.getElementById('returnDetail');
    const returnError = document.getElementById('returnError');
    const confirmReturnBtn = document.getElementById('confirmReturnBtn');
    let returningOrderId = null;

    function openReturn(id, deadline) {
        returningOrderId = id;
        document.getElementById('returnOrderId').textContent = '#' + id;
        document.getElementById('returnDeadline').textContent = deadline ? 'Hạn gửi yêu cầu trả hàng: ' + deadline : '';
        document.getElementById('traLyDo1').checked = true;
        document.getElementById('returnDetailRequired').classList.add('d-none');
        returnDetail.value = '';
        returnError.classList.add('d-none');
        detailModal.hide();
        returnModal.show();
    }

    document.getElementById('returnReasons').addEventListener('change', () => {
        document.getElementById('returnDetailRequired').classList.toggle('d-none', !document.getElementById('traLyDo5').checked);
    });

    confirmReturnBtn.addEventListener('click', async () => {
        const reason = document.querySelector('input[name="lyDoTra"]:checked').value;
        const detail = returnDetail.value.trim();
        if (reason === 'Lý do khác' && !detail) {
            returnError.textContent = 'Vui lòng mô tả lý do trả hàng.';
            returnError.classList.remove('d-none');
            return;
        }
        confirmReturnBtn.disabled = true;
        try {
            const data = await callApi('/api/don-hang/' + returningOrderId + '/tra-hang', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ly_do: reason, chi_tiet: detail }),
            });
            returnModal.hide();
            alert(data.message);
            loadOrders();
        } catch (error) {
            returnError.textContent = error.message;
            returnError.classList.remove('d-none');
        } finally {
            confirmReturnBtn.disabled = false;
        }
    });

    // ---------- Mua lại: thêm lại các sản phẩm của đơn vào giỏ ----------

    async function rebuy(card) {
        let customerId = null;
        try {
            customerId = JSON.parse(localStorage.getItem('user'))?.ID_KhachHang || null;
        } catch (e) { /* bỏ qua */ }
        const items = JSON.parse(card.dataset.order || '[]');
        const failed = [];
        for (const item of items) {
            try {
                await callApi('/api/gio-hang/them', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ID_SanPham: item.id, SoLuong: item.qty, ID_KhachHang: customerId }),
                });
            } catch (error) {
                failed.push(error.message);
            }
        }
        if (failed.length === items.length) {
            alert('Không thể mua lại: ' + failed[0]);
            return;
        }
        if (failed.length) {
            alert('Một số sản phẩm không thể thêm lại (có thể đã ngừng bán). Các sản phẩm còn lại đã vào giỏ hàng.');
        }
        window.location.href = '/giohang';
    }

    // ---------- Sự kiện ----------

    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        currentStatus = tab.dataset.status;
        loadOrders();
    }));

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const id = Number(button.dataset.id);
        if (button.dataset.action === 'detail') openDetail(id);
        if (button.dataset.action === 'cancel') openCancel(id);
        if (button.dataset.action === 'rebuy') rebuy(button.closest('.order-card'));
        if (button.dataset.action === 'return') openReturn(id, button.dataset.deadline);
    });

    // Mở thẳng tab theo đường dẫn, ví dụ /don-hang?trang_thai=DangGiao
    const initialStatus = new URLSearchParams(window.location.search).get('trang_thai') || '';
    const initialTab = document.querySelector(`.order-tab[data-status="${CSS.escape(initialStatus)}"]`);
    if (initialTab) {
        tabs.forEach(t => t.classList.remove('active'));
        initialTab.classList.add('active');
        currentStatus = initialStatus;
    }
    loadOrders();
});
