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
                            <span class="text-danger fw-bold">{{ number_format($sp->GiaBan, 0, ',', '.') }}đ</span>
                            @if($sp->GiaKhuyenMai)
                                <br><small class="text-muted text-decoration-line-through">{{ number_format($sp->GiaKhuyenMai, 0, ',', '.') }}đ</small>
                            @endif
                        </td>
                        <td>{{ $sp->SoLuongTon }}</td>
                        <td>
                            @if($sp->TrangThai == 'DangBan')
                                <span class="badge bg-success">Đang Bán</span>
                            @else
                                <span class="badge bg-secondary">Ngừng Bán</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary btn-edit" 
                                data-id="{{ $sp->ID_SanPham }}" data-ten="{{ $sp->TenSanPham }}"
                                data-danhmuc="{{ $sp->ID_DanhMuc }}" data-thuonghieu="{{ $sp->ID_ThuongHieu }}"
                                data-giaban="{{ $sp->GiaBan }}" data-giakm="{{ $sp->GiaKhuyenMai }}"
                                data-soluong="{{ $sp->SoLuongTon }}" data-mota="{{ $sp->MoTa }}"
                                data-hinhanh="{{ $sp->HinhAnh }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $sp->ID_SanPham }}" {{ $sp->TrangThai == 'NgungKinhDoanh' ? 'disabled' : '' }}>
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
                    
                    <div class="mb-3">
                        <label>Link Hình Ảnh (URL) *</label>
                        <input type="text" class="form-control" name="HinhAnh" id="HinhAnh" placeholder="https://cdn.shopvnb.com/..." required>
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
    <script src="{{ asset('js/admin/sanpham.js') }}"></script>
@endsection