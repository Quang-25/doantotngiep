document.addEventListener('DOMContentLoaded', () => { 
    const list = document.querySelector('.product-list'); 

    async function loadSanPham() { 
        if (!list) return; 
        
        // Tham số lọc lấy từ URL (form lọc đã đồng bộ vào URL khi bấm "Áp dụng lọc")
        const params = buildApiParams();

        try { 
            const res = await fetch(`/api/sanpham?${params.toString()}`, { 
                headers: { 'Accept': 'application/json' } 
            }); 

            const data = await res.json(); 

            if (!data.success) { 
                throw new Error(data.message || 'Không thể tải sản phẩm.'); 
            } 

            list.innerHTML = ''; 

            if (!data.data.length) { 
                list.innerHTML = ` 
                    <div class="col-12 text-center py-5"> 
                        <i class="bi bi-box-seam fs-1"></i> 
                        <p class="mt-3">Không tìm thấy sản phẩm phù hợp.</p> 
                    </div> 
                `; 
                return; 
            } 

            data.data.forEach(sp => { 
                let giaHTML = ''; 

                if (sp.GiaKhuyenMai) { 
                    giaHTML = ` 
                        <div class="product-price"> 
                            <span class="price-sale">${formatPrice(sp.GiaKhuyenMai)}đ</span> 
                            <del>${formatPrice(sp.GiaBan)}đ</del> 
                        </div> 
                    `; 
                } else { 
                    giaHTML = ` 
                        <div class="product-price"> 
                            ${formatPrice(sp.GiaBan)}đ 
                        </div> 
                    `; 
                } 

                let hinhAnhSrc = '';
                if (sp.HinhAnh) {
                    hinhAnhSrc = sp.HinhAnh.startsWith('http') ? sp.HinhAnh : `/images/${sp.HinhAnh}`;
                }

                list.innerHTML += ` 
                    <div class="col-md-6 col-xl-4"> 
                        <div class="product-card"> 
                           <div class="product-image text-center"> 
                            <a href="/san-pham/${sp.ID_SanPham}">
                            <img src="${hinhAnhSrc}" alt="${sp.TenSanPham}" style="height: 250px; width: 100%; object-fit: contain; padding: 15px;"> 
                            </a>
                            </div>
                            <div class="product-info"> 
                                <a href="/san-pham/${sp.ID_SanPham}" class="text-decoration-none text-dark">
                                    <h5>${sp.TenSanPham}</h5> 
                                </a>
                                ${giaHTML} 
                                <button class="btn btn-primary w-100 btn-them-gio" data-id="${sp.ID_SanPham}"> 
                                    <i class="bi bi-cart-plus"></i> Thêm vào giỏ hàng 
                                </button> 
                            </div> 
                        </div> 
                    </div> 
                `; 
            }); 

            ganSuKienThemGio(); 

            const soSanPham = document.querySelector('.product-header span'); 
            if (soSanPham) soSanPham.textContent = `${data.data.length} sản phẩm`; 

        } catch (error) { 
            console.error('Lỗi tải sản phẩm:', error); 
            list.innerHTML = ` 
                <div class="col-12 text-center py-5"> 
                    <p>Không thể tải sản phẩm.</p> 
                </div> 
            `; 
        } 
    } 

    function ganSuKienThemGio() { 
        const buttons = document.querySelectorAll('.btn-them-gio'); 
        buttons.forEach(button => { 
            button.addEventListener('click', () => { 
                themVaoGio(button.dataset.id, button); 
            }); 
        }); 
    } 

    // ĐÃ XÓA hàm layMaPhien() tại đây

    async function themVaoGio(idSanPham, button) { 
        try { 
            const user = JSON.parse(localStorage.getItem('user')); 
            
            // KIỂM TRA ĐĂNG NHẬP
            if (!user || !user.ID_KhachHang) {
                alert('Vui lòng đăng nhập để thêm sản phẩm vào giỏ hàng!');
                window.location.href = '/Dangnhap'; // Thay đổi đường dẫn này nếu route đăng nhập của bạn khác
                return; 
            }

            // Chỉ gắn ID_KhachHang vì đã bỏ luồng MaPhien
            const body = { 
                ID_SanPham: idSanPham, 
                SoLuong: 1,
                ID_KhachHang: user.ID_KhachHang
            }; 

            button.disabled = true; 

            const res = await fetch('/api/gio-hang/them', { 
                method: 'POST', 
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json' 
                },
                credentials: 'same-origin',
                body: JSON.stringify(body) 
            }); 

            const data = await res.json(); 

            if (data.success) { 
                if (typeof window.laySoLuongGioHangToanCuc === 'function') {
                    window.laySoLuongGioHangToanCuc();
                }
                alert(data.message); 
            } else { 
                alert(data.message || 'Không thể thêm sản phẩm.'); 
            }
        } catch (error) { 
            console.error('Lỗi:', error); 
            alert('Không thể thêm sản phẩm vào giỏ hàng.'); 
        } finally { 
            button.disabled = false; 
        } 
    } 

    function formatPrice(price) { 
        return new Intl.NumberFormat('vi-VN').format(price); 
    } 

    // ================= BỘ LỌC =================
    const form = document.querySelector('.filter-section form');
    // Tên ô trong form => tên tham số trên URL / API
    const FILTER_FIELDS = {
        sap_xep: 'sap_xep', danhmuc: 'danh_muc', thuonghieu: 'thuong_hieu', gia: 'gia',
        gia_tu: 'gia_tu', gia_den: 'gia_den', giam_gia: 'giam_gia', con_hang: 'con_hang',
        trinh_do: 'trinh_do', trong_luong: 'trong_luong', can_bang: 'can_bang', do_cung: 'do_cung',
        co_can: 'co_can', size: 'size',
    };
    const API_KEYS = ['search', ...Object.values(FILTER_FIELDS)];

    function buildApiParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const params = new URLSearchParams();
        API_KEYS.forEach(key => {
            const value = urlParams.get(key);
            if (value) params.set(key, value);
        });
        return params;
    }

    // Hiện nhóm "Thông số vợt" / "Size" theo danh mục đang chọn; nhóm bị ẩn thì xóa giá trị.
    function capNhatNhomLoc() {
        if (!form) return;
        const danhMuc = form.elements.danhmuc.value;
        form.querySelectorAll('.filter-group').forEach(group => {
            const show = danhMuc !== '' && group.dataset.showFor.split(',').includes(danhMuc);
            group.hidden = !show;
            if (!show) group.querySelectorAll('select, input').forEach(el => { el.value = ''; });
        });
        form.querySelectorAll('#loc-size optgroup').forEach(group => {
            group.hidden = danhMuc !== '' && !group.dataset.for.split(',').includes(danhMuc);
        });
    }

    // Điền lại form từ URL (menu "Vợt", "Yonex", "trình độ"... hoặc khi tải lại trang)
    function dongBoFormTuUrl() {
        if (!form) return;
        const urlParams = new URLSearchParams(window.location.search);
        Object.entries(FILTER_FIELDS).forEach(([field, key]) => {
            const input = form.elements[field];
            if (!input) return;
            const value = urlParams.get(key) || '';
            if (input.type === 'checkbox') input.checked = value === '1';
            else input.value = value;
        });
        // Danh mục từ menu dạng "3,5" (quần áo + váy) không có trong ô chọn => ô để trống, vẫn lọc theo URL
        capNhatNhomLoc();
    }

    if (form) {
        form.elements.danhmuc.addEventListener('change', capNhatNhomLoc);
        // Chọn mức giá sẵn thì xóa ô tự nhập để không bị lẫn
        form.elements.gia.addEventListener('change', () => { form.elements.gia_tu.value = ''; form.elements.gia_den.value = ''; });

        form.addEventListener('submit', event => {
            event.preventDefault();
            const current = new URLSearchParams(window.location.search);
            const params = new URLSearchParams();
            if (current.get('search')) params.set('search', current.get('search'));
            // Danh mục nhiều giá trị từ menu (vd 3,5) được giữ nếu khách không chọn danh mục khác
            if (!form.elements.danhmuc.value && (current.get('danh_muc') || '').includes(',')) {
                params.set('danh_muc', current.get('danh_muc'));
            }

            Object.entries(FILTER_FIELDS).forEach(([field, key]) => {
                const input = form.elements[field];
                if (!input || input.closest('.filter-group')?.hidden) return;
                const value = input.type === 'checkbox' ? (input.checked ? '1' : '') : input.value.trim();
                if (value) params.set(key, value);
            });
            // Ô tự nhập giá ưu tiên hơn mức chọn sẵn
            if (params.get('gia_tu') || params.get('gia_den')) params.delete('gia');

            window.history.pushState({}, '', `/sanpham?${params.toString()}`);
            loadSanPham();
        });

        document.getElementById('btn-xoa-loc')?.addEventListener('click', () => {
            const search = new URLSearchParams(window.location.search).get('search');
            window.history.pushState({}, '', '/sanpham' + (search ? `?search=${encodeURIComponent(search)}` : ''));
            dongBoFormTuUrl();
            loadSanPham();
        });
    }

    // Nút Back/Forward của trình duyệt
    window.addEventListener('popstate', () => { dongBoFormTuUrl(); loadSanPham(); });

    const searchBox = document.querySelector('.search-box'); 
    if (searchBox) { 
        const searchInput = searchBox.querySelector('input'); 
        const searchButton = searchBox.querySelector('button'); 
        const urlSearch = new URLSearchParams(window.location.search).get('search'); 

        if (searchInput && urlSearch) searchInput.value = urlSearch; 

        if (searchButton) { 
            searchButton.addEventListener('click', () => { 
                const keyword = searchInput.value.trim(); 
                const params = new URLSearchParams(window.location.search); 
                if (keyword) params.set('search', keyword); 
                else params.delete('search'); 
                window.history.pushState({}, '', `/sanpham?${params.toString()}`); 
                loadSanPham(); 
            }); 
        } 

        if (searchInput) { 
            searchInput.addEventListener('keydown', event => { 
                if (event.key === 'Enter') { 
                    event.preventDefault(); 
                    const keyword = searchInput.value.trim(); 
                    const params = new URLSearchParams(window.location.search); 
                    if (keyword) params.set('search', keyword); 
                    else params.delete('search'); 
                    window.history.pushState({}, '', `/sanpham?${params.toString()}`); 
                    loadSanPham(); 
                } 
            }); 
        } 
    } 

    dongBoFormTuUrl();
    loadSanPham(); 
});