@extends('admin.layout')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin/khachhang.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="container-fluid p-0">
    <h3 class="mb-4 fw-bold">Quản Lý Khách Hàng</h3>

    <div class="d-flex justify-content-between mb-3">
        <form action="/admin/khach-hang" method="GET" class="d-flex search-form">
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm theo tên, SĐT hoặc Email..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            @if(request('search'))
                <a href="/admin/khach-hang" class="btn btn-outline-secondary ms-2" title="Hủy tìm kiếm"><i class="fas fa-times"></i></a>
            @endif
        </form>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã KH</th>
                        <th>Họ Tên</th>
                        <th>Email</th> <!-- Đã thay thế ID bằng Email -->
                        <th>Số Điện Thoại</th>
                        <th>Địa Chỉ</th>
                        <th class="text-center">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($khachHangs as $kh)
                    <tr id="row-{{ $kh->ID_KhachHang }}">
                        <td class="fw-bold">#{{ $kh->ID_KhachHang }}</td>
                        <td class="fw-bold text-primary">{{ $kh->HoTen }}</td>
                        
                        <!-- Hiển thị Email -->
                        <td>{{ $kh->Email }}</td>
                        
                        <td>(+84) {{ $kh->SoDienThoai }}</td>
                        <td>
                            <span class="address-text" title="{{ $kh->DiaChi }}">
                                {{ $kh->DiaChi }}
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary btn-edit" 
                                data-id="{{ $kh->ID_KhachHang }}" data-hoten="{{ $kh->HoTen }}"
                                data-sdt="{{ $kh->SoDienThoai }}" data-diachi="{{ $kh->DiaChi }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-delete" data-id="{{ $kh->ID_KhachHang }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4">Không có khách hàng nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 py-3">{{ $khachHangs->links('pagination::bootstrap-5') }}</div>
    </div>
</div>

<!-- Modal Form (Chỉ dùng để Sửa) -->
<div class="modal fade" id="modalKhachHang" tabindex="-1">
    <div class="modal-dialog">
        <form id="formKhachHang">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">Cập Nhật Khách Hàng</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="kh_id" name="kh_id">
                    
                    <div class="mb-3">
                        <label>Họ Tên *</label>
                        <input type="text" class="form-control" name="HoTen" id="HoTen" required>
                    </div>
                    
                    <div class="mb-3">
                        <label>Số Điện Thoại</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">+84</span>
                            <input type="text" class="form-control" name="SoDienThoai" id="SoDienThoai" placeholder="987654321">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Địa Chỉ</label>
                        <textarea class="form-control" name="DiaChi" id="DiaChi" rows="3"></textarea>
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
    <script src="{{ asset('js/admin/khachhang.js') }}"></script>
@endsection