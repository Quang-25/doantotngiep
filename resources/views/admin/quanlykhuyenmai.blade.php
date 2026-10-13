@extends('admin.layout')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/admin/khuyenmai.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold m-0">Quản Lý Chương Trình Khuyến Mãi</h3>
        <button class="btn btn-primary" onclick="openModalAdd()">
            <i class="fas fa-plus-circle"></i> Thêm Mã Mới
        </button>
    </div>

    <!-- Thanh tìm kiếm -->
    <div class="mb-3">
        <form action="/admin/khuyen-mai" method="GET" class="d-flex search-form">
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm mã hoặc mô tả..." value="{{ request('search') }}">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            @if(request('search'))
                <a href="/admin/khuyen-mai" class="btn btn-outline-secondary ms-2" title="Hủy"><i class="fas fa-times"></i></a>
            @endif
        </form>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã Code</th>
                        <th>Mô Tả</th>
                        <th class="text-center">Mức Giảm</th>
                        <th class="text-center">Số Lượng</th>
                        <th>Thời Gian</th>
                        <th>Trạng Thái</th>
                        <th class="text-center">Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($khuyenMais as $km)
                    <tr>
                        <td><span class="discount-code-badge">{{ $km->MaCode }}</span></td>
                        <td class="fw-bold text-primary">{{ $km->MoTa }}</td>
                        <td class="text-center fw-bold text-success">
                            {{ $km->LoaiGiam == 'PhanTram' ? $km->GiaTriGiam.'%' : number_format($km->GiaTriGiam, 0, ',', '.').'đ' }}
                            <br><small class="text-muted">Đơn từ: {{ number_format($km->GiaTriDonToiThieu, 0, ',', '.') }}đ</small>
                        </td>
                        <td class="text-center">{{ $km->SoLuong ?? '∞' }}</td>
                        <td style="font-size: 13px;">
                            Từ: {{ \Carbon\Carbon::parse($km->NgayBatDau)->format('d/m/Y') }}<br>
                            Đến: {{ \Carbon\Carbon::parse($km->NgayKetThuc)->format('d/m/Y') }}
                        </td>
                        <td>
                            @if($km->TrangThai == 'HoatDong' && \Carbon\Carbon::parse($km->NgayKetThuc)->isFuture())
                                <span class="badge bg-success">Hoạt động</span>
                            @elseif($km->TrangThai == 'TamNgung')
                                <span class="badge bg-secondary">Tạm ngưng</span>
                            @else
                                <span class="badge bg-danger">Hết hạn</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-primary me-1 btn-edit-km" 
                                data-id="{{ $km->ID_KhuyenMai }}" 
                                data-macode="{{ $km->MaCode }}" 
                                data-mota="{{ $km->MoTa }}" 
                                data-loaigiam="{{ $km->LoaiGiam }}"
                                data-giatrigiam="{{ $km->GiaTriGiam }}"
                                data-toithieu="{{ $km->GiaTriDonToiThieu }}"
                                data-soluong="{{ $km->SoLuong }}"
                                data-batdau="{{ $km->NgayBatDau }}"
                                data-ketthuc="{{ $km->NgayKetThuc }}"
                                data-trangthai="{{ $km->TrangThai }}" 
                                title="Chỉnh sửa">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            @if($km->TrangThai == 'HoatDong')
                                <button class="btn btn-sm btn-warning text-dark btn-toggle-status" data-id="{{ $km->ID_KhuyenMai }}" data-status="TamNgung" title="Tạm ngưng">
                                    <i class="fas fa-pause-circle"></i>
                                </button>
                            @else
                                <button class="btn btn-sm btn-success btn-toggle-status" data-id="{{ $km->ID_KhuyenMai }}" data-status="HoatDong" title="Kích hoạt">
                                    <i class="fas fa-play-circle"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4">Không có mã khuyến mãi nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 py-3">{{ $khuyenMais->links('pagination::bootstrap-5') }}</div>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="modalKhuyenMai" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="formKhuyenMai">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">Thêm Mã Khuyến Mãi</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" id="km_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold mb-1">Mã Nhập (Code) *</label>
                            <input type="text" class="form-control text-uppercase" id="MaCode" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold mb-1">Loại Giảm</label>
                            <select class="form-select" id="LoaiGiam">
                                <option value="PhanTram">Giảm theo %</option>
                                <option value="SoTien">Giảm tiền trực tiếp</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold mb-1">Mô Tả Chương Trình</label>
                        <input type="text" class="form-control" id="MoTa">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold mb-1">Giá Trị Giảm *</label>
                            <input type="number" class="form-control" id="GiaTriGiam" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold mb-1">Đơn Tối Thiểu</label>
                            <input type="number" class="form-control" id="GiaTriDonToiThieu" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold mb-1">Số Lượng</label>
                            <input type="number" class="form-control" id="SoLuong" placeholder="Trống = Vô hạn">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold mb-1">Từ Ngày *</label>
                            <input type="date" class="form-control" id="NgayBatDau" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold mb-1">Đến Ngày *</label>
                            <input type="date" class="form-control" id="NgayKetThuc" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold mb-1">Trạng Thái</label>
                            <select class="form-select" id="TrangThai">
                                <option value="HoatDong">Kích Hoạt</option>
                                <option value="TamNgung">Tạm Ngưng</option>
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
    <script src="{{ asset('js/admin/khuyenmai.js') }}"></script>
@endsection