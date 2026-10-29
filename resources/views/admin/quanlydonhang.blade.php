@extends('admin.layout')

@section('css')
    <!-- Gọi file CSS tách rời đã tạo ở bước trước -->
    <link rel="stylesheet" href="{{ asset('css/admin/donhang.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="container-fluid p-0">
    <h3 class="mb-4 fw-bold">Quản Lý Đơn Hàng</h3>

    <!-- Thanh tìm kiếm (Đồng bộ 100% với Sản phẩm / Khách hàng) -->
    <div class="d-flex justify-content-between mb-3">
        <form action="/admin/don-hang" method="GET" class="d-flex search-form gap-2" style="max-width: 640px; width: 100%;">
            <input type="text" name="search" class="form-control" placeholder="Tìm mã đơn, Tên hoặc SĐT..." value="{{ request('search') }}">
            <select name="trang_thai" class="form-select" style="max-width: 210px;" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="ChoXacNhan" @selected($trangThai === 'ChoXacNhan')>Chờ xác nhận</option>
                <option value="DangGiao" @selected($trangThai === 'DangGiao')>Đang giao</option>
                <option value="HoanThanh" @selected($trangThai === 'HoanThanh')>Hoàn thành</option>
                <option value="DaHuy" @selected($trangThai === 'DaHuy')>Đã hủy (tất cả)</option>
                <optgroup label="Trả hàng">
                    <option value="TraHangChoDuyet" @selected($trangThai === 'TraHangChoDuyet')>Yêu cầu trả hàng chờ duyệt</option>
                    <option value="TraHangChoNhan" @selected($trangThai === 'TraHangChoNhan')>Đã chấp nhận - chờ nhận hàng</option>
                    <option value="TraHangChoHoanTien" @selected($trangThai === 'TraHangChoHoanTien')>Đã nhận hàng - chờ hoàn tiền</option>
                    <option value="DaTraHang" @selected($trangThai === 'DaTraHang')>Đã trả hàng (tất cả)</option>
                </optgroup>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
            @if(request('search') || $trangThai)
                <a href="/admin/don-hang" class="btn btn-outline-secondary ms-2" title="Hủy tìm kiếm"><i class="fas fa-times"></i></a>
            @endif
        </form>
    </div>

    <!-- Bảng Danh Sách Đơn Hàng -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã Đơn</th>
                        <th>Người Nhận</th>
                        <th>Ngày Đặt</th>
                        <th>Thanh Toán</th>
                        <th>Tổng Tiền</th>
                        <th>Trạng Thái</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($donHangs as $don)
                    <tr>
                        <td class="fw-bold">#{{ $don->ID_DonHang }}</td>
                        <td>
                            <b class="text-primary">{{ $don->TenNguoiNhan }}</b><br>
                            <small class="text-muted">{{ $don->SoDienThoaiNhan }}</small>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($don->NgayDat)->format('d/m/Y H:i') }}</td>
                        <td><span class="badge bg-secondary">{{ $don->PhuongThucThanhToan }}</span></td>
                        <td class="fw-bold text-danger">{{ number_format($don->ThanhTien, 0, ',', '.') }}đ</td>
                        <td>
                            @if($don->TrangThaiDon == 'ChoXacNhan') 
                                <span class="badge bg-warning text-dark badge-status">Chờ Xác Nhận</span>
                            @elseif($don->TrangThaiDon == 'DangGiao') 
                                <span class="badge bg-primary badge-status">Đang Giao</span>
                            @elseif($don->TrangThaiDon == 'HoanThanh') 
                                <span class="badge bg-success badge-status">Hoàn Thành</span>
                            @elseif(isset($traHang[$don->ID_DonHang]))
                                <span class="badge bg-danger badge-status">Đã Trả Hàng</span>
                            @else
                                <span class="badge bg-secondary badge-status">Đã Hủy</span>
                            @endif
                            @if(isset($traHang[$don->ID_DonHang]))
                                @php $th = $traHang[$don->ID_DonHang]; @endphp
                                <br><span class="badge mt-1 {{ match ($th['buoc']) { 'cho_duyet' => 'bg-warning text-dark', 'tu_choi' => 'bg-secondary', 'da_hoan_tien' => 'bg-success', default => 'bg-info text-dark' } }}">
                                    <i class="fas fa-undo"></i> {{ $th['ten'] }}
                                </span>
                                @if($th['lyDo'])
                                    <br><small class="text-muted">Lý do: {{ $th['lyDo'] }}</small>
                                @endif
                                @if($th['buoc'] === 'tu_choi' && $th['lyDoTuChoi'])
                                    <br><small class="text-muted">Từ chối: {{ $th['lyDoTuChoi'] }}</small>
                                @endif
                            @endif
                        </td>
                        <td>
                            <!-- Nút Xem chi tiết -->
                            <button class="btn btn-sm btn-primary btn-view-order me-1" data-id="{{ $don->ID_DonHang }}" title="Xem chi tiết">
                                <i class="fas fa-eye"></i>
                            </button>

                            <!-- Nút Cập nhật nhanh thông minh (Thay đổi theo Trạng thái) -->
                            @if($don->TrangThaiDon == 'ChoXacNhan')
                                <!-- Nếu đang Chờ xác nhận -> Hiện nút Duyệt -->
                                <button class="btn btn-sm btn-info text-white btn-update-status" data-id="{{ $don->ID_DonHang }}" data-status="DangGiao" title="Duyệt và giao hàng">
                                    <i class="fas fa-check"></i> Duyệt
                                </button>
                            @elseif($don->TrangThaiDon == 'DangGiao')
                                <!-- Nếu đang Giao hàng -> Hiện nút Hoàn thành -->
                                <button class="btn btn-sm btn-success text-white btn-update-status" data-id="{{ $don->ID_DonHang }}" data-status="HoanThanh" title="Xác nhận giao thành công">
                                    <i class="fas fa-check-circle"></i> Hoàn thành
                                </button>
                            @endif

                            <!-- Xử lý yêu cầu trả hàng của khách theo từng bước -->
                            @php $buocTraHang = $traHang[$don->ID_DonHang]['buoc'] ?? null; @endphp
                            @if($buocTraHang === 'cho_duyet')
                                <button class="btn btn-sm btn-success btn-return-action mt-1" data-id="{{ $don->ID_DonHang }}" data-action="chap_nhan" title="Chấp nhận cho khách trả hàng">
                                    <i class="fas fa-check"></i> Chấp nhận trả
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-return-action mt-1" data-id="{{ $don->ID_DonHang }}" data-action="tu_choi" title="Từ chối yêu cầu trả hàng">
                                    <i class="fas fa-times"></i> Từ chối
                                </button>
                            @elseif($buocTraHang === 'cho_nhan_hang')
                                <button class="btn btn-sm btn-info text-white btn-return-action mt-1" data-id="{{ $don->ID_DonHang }}" data-action="da_nhan" title="Shop đã nhận lại hàng khách trả (hoàn về kho)">
                                    <i class="fas fa-box-open"></i> Đã nhận hàng trả
                                </button>
                            @elseif($buocTraHang === 'cho_hoan_tien')
                                <button class="btn btn-sm btn-warning btn-return-action mt-1" data-id="{{ $don->ID_DonHang }}" data-action="da_hoan_tien" data-amount="{{ number_format($don->ThanhTien, 0, ',', '.') }}" title="Ghi nhận đã hoàn tiền cho khách">
                                    <i class="fas fa-money-bill-wave"></i> Đã hoàn tiền
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4">Không có đơn hàng nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-0 py-3">{{ $donHangs->links('pagination::bootstrap-5') }}</div>
    </div>
</div>

<!-- Modal Chi Tiết Đơn Hàng -->
<div class="modal fade" id="modalDonHang" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="formDonHang">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Chi Tiết Đơn Hàng <span id="md_MaDon" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <input type="hidden" id="dh_id">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-primary mb-3"><i class="fas fa-map-marker-alt"></i> Thông tin giao hàng</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2"><b>Người nhận:</b> <span id="md_NguoiNhan"></span></div>
                                <div class="col-md-6 mb-2"><b>Điện thoại:</b> <span id="md_SDT"></span></div>
                                <div class="col-12 mb-2"><b>Địa chỉ:</b> <span id="md_DiaChi"></span></div>
                                <div class="col-12 text-danger"><b>Ghi chú:</b> <span id="md_GhiChu"></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-0">
                            <table class="table table-borderless mb-0">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th colspan="2">Sản phẩm</th>
                                        <th class="text-center">Số lượng</th>
                                        <th class="text-end">Đơn giá</th>
                                    </tr>
                                </thead>
                                <tbody id="order-items-container"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold m-0">Trạng thái hiện tại:</h6>
                            <select class="form-select w-50 fw-bold" id="TrangThaiDon" name="TrangThaiDon">
                                <option value="ChoXacNhan">Chờ Xác Nhận</option>
                                <option value="DangGiao">Đang Giao Hàng</option>
                                <option value="HoanThanh">Hoàn Thành (Đã giao)</option>
                                <option value="DaHuy">Hủy Đơn Hàng</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveStatus">Lưu Trạng Thái</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
    <script src="{{ asset('js/admin/donhang.js') }}"></script>
@endsection