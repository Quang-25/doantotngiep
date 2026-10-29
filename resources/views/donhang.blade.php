<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Đơn mua - Badminton Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/donhang.css') }}">
    <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
</head>
<body class="bg-light-gray">
    @include('layouts.header')

    <div class="container mt-4 mb-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="{{ url('/') }}" class="text-decoration-none text-muted">Trang chủ</a></li>
                <li class="breadcrumb-item active fw-medium" aria-current="page">Đơn mua</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-3 text-uppercase" style="font-size: 20px;">Đơn mua của tôi</h4>

        <!-- Tab trạng thái -->
        <div class="order-tabs mb-3" id="order-tabs" role="tablist">
            <button type="button" class="order-tab active" data-status="">Tất cả <span class="tab-count" data-count=""></span></button>
            <button type="button" class="order-tab" data-status="ChoXacNhan">Chờ xác nhận <span class="tab-count" data-count="ChoXacNhan"></span></button>
            <button type="button" class="order-tab" data-status="DangGiao">Đang giao <span class="tab-count" data-count="DangGiao"></span></button>
            <button type="button" class="order-tab" data-status="HoanThanh">Hoàn thành <span class="tab-count" data-count="HoanThanh"></span></button>
            <button type="button" class="order-tab" data-status="DaHuy">Đã hủy <span class="tab-count" data-count="DaHuy"></span></button>
        </div>

        <!-- Danh sách đơn -->
        <div id="order-list">
            <div class="text-center p-5 text-muted">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <h6 class="fw-medium">Đang tải đơn hàng...</h6>
            </div>
        </div>
    </div>

    <!-- Modal chi tiết đơn -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="orderDetailTitle">Chi tiết đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body" id="orderDetailBody"></div>
            </div>
        </div>
    </div>

    <!-- Modal hủy đơn -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Hủy đơn hàng <span id="cancelOrderId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Bạn vui lòng chọn lý do hủy đơn:</p>
                    <div id="cancelReasons">
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoHuy" id="lyDo1" value="Muốn thay đổi địa chỉ / số điện thoại nhận hàng" checked><label class="form-check-label" for="lyDo1">Muốn thay đổi địa chỉ / số điện thoại nhận hàng</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoHuy" id="lyDo2" value="Muốn đổi sản phẩm khác"><label class="form-check-label" for="lyDo2">Muốn đổi sản phẩm khác</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoHuy" id="lyDo3" value="Muốn dùng mã giảm giá khác"><label class="form-check-label" for="lyDo3">Muốn dùng mã giảm giá khác</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoHuy" id="lyDo4" value="Không còn nhu cầu mua"><label class="form-check-label" for="lyDo4">Không còn nhu cầu mua</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoHuy" id="lyDo5" value="khac"><label class="form-check-label" for="lyDo5">Lý do khác</label></div>
                    </div>
                    <textarea class="form-control mt-2 d-none" id="cancelOtherReason" rows="2" maxlength="200" placeholder="Nhập lý do của bạn"></textarea>
                    <div class="text-danger small mt-2 d-none" id="cancelError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Không hủy</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelBtn">Xác nhận hủy đơn</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Yêu cầu trả hàng (đơn đã nhận) -->
    <div class="modal fade" id="returnOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Yêu cầu trả hàng <span id="returnOrderId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="returnDeadline"></p>
                    <p class="mb-2">Bạn vui lòng chọn lý do trả hàng:</p>
                    <div id="returnReasons">
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoTra" id="traLyDo1" value="Sản phẩm bị lỗi / hư hỏng" checked><label class="form-check-label" for="traLyDo1">Sản phẩm bị lỗi / hư hỏng</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoTra" id="traLyDo2" value="Giao sai mẫu, sai size hoặc sai màu"><label class="form-check-label" for="traLyDo2">Giao sai mẫu, sai size hoặc sai màu</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoTra" id="traLyDo3" value="Sản phẩm không giống mô tả"><label class="form-check-label" for="traLyDo3">Sản phẩm không giống mô tả</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoTra" id="traLyDo4" value="Thiếu sản phẩm / phụ kiện đi kèm"><label class="form-check-label" for="traLyDo4">Thiếu sản phẩm / phụ kiện đi kèm</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="lyDoTra" id="traLyDo5" value="Lý do khác"><label class="form-check-label" for="traLyDo5">Lý do khác</label></div>
                    </div>
                    <label for="returnDetail" class="form-label mt-2 mb-1 small">Mô tả thêm <span id="returnDetailRequired" class="text-danger d-none">*</span></label>
                    <textarea class="form-control" id="returnDetail" rows="3" maxlength="500" placeholder="Ví dụ: vợt bị nứt khung ở đầu vợt, giày bị bong keo..."></textarea>
                    <div class="small text-muted mt-2">Sản phẩm trả cần còn nguyên tem, hộp và phụ kiện đi kèm. Shop sẽ hoàn tiền sau khi nhận và kiểm tra hàng.</div>
                    <div class="text-danger small mt-2 d-none" id="returnError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-warning" id="confirmReturnBtn">Gửi yêu cầu trả hàng</button>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.footer')
    <script src="{{ asset('js/donhang.js') }}"></script>
</body>
</html>
