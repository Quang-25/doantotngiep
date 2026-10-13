@extends('admin.layout')

@section('css')
    <!-- Gọi file CSS tách rời -->
    <link rel="stylesheet" href="{{ asset('css/admin/taikhoan.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold m-0">Quản Lý Người Dùng & Phân Quyền</h3>
    </div>

    <!-- Thanh tìm kiếm đồng bộ -->
    <div class="mb-3">
        <form action="/admin/tai-khoan" method="GET" class="d-flex search-form">
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm kiếm Email..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            @if(request('search'))
                <a href="/admin/tai-khoan" class="btn btn-outline-secondary ms-2" title="Hủy"><i class="fas fa-times"></i></a>
            @endif
        </form>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Email Đăng Nhập</th>
                        <th>Phân Quyền</th>
                        <th>Trạng Thái</th>
                        <th>Ngày Tạo</th>
                        <th class="text-center">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taiKhoans as $tk)
                    <tr>
                        <td class="fw-bold">TK-{{ $tk->ID_TaiKhoan }}</td>
                        <td class="fw-bold text-primary">{{ $tk->Email }}</td>
                        <td>
                            @if($tk->VaiTro == 'Admin')
                                <span class="badge bg-danger badge-role"><i class="fas fa-user-shield"></i> Quản Trị Viên</span>
                            @else
                                <span class="badge bg-info text-dark badge-role"><i class="fas fa-user"></i> Khách Hàng</span>
                            @endif
                        </td>
                        <td>
                            @if($tk->TrangThai == 'HoatDong')
                                <span class="badge bg-success badge-status">Hoạt động</span>
                            @else
                                <span class="badge bg-secondary badge-status">Bị khóa</span>
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($tk->NgayTao)->format('d/m/Y H:i') }}</td>
                        <td class="text-center">
                            <!-- ĐÃ FIX: Chuyển từ onclick sang dùng data-* attributes -->
                            <button class="btn btn-sm btn-primary me-1 btn-edit-account" 
                                data-id="{{ $tk->ID_TaiKhoan }}" 
                                data-email="{{ $tk->Email }}" 
                                data-vaitro="{{ $tk->VaiTro }}" 
                                data-trangthai="{{ $tk->TrangThai }}" 
                                title="Chỉnh sửa / Phân quyền">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            @if($tk->TrangThai == 'HoatDong')
                                <button class="btn btn-sm btn-warning text-dark btn-toggle-status" data-id="{{ $tk->ID_TaiKhoan }}" data-status="Khoa" title="Khóa tài khoản">
                                    <i class="fas fa-lock"></i>
                                </button>
                            @else
                                <button class="btn btn-sm btn-success btn-toggle-status" data-id="{{ $tk->ID_TaiKhoan }}" data-status="HoatDong" title="Mở khóa tài khoản">
                                    <i class="fas fa-unlock"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4">Không có dữ liệu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 py-3">{{ $taiKhoans->links('pagination::bootstrap-5') }}</div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="modalTaiKhoan" tabindex="-1">
    <div class="modal-dialog">
        <form id="formTaiKhoan">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">Thêm Tài Khoản Mới</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" id="tk_id">
                    
                    <div class="mb-3">
                        <label class="fw-bold mb-1">Email Đăng Nhập *</label>
                        <input type="email" class="form-control" id="Email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold mb-1">Mật khẩu <span id="pass-hint" class="text-danger small"></span></label>
                        <input type="password" class="form-control" id="MatKhau" placeholder="Nhập mật khẩu...">
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="fw-bold mb-1">Phân Quyền</label>
                            <select class="form-select" id="VaiTro">
                                <option value="KhachHang">Khách Hàng</option>
                                <option value="Admin">Quản Trị Viên</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="fw-bold mb-1">Trạng Thái</label>
                            <select class="form-select" id="TrangThai">
                                <option value="HoatDong">Hoạt Động</option>
                                <option value="Khoa">Khóa (Ban)</option>
                            </select>
                        </div>
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
    <script src="{{ asset('js/admin/taikhoan.js') }}"></script>
@endsection