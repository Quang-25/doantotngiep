<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OrderService;
use App\Mail\DonHangDaDuyetMail;
use App\Mail\TraHangMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminDonHangController extends Controller
{
    // 1. Xem danh sách, tìm kiếm và lọc trạng thái (có lọc riêng các bước trả hàng)
    public function quanLyDonHang(Request $request, OrderService $orderService)
    {
        $search = $request->input('search');
        $trangThai = $request->input('trang_thai');

        $query = DB::table('donhang')->select('*');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ID_DonHang', 'LIKE', '%' . $search . '%')
                  ->orWhere('TenNguoiNhan', 'LIKE', '%' . $search . '%')
                  ->orWhere('SoDienThoaiNhan', 'LIKE', '%' . $search . '%');
            });
        }
        $coGhiChu = fn ($q, $dong) => $q->where('GhiChu', 'LIKE', '%' . $dong . '%');
        $khongCo = fn ($q, $dong) => $q->where(fn ($w) => $w->whereNull('GhiChu')->orWhere('GhiChu', 'NOT LIKE', '%' . $dong . '%'));
        if ($trangThai === 'TraHangChoDuyet') {
            $coGhiChu($query, OrderService::TRA_HANG_YEU_CAU);
            $khongCo($query, OrderService::TRA_HANG_CHAP_NHAN);
            $khongCo($query, OrderService::TRA_HANG_TU_CHOI);
        } elseif ($trangThai === 'TraHangChoNhan') {
            $coGhiChu($query, OrderService::TRA_HANG_CHAP_NHAN);
            $khongCo($query, OrderService::TRA_HANG_DA_NHAN);
        } elseif ($trangThai === 'TraHangChoHoanTien') {
            $coGhiChu($query, OrderService::TRA_HANG_DA_NHAN);
            $khongCo($query, OrderService::TRA_HANG_DA_HOAN_TIEN);
        } elseif ($trangThai === 'DaTraHang') {
            $coGhiChu($query, OrderService::TRA_HANG_DA_NHAN);
        } elseif (in_array($trangThai, ['ChoXacNhan', 'DangGiao', 'HoanThanh', 'DaHuy'], true)) {
            $query->where('TrangThaiDon', $trangThai);
        }

        $donHangs = $query->orderBy('ID_DonHang', 'desc')->paginate(10);
        $donHangs->appends(['search' => $search, 'trang_thai' => $trangThai]);

        // Bước trả hàng + lý do của từng đơn để hiện nhãn và nút trên từng dòng
        $traHang = [];
        foreach ($donHangs as $don) {
            $buoc = $orderService->trangThaiTraHang($don);
            if ($buoc !== null) {
                $traHang[$don->ID_DonHang] = [
                    'buoc' => $buoc,
                    'ten' => OrderService::TEN_TRANG_THAI_TRA_HANG[$buoc],
                    'lyDo' => $orderService->ghiChuBuocTraHang($don, OrderService::TRA_HANG_YEU_CAU),
                    'lyDoTuChoi' => $orderService->ghiChuBuocTraHang($don, OrderService::TRA_HANG_TU_CHOI),
                ];
            }
        }

        return view('admin.quanlydonhang', compact('donHangs', 'search', 'trangThai', 'traHang'));
    }

    // 2. Lấy chi tiết đơn hàng (Dùng cho Modal)
    public function chiTietDonHang(int $id)
    {
        $donHang = DB::table('donhang')->where('ID_DonHang', $id)->first();
        
        if (!$donHang) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng!']);
        }

        // Lấy danh sách sản phẩm trong đơn hàng
        $chiTiet = DB::table('chitietdonhang')
            ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->where('chitietdonhang.ID_DonHang', $id)
            ->select('sanpham.TenSanPham', 'sanpham.HinhAnh', 'chitietdonhang.SoLuong', 'chitietdonhang.GiaMua')
            ->get();

        return response()->json([
            'success' => true, 
            'donHang' => $donHang, 
            'chiTiet' => $chiTiet
        ]);
    }

    // 3. Cập nhật trạng thái đơn hàng theo đúng luồng:
    //    Chờ xác nhận -> (Duyệt) Đang giao -> (Hoàn thành) Hoàn thành; hủy được khi chưa hoàn thành.
    public function capNhatTrangThai(Request $request, int $id, OrderService $orderService)
    {
        $tenTrangThai = ['ChoXacNhan' => 'Chờ xác nhận', 'DangGiao' => 'Đang giao', 'HoanThanh' => 'Hoàn thành', 'DaHuy' => 'Đã hủy'];
        $trangThaiMoi = (string) $request->input('TrangThaiDon');
        $donHang = DB::table('donhang')->where('ID_DonHang', $id)->first();

        if (!$donHang) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng!'], 404);
        }
        if (!isset($tenTrangThai[$trangThaiMoi])) {
            return response()->json(['success' => false, 'message' => 'Trạng thái không hợp lệ!'], 422);
        }
        if ($trangThaiMoi === $donHang->TrangThaiDon) {
            return response()->json(['success' => true, 'message' => 'Đơn hàng đang ở trạng thái này, không có gì thay đổi.']);
        }
        if (!$orderService->coTheChuyenTrangThai($donHang->TrangThaiDon, $trangThaiMoi)) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chuyển đơn từ "' . $tenTrangThai[$donHang->TrangThaiDon] . '" sang "' . $tenTrangThai[$trangThaiMoi]
                    . '". Đơn phải đi theo thứ tự: Chờ xác nhận → Đang giao → Hoàn thành.',
            ], 422);
        }
        if ($trangThaiMoi === 'DangGiao' && $donHang->PhuongThucThanhToan === 'VNPay' && !$orderService->daThanhToanVNPay($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn VNPay này chưa thanh toán thành công nên chưa thể duyệt giao hàng.',
            ], 422);
        }

        try {
            if ($trangThaiMoi === 'DaHuy') {
                // Hủy đơn: hoàn kho, trả lượt mã giảm giá.
                $orderService->huyDon($id, 'Shop hủy đơn.');
            } else {
                DB::table('donhang')->where('ID_DonHang', $id)->update(['TrangThaiDon' => $trangThaiMoi]);
                if ($trangThaiMoi === 'HoanThanh') {
                    // Mốc tính hạn trả hàng (khách được yêu cầu trả hàng trong vài ngày sau khi nhận)
                    $orderService->ghiThemGhiChu($id, OrderService::GHI_CHU_GIAO_THANH_CONG . now()->format('d/m/Y H:i'));
                }
            }

            $message = 'Đã chuyển đơn #' . $id . ' sang "' . $tenTrangThai[$trangThaiMoi] . '".';
            if ($trangThaiMoi === 'DangGiao') {
                $message .= $this->guiEmailDaDuyet($id, $orderService)
                    ? ' Đã gửi email thông báo cho khách.'
                    : ' (Chưa gửi được email cho khách.)';
            }

            return response()->json(['success' => true, 'message' => $message], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    // 4. Xử lý yêu cầu trả hàng của khách (đơn đã hoàn thành):
    //    chap_nhan / tu_choi (kèm lý do) -> da_nhan (nhận lại hàng, hoàn kho) -> da_hoan_tien.
    public function xuLyTraHang(Request $request, int $id, OrderService $orderService)
    {
        $hanhDong = (string) $request->input('hanh_dong');
        $donHang = DB::table('donhang')->where('ID_DonHang', $id)->first();
        if (!$donHang) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng!'], 404);
        }
        $buoc = $orderService->trangThaiTraHang($donHang);
        $canBuoc = ['chap_nhan' => 'cho_duyet', 'tu_choi' => 'cho_duyet', 'da_nhan' => 'cho_nhan_hang', 'da_hoan_tien' => 'cho_hoan_tien'];
        if (!isset($canBuoc[$hanhDong])) {
            return response()->json(['success' => false, 'message' => 'Thao tác không hợp lệ.'], 422);
        }
        if ($buoc !== $canBuoc[$hanhDong]) {
            return response()->json(['success' => false, 'message' => 'Đơn đang ở bước "'
                . (OrderService::TEN_TRANG_THAI_TRA_HANG[$buoc] ?? 'chưa có yêu cầu trả hàng') . '", không thể thực hiện thao tác này.'], 422);
        }

        $luc = ' (' . now()->format('d/m/Y H:i') . ')';
        $ghiChuEmail = null;
        try {
            switch ($hanhDong) {
                case 'chap_nhan':
                    $orderService->ghiThemGhiChu($id, OrderService::TRA_HANG_CHAP_NHAN . $luc);
                    $message = 'Đã chấp nhận yêu cầu trả hàng đơn #' . $id . '. Khi nhận lại hàng, bấm "Đã nhận hàng trả".';
                    break;
                case 'tu_choi':
                    $lyDo = trim((string) $request->input('ly_do'));
                    if ($lyDo === '' || mb_strlen($lyDo) > 255) {
                        return response()->json(['success' => false, 'message' => 'Vui lòng nhập lý do từ chối (tối đa 255 ký tự).'], 422);
                    }
                    $orderService->ghiThemGhiChu($id, OrderService::TRA_HANG_TU_CHOI . $luc . '. Lý do: ' . $lyDo);
                    $ghiChuEmail = $lyDo;
                    $message = 'Đã từ chối yêu cầu trả hàng đơn #' . $id . '.';
                    break;
                case 'da_nhan':
                    if (!$orderService->nhanLaiHangTra($id)) {
                        return response()->json(['success' => false, 'message' => 'Không ghi nhận được, vui lòng tải lại trang.'], 422);
                    }
                    $soLuong = (int) DB::table('chitietdonhang')->where('ID_DonHang', $id)->sum('SoLuong');
                    $message = 'Đã nhận lại hàng đơn #' . $id . ', hoàn ' . $soLuong . ' sản phẩm về kho. Nhớ hoàn '
                        . number_format($donHang->ThanhTien, 0, ',', '.') . 'đ cho khách rồi bấm "Đã hoàn tiền".';
                    break;
                default: // da_hoan_tien
                    $orderService->ghiThemGhiChu($id, OrderService::TRA_HANG_DA_HOAN_TIEN . $luc);
                    $message = 'Đã ghi nhận hoàn tiền cho khách đơn #' . $id . '.';
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }

        $donMoi = DB::table('donhang')->where('ID_DonHang', $id)->first();
        $message .= $this->guiEmailTraHang($donMoi, $hanhDong, $ghiChuEmail) ? ' Đã gửi email báo khách.' : ' (Chưa gửi được email cho khách.)';

        return response()->json(['success' => true, 'message' => $message]);
    }

    private function guiEmailTraHang(object $donHang, string $hanhDong, ?string $ghiChu): bool
    {
        try {
            $email = DB::table('khachhang')
                ->join('taikhoan', 'khachhang.ID_TaiKhoan', '=', 'taikhoan.ID_TaiKhoan')
                ->where('khachhang.ID_KhachHang', $donHang->ID_KhachHang)
                ->value('taikhoan.Email');
            if (!$email) {
                return false;
            }
            Mail::to($email)->send(new TraHangMail($donHang, $hanhDong, $ghiChu));

            return true;
        } catch (\Throwable $e) {
            Log::error('Lỗi gửi email trả hàng đơn #' . $donHang->ID_DonHang . ': ' . $e->getMessage());

            return false;
        }
    }

    // Gửi email báo khách là đơn đã được duyệt. Lỗi gửi mail không làm hỏng việc duyệt đơn.
    private function guiEmailDaDuyet(int $id, OrderService $orderService): bool
    {
        try {
            $donHang = DB::table('donhang')->where('ID_DonHang', $id)->first();
            $email = DB::table('khachhang')
                ->join('taikhoan', 'khachhang.ID_TaiKhoan', '=', 'taikhoan.ID_TaiKhoan')
                ->where('khachhang.ID_KhachHang', $donHang->ID_KhachHang)
                ->value('taikhoan.Email');
            if (!$email) {
                return false;
            }

            $sanPhams = DB::table('chitietdonhang')
                ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
                ->where('chitietdonhang.ID_DonHang', $id)
                ->select('sanpham.TenSanPham', 'chitietdonhang.SoLuong', 'chitietdonhang.GiaMua')
                ->get();
            $daThanhToan = $donHang->PhuongThucThanhToan === 'VNPay' && $orderService->daThanhToanVNPay($id);

            Mail::to($email)->send(new DonHangDaDuyetMail($donHang, $sanPhams, $daThanhToan));
            return true;
        } catch (\Throwable $e) {
            Log::error('Lỗi gửi email duyệt đơn #' . $id . ': ' . $e->getMessage());
            return false;
        }
    }
}