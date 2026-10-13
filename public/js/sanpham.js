document.addEventListener('DOMContentLoaded', () => { 
    const list = document.querySelector('.product-list'); 

    async function loadSanPham() { 
        if (!list) return; 
        
        // 1. ĐỌC THAM SỐ TỪ URL (Menu truyền xuống)
        const urlParams = new URLSearchParams(window.location.search); 
        const search = urlParams.get('search'); 
        const danhMucUrl = urlParams.get('danh_muc'); 
        const thuongHieuUrl = urlParams.get('thuong_hieu');
        const trinhDoUrl = urlParams.get('trinh_do');

        // ĐỌC THAM SỐ TỪ BỘ LỌC (Form bên trái)
        const danhMucForm = document.querySelector('[name="danhmuc"]'); 
        const thuongHieuForm = document.querySelector('[name="thuonghieu"]'); 
        const gia = document.querySelector('[name="gia"]'); 

        // 2. GỘP CHUNG VÀO 1 PARAMS ĐỂ GỬI API
        const params = new URLSearchParams(); 
        
        if (search) params.append('search', search); 
        
        // Ưu tiên lấy từ Form lọc trước, nếu không có mới lấy từ URL Menu
        const finalDanhMuc = (danhMucForm && danhMucForm.value) ? danhMucForm.value : danhMucUrl;
        if (finalDanhMuc) params.append('danh_muc', finalDanhMuc); 
        
        const finalThuongHieu = (thuongHieuForm && thuongHieuForm.value) ? thuongHieuForm.value : thuongHieuUrl;
        if (finalThuongHieu) params.append('thuong_hieu', finalThuongHieu); 

        if (trinhDoUrl) params.append('trinh_do', trinhDoUrl);

        if (gia && gia.value) params.append('gia', gia.value); 

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

    const form = document.querySelector('.filter-section form'); 
    if (form) { 
        form.addEventListener('submit', event => { 
            event.preventDefault(); 
            const params = new URLSearchParams(window.location.search); 
            const danhMuc = document.querySelector('[name="danhmuc"]'); 
            const thuongHieu = document.querySelector('[name="thuonghieu"]'); 
            const gia = document.querySelector('[name="gia"]'); 

            if (danhMuc && danhMuc.value) params.set('danh_muc', danhMuc.value); 
            else params.delete('danh_muc'); 

            if (thuongHieu && thuongHieu.value) params.set('thuong_hieu', thuongHieu.value); 
            else params.delete('thuong_hieu'); 

            if (gia && gia.value) params.set('gia', gia.value); 
            else params.delete('gia'); 

            window.history.pushState({}, '', `/sanpham?${params.toString()}`); 
            loadSanPham(); 
        }); 
    } 

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

    loadSanPham(); 
});