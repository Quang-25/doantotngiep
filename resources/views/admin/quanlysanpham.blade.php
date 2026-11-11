@extends('admin.layout') {{-- Gọi khung giao diện --}}

@section('css')
    <!-- CSS riêng của trang Sản phẩm -->
    <link rel="stylesheet" href="{{ asset('css/admin/sanpham.css') }}">
@endsection

@section('content')
<div class="container-fluid p-0">
    <h3 class="mb-4 fw-bold">Quản Lý Sản Phẩm</h3>

    <!-- Thanh công cụ: Tìm kiếm & Thêm mới -->
    <div class="d-flex justify-content-between mb-3">
        <form action="/admin/san-pham" method="GET" class="d-flex" style="width: 400px;">
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm tên hoặc mã SP..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            @if(request('search'))
                <a href="/admin/san-pham" class="btn btn-outline-secondary ms-2" title="Hủy tìm kiếm"><i class="fas fa-times"></i></a>
            @endif
        </form>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalSanPham" id="btnOpenAdd">
            <i class="fas fa-plus"></i> Thêm Sản Phẩm
        </button>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã</th>
                        <th>Hình Ảnh</th>
                        <th>Tên Sản Phẩm</th>
                        <th>Danh Mục</th>
                        <th>Thương Hiệu</th>
                        <th>Giá Bán</th>
                        <th>Tồn Kho</th>
                        <th>Trạng Thái</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sanPhams as $sp)
                    <tr id="row-{{ $sp->ID_SanPham }}">
                        <td class="fw-bold">#{{ $sp->ID_SanPham }}</td>
                        <td><img src="{{ $sp->HinhAnh }}" class="product-img" style="width:50px; height:50px; object-fit:cover; border-radius:5px;" onerror="this.src='https://via.placeholder.com/50'"></td>
                        <td><span class="title-sp" style="max-width:200px; display:inline-block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $sp->TenSanPham }}">{{ $sp->TenSanPham }}</span></td>
                        <td>{{ $sp->TenDanhMuc }}</td>
                        <td>{{ $sp->TenThuongHieu }}</td>
                        <td>
                            @if($sp->GiaKhuyenMai && $sp->GiaKhuyenMai < $sp->GiaBan)
                                {{-- Có khuyến mãi: giá đang bán (khuyến mãi) in đỏ, giá gốc gạch ngang --}}
                                <span class="text-danger fw-bold">{{ number_format($sp->GiaKhuyenMai, 0, ',', '.') }}đ</span>
                                <span class="badge bg-danger ms-1">-{{ round(($sp->GiaBan - $sp->GiaKhuyenMai) / $sp->GiaBan * 100) }}%</span>
                                <br><small class="text-muted text-decoration-line-through">{{ number_format($sp->GiaBan, 0, ',', '.') }}đ</small>
                            @else
                                <span class="text-danger fw-bold">{{ number_format($sp->GiaBan, 0, ',', '.') }}đ</span>
                                @if($sp->GiaKhuyenMai)
                                    <br><small class="text-warning" title="Giá khuyến mãi phải nhỏ hơn giá bán">
                                        <i class="fas fa-exclamation-triangle"></i> KM {{ number_format($sp->GiaKhuyenMai, 0, ',', '.') }}đ ≥ giá bán
                                    </small>
                                @endif
                            @endif
                        </td>
                        <td>{{ $sp->SoLuongTon }}</td>
                        <td>
                            {{-- Hết hàng (tồn kho <= 0) luôn hiện Ngừng kinh doanh, không hiện Đang bán --}}
                            @if($sp->TrangThai == 'DangBan' && $sp->SoLuongTon > 0)
                                <span class="badge bg-success">Đang Bán</span>
                            @else
                                <span class="badge bg-secondary">Ngừng Kinh Doanh</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary btn-edit" 
                                data-id="{{ $sp->ID_SanPham }}" data-ten="{{ $sp->TenSanPham }}"
                                data-danhmuc="{{ $sp->ID_DanhMuc }}" data-thuonghieu="{{ $sp->ID_ThuongHieu }}"
                                data-giaban="{{ $sp->GiaBan }}" data-giakm="{{ $sp->GiaKhuyenMai }}"
                                data-soluong="{{ $sp->SoLuongTon }}" data-mota="{{ $sp->MoTa }}" data-size="{{ \App\Support\SizeSanPham::chuanHoa($sp->SizeCoSan) }}"
                                data-anhphu="{{ json_encode($hinhAnhPhu[$sp->ID_SanPham] ?? []) }}"
                                data-thongso="{{ json_encode(['TrongLuong' => $sp->TrongLuong, 'ChuViTayCam' => trim((string) $sp->ChuViTayCam), 'ChatLieu' => $sp->ChatLieu, 'DiemCanBang' => $sp->DiemCanBang, 'DoCung' => $sp->DoCung, 'MucCangToiDa' => $sp->MucCangToiDa, 'TrinhDoPhuHop' => $sp->TrinhDoPhuHop]) }}"
                                data-hinhanh="{{ $sp->HinhAnh }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $sp->ID_SanPham }}" {{ $sp->TrangThai == 'NgungKinhDoanh' || $sp->SoLuongTon <= 0 ? 'disabled' : '' }}>
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-4">Không có sản phẩm nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 py-3">{{ $sanPhams->links('pagination::bootstrap-5') }}</div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="modalSanPham" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="formSanPham">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">Thêm Sản Phẩm Mới</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="sp_id" name="sp_id">
                    
                    <div class="mb-3">
                        <label>Tên sản phẩm *</label>
                        <input type="text" class="form-control" name="TenSanPham" id="TenSanPham" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <label>Danh mục *</label>
                            <select class="form-select" name="ID_DanhMuc" id="ID_DanhMuc" required>
                                <option value="">- Chọn -</option>
                                @foreach($danhMuc as $dm) <option value="{{ $dm->ID_DanhMuc }}">{{ $dm->TenDanhMuc }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label>Thương hiệu *</label>
                            <select class="form-select" name="ID_ThuongHieu" id="ID_ThuongHieu" required>
                                <option value="">- Chọn -</option>
                                @foreach($thuongHieu as $th) <option value="{{ $th->ID_ThuongHieu }}">{{ $th->TenThuongHieu }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-4">
                            <label>Giá bán (VNĐ) *</label>
                            <input type="number" class="form-control" name="GiaBan" id="GiaBan" required>
                        </div>
                        <div class="col-4">
                            <label>Giá Khuyến Mãi</label>
                            <input type="number" class="form-control" name="GiaKhuyenMai" id="GiaKhuyenMai">
                        </div>
                        <div class="col-4">
                            <label>Tồn kho *</label>
                            <input type="number" class="form-control" name="SoLuongTon" id="SoLuongTon" value="0" required>
                        </div>
                    </div>
                    
                    <!-- Thông số vợt (bảng thongsovot): chỉ hiện khi danh mục là Vợt cầu lông -->
                    <div id="khuThongSoVot" class="border rounded p-3 mb-3 bg-light">
                        <h6 class="fw-bold mb-1"><i class="fas fa-sliders-h me-1"></i> Thông số vợt</h6>
                        <small class="d-block mb-3" id="ghiChuThongSoVot"></small>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label>Trọng lượng</label>
                                <input type="text" class="form-control" name="TrongLuong" id="TrongLuong" maxlength="20" list="dsTrongLuong" placeholder="4U (80-84g)">
                            </div>
                            <div class="col-md-4">
                                <label>Chu vi tay cầm</label>
                                <select class="form-select" name="ChuViTayCam" id="ChuViTayCam">
                                    <option value="">- Chọn -</option>
                                    <option value="to">To</option>
                                    <option value="nhỏ">Nhỏ</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Chất liệu</label>
                                <input type="text" class="form-control" name="ChatLieu" id="ChatLieu" maxlength="100" list="dsChatLieu" placeholder="Carbon">
                            </div>
                            <div class="col-md-4">
                                <label>Điểm cân bằng</label>
                                <input type="text" class="form-control" name="DiemCanBang" id="DiemCanBang" maxlength="50" list="dsDiemCanBang" placeholder="Đầu nặng (Head Heavy)">
                            </div>
                            <div class="col-md-4">
                                <label>Độ cứng</label>
                                <input type="text" class="form-control" name="DoCung" id="DoCung" maxlength="50" list="dsDoCung" placeholder="Trung bình">
                            </div>
                            <div class="col-md-4">
                                <label>Mức căng tối đa</label>
                                <input type="text" class="form-control" name="MucCangToiDa" id="MucCangToiDa" maxlength="50" list="dsMucCang" placeholder="20-28 lbs">
                            </div>
                            <div class="col-md-4">
                                <label>Trình độ phù hợp</label>
                                <select class="form-select" name="TrinhDoPhuHop" id="TrinhDoPhuHop">
                                    <option value="MoiChoi">Mới chơi</option>
                                    <option value="TrungCap" selected>Trung cấp</option>
                                    <option value="NangCao">Nâng cao</option>
                                    <option value="ChuyenNghiep">Chuyên nghiệp</option>
                                </select>
                            </div>
                        </div>
                        <datalist id="dsTrongLuong"><option value="3U (85-89g)"><option value="4U (80-84g)"><option value="5U (75-79g)"></datalist>
                        <datalist id="dsChatLieu"><option value="Carbon"><option value="Nhôm"><option value="Titanium"><option value="Tungsten"><option value="Graphite"></datalist>
                        <datalist id="dsDiemCanBang"><option value="Đầu nặng (Head Heavy)"><option value="Cân bằng (Even Balance)"><option value="Đầu nhẹ (Head Light)"></datalist>
                        <datalist id="dsDoCung"><option value="Dẻo"><option value="Trung bình"><option value="Cứng"><option value="Rất cứng"></datalist>
                        <datalist id="dsMucCang"><option value="18-24 lbs"><option value="20-26 lbs"><option value="20-28 lbs"><option value="22-30 lbs"></datalist>
                    </div>

                    <div class="mb-3">
                        <label>Size có sẵn <small class="text-muted">(giày, quần áo — cách nhau bằng dấu phẩy)</small></label>
                        <input type="text" class="form-control" name="SizeCoSan" id="SizeCoSan" maxlength="100" placeholder="VD giày: 39,40,41,42,43 — áo/váy: S,M,L,XL">
                        <small class="text-muted">Giày để trống hoặc chỉ 1 size thì khách được chọn size chuẩn 38–44.</small>
                    </div>

                    <div class="mb-3">
                        <label>Link Hình Ảnh (URL) *</label>
                        <input type="text" class="form-control" name="HinhAnh" id="HinhAnh" placeholder="https://cdn.shopvnb.com/..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label>Hình ảnh phụ <small class="text-muted">(mỗi dòng 1 link, thứ tự dòng = thứ tự ảnh)</small></label>
                        <textarea class="form-control" name="HinhAnhPhu" id="HinhAnhPhu" rows="3"
                            placeholder="https://cdn.shopvnb.com/img/300x300/uploads/gallery/anh-1.webp&#10;https://cdn.shopvnb.com/img/300x300/uploads/gallery/anh-2.webp"></textarea>
                        <div id="xemTruocAnhPhu" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>

                    <div class="mb-3">
                        <label>Mô tả chi tiết</label>
                        <textarea class="form-control" name="MoTa" id="MoTa" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">Lưu Dữ Liệu</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
    <!-- JS Xử lý Thêm/Sửa/Xóa của trang Sản phẩm -->
    <script src="{{ asset('js/admin/sanpham.js') }}?v={{ filemtime(public_path('js/admin/sanpham.js')) }}"></script>
@endsection