document.addEventListener('DOMContentLoaded', () => { 
    const list = document.querySelector('.product-list'); 
    async function loadSanPham() { 
        if (!list) return; 
        const params = new URLSearchParams(); 
        const urlParams = new URLSearchParams(window.location.search); 
        const search = urlParams.get('search'); 
        const danhMuc = document.querySelector('[name="danhmuc"]'); 
        const thuongHieu = document.querySelector('[name="thuonghieu"]'); 
        const gia = document.querySelector('[name="gia"]'); 
        if (search) { 
            params.append('search', search); 
        } 
        if (danhMuc && danhMuc.value) { 
            params.append('danhmuc', danhMuc.value); 
        } 
 
        if (thuongHieu && thuongHieu.value) { 
            params.append('thuonghieu', thuongHieu.value); 
        } 
 
        if (gia && gia.value) { 
            params.append('gia', gia.value); 
        } 
 
        try { 
            const res = await fetch(`/api/sanpham?${params.toString()}`, { 
                headers: { 
                    'Accept': 'application/json' 
                } 
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
                            <span class="price-sale"> 
                                ${formatPrice(sp.GiaKhuyenMai)}đ 
                            </span> 
                            <del> 
                                ${formatPrice(sp.GiaBan)}đ 
                            </del> 
                        </div> 
                    `; 
                } else { 
                    giaHTML = ` 
                        <div class="product-price"> 
                            ${formatPrice(sp.GiaBan)}đ 
                        </div> 
                    `; 
                } 
 
                list.innerHTML += ` 
                    <div class="col-md-6 col-xl-4"> 
                        <div class="product-card"> 
                            <div class="product-image"> 
                                <img src="${sp.HinhAnh || ''}" 
                                    alt="${sp.TenSanPham}"> 
                            </div> 
 
                            <div class="product-info"> 
                                <h5>${sp.TenSanPham}</h5> 
 
                                ${giaHTML} 
 
                                <button 
                                    class="btn btn-primary w-100 btn-them-gio" 
                                    data-id="${sp.ID_SanPham}"> 
                                    <i class="bi bi-cart-plus"></i> 
                                    Thêm vào giỏ hàng 
                                </button> 
                            </div> 
                        </div> 
                    </div> 
                `; 
            }); 
 
            ganSuKienThemGio(); 
 
            const soSanPham = document.querySelector('.product-header span'); 
 
            if (soSanPham) { 
                soSanPham.textContent = `${data.data.length} sản phẩm`; 
            } 
 
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
 
    function layMaPhien() { 
        let maPhien = localStorage.getItem('MaPhien'); 
 
        if (!maPhien) { 
            maPhien = crypto.randomUUID(); 
            localStorage.setItem('MaPhien', maPhien); 
        } 
 
        return maPhien; 
    } 
 
    async function themVaoGio(idSanPham, button) { 
        try { 
            const user = JSON.parse(localStorage.getItem('user')); 
 
            const body = { 
                ID_SanPham: idSanPham, 
                SoLuong: 1 
            }; 
 
            if (user && user.ID_KhachHang) { 
                body.ID_KhachHang = user.ID_KhachHang; 
            } else { 
                body.MaPhien = layMaPhien(); 
            } 
 
            button.disabled = true; 
 
            const res = await fetch('/api/gio-hang/them', { 
                method: 'POST', 
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json' 
                }, 
                body: JSON.stringify(body) 
            }); 
 
            const data = await res.json(); 
 
            console.log('Giỏ hàng:', data); 
 
            if (data.success) { 
                capNhatSoGioHang(data.data.TongSoLuong); 
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
 
    function capNhatSoGioHang(tongSoLuong) { 
        const badge = document.getElementById('cart-count'); 
 
        if (!badge) return; 
 
        badge.textContent = tongSoLuong; 
        badge.style.display = 'flex'; 
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
 
            if (danhMuc && danhMuc.value) { 
                params.set('danhmuc', danhMuc.value); 
            } else { 
                params.delete('danhmuc'); 
            } 
 
            if (thuongHieu && thuongHieu.value) { 
                params.set('thuonghieu', thuongHieu.value); 
            } else { 
                params.delete('thuonghieu'); 
            } 
 
            if (gia && gia.value) { 
                params.set('gia', gia.value); 
            } else { 
                params.delete('gia'); 
            } 
 
            window.history.pushState( 
                {}, 
                '', 
                `/sanpham?${params.toString()}` 
            ); 
 
            loadSanPham(); 
        }); 
    } 
 
    const searchBox = document.querySelector('.search-box'); 
 
    if (searchBox) { 
        const searchInput = searchBox.querySelector('input'); 
        const searchButton = searchBox.querySelector('button'); 
 
        const urlSearch = new URLSearchParams(window.location.search).get('search'); 
 
        if (searchInput && urlSearch) { 
            searchInput.value = urlSearch; 
        } 
 
        if (searchButton) { 
            searchButton.addEventListener('click', () => { 
                const keyword = searchInput.value.trim(); 
 
                const params = new URLSearchParams(window.location.search); 
 
                if (keyword) { 
                    params.set('search', keyword); 
                } else { 
                    params.delete('search'); 
                } 
 
                window.history.pushState( 
                    {}, 
                    '', 
                    `/sanpham?${params.toString()}` 
                ); 
 
                loadSanPham(); 
            }); 
        } 
 
        if (searchInput) { 
            searchInput.addEventListener('keydown', event => { 
                if (event.key === 'Enter') { 
                    event.preventDefault(); 
 
                    const keyword = searchInput.value.trim(); 
 
                    const params = new URLSearchParams(window.location.search); 
 
                    if (keyword) { 
                        params.set('search', keyword); 
                    } else { 
                        params.delete('search'); 
                    } 
 
                    window.history.pushState( 
                        {}, 
                        '', 
                        `/sanpham?${params.toString()}` 
                    ); 
 
                    loadSanPham(); 
                } 
            }); 
        } 
    } 
 
    loadSanPham(); 
}); 