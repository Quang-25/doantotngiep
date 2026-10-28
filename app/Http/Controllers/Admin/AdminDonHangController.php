<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OrderService;
use App\Mail\DonHangDaDuyetMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminDonHangController extends Controller
{
    // 1. Xem danh sách và Tìm kiếm
    public function quanLyDonHang(Request $request)
    {
        $search = $request->input('search');

        $query = DB::table('donhang')->select('*');

        if (!empty($search)) {
            $query->where('ID_DonHang', 'LIKE', '%' . $search . '%')
                  ->orWhere('TenNguoiNhan', 'LIKE', '%' . $search . '%')
                  ->orWhere('SoDienThoaiNhan', 'LIKE', '%' . $search . '%');
        }

        $donHangs = $query->orderBy('ID_DonHang', 'desc')->paginate(10);
        $donHangs->appends(['search' => $search]);

        return view('admin.quanlydonhang', compact('donHangs', 'search'));
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