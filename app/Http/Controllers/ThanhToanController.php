<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\XacNhanDonHangMail;

class ThanhToanController extends Controller
{
    public function index()
    {
        $idTaiKhoan = session('ID_TaiKhoan');
        
        if (!$idTaiKhoan) {
            return redirect('/thanhtoan')->with('error', 'Vui lòng đăng nhập để tiến hành thanh toán.');
        }

        $khachHang = DB::table('KhachHang')
            ->join('TaiKhoan', 'KhachHang.ID_TaiKhoan', '=', 'TaiKhoan.ID_TaiKhoan')
            ->where('TaiKhoan.ID_TaiKhoan', $idTaiKhoan)
            ->select('KhachHang.*', 'TaiKhoan.Email')
            ->first();

        if (!$khachHang) {
            return redirect('/')->with('error', 'Không tìm thấy hồ sơ khách hàng của bạn. Vui lòng cập nhật thông tin!');
        }

        $cartItems = DB::table('ChiTietGioHang')
            ->join('GioHang', 'ChiTietGioHang.ID_GioHang', '=', 'GioHang.ID_GioHang')
            ->join('SanPham', 'ChiTietGioHang.ID_SanPham', '=', 'SanPham.ID_SanPham')
            ->where('GioHang.ID_KhachHang', $khachHang->ID_KhachHang)
            ->where('ChiTietGioHang.DaChon', 1)
            ->select('SanPham.ID_SanPham', 'SanPham.TenSanPham', 'SanPham.GiaBan', 'SanPham.GiaKhuyenMai', 'ChiTietGioHang.SoLuong')
            ->get();

        $tongTien = 0;
        foreach ($cartItems as $item) {
            $giaThucTe = $item->GiaKhuyenMai ?? $item->GiaBan;
            $tongTien += $giaThucTe * $item->SoLuong;
            $item->GiaThucTe = $giaThucTe;
        }

        // Lấy danh sách mã khuyến mãi hợp lệ hiển thị ra Popup
        $today = date('Y-m-d');
        $danhSachKhuyenMai = DB::table('KhuyenMai')
            ->where('TrangThai', 'HoatDong')
            ->where('NgayBatDau', '<=', $today)
            ->where('NgayKetThuc', '>=', $today)
            ->where(function($query) {
                $query->whereNull('SoLuong')->orWhere('SoLuong', '>', 0);
            })
            ->get();

        return view('thanhtoan', compact('khachHang', 'cartItems', 'tongTien', 'danhSachKhuyenMai'));
    }
    
    public function processCheckout(Request $request)
    {
        DB::beginTransaction();
        try {
            $phuongThucRequest = trim($request->phuong_thuc);
            // Mọi đơn mới đều chờ shop xác nhận; admin duyệt mới chuyển sang Đang giao.
            $trangThaiDon = 'ChoXacNhan';
            $phuongThucDonHang = ($phuongThucRequest === 'VNPay') ? 'VNPay' : 'COD';

            // XỬ LÝ TÍNH TOÁN KHUYẾN MÃI BẢO MẬT TRÊN SERVER
            $tongTien = $request->tong_tien;
            $tienGiam = 0;
            $thanhTien = $tongTien;
            $idKhuyenMai = $request->id_khuyenmai ?? null;

            if ($idKhuyenMai) {
                $km = DB::table('KhuyenMai')->where('ID_KhuyenMai', $idKhuyenMai)->where('TrangThai', 'HoatDong')->first();
                if ($km) {
                    $tienGiam = ($km->LoaiGiam == 'PhanTram') ? ($tongTien * $km->GiaTriGiam) / 100 : $km->GiaTriGiam;
                    if ($tienGiam > $tongTien) $tienGiam = $tongTien; // Không giảm âm tiền
                    $thanhTien = $tongTien - $tienGiam;

                    // Trừ đi 1 lượt sử dụng của mã khuyến mãi
                    if ($km->SoLuong > 0) {
                        DB::table('KhuyenMai')->where('ID_KhuyenMai', $idKhuyenMai)->decrement('SoLuong', 1);
                    }
                } else {
                    $idKhuyenMai = null; // Trùng hợp mã bị khóa trong lúc đang đặt hàng
                }
            }

            $idDonHang = DB::table('DonHang')->insertGetId([
                'ID_KhachHang' => $request->id_khachhang,
                'ID_KhuyenMai' => $idKhuyenMai,
                'TongTien' => $tongTien,
                'TienGiam' => $tienGiam,
                'ThanhTien' => $thanhTien,
                'TenNguoiNhan' => $request->ten_nguoi_nhan,
                'SoDienThoaiNhan' => $request->so_dien_thoai,
                'DiaChiGiaoHang' => $request->dia_chi,
                'PhuongThucThanhToan' => $phuongThucDonHang, 
                'TrangThaiDon' => $trangThaiDon,
                'NgayDat' => now(),
            ]);

            foreach ($request->cart_items as $item) {
                DB::table('ChiTietDonHang')->insert([
                    'ID_DonHang' => $idDonHang,
                    'ID_SanPham' => $item['id_sanpham'],
                    'SoLuong' => $item['so_luong'],
                    'GiaMua' => $item['gia_mua'], 
                ]);

                if ($phuongThucRequest !== 'VNPay') {
                    DB::table('SanPham')
                        ->where('ID_SanPham', $item['id_sanpham'])
                        ->decrement('SoLuongTon', $item['so_luong']);

                    $sanPhamCheck = DB::table('SanPham')->where('ID_SanPham', $item['id_sanpham'])->first();
                    if ($sanPhamCheck && $sanPhamCheck->SoLuongTon <= 0) {
                        DB::table('SanPham')
                            ->where('ID_SanPham', $item['id_sanpham'])
                            ->update(['TrangThai' => 'NgungKinhDoanh']);
                    }
                }
            }

            if ($phuongThucRequest === 'VNPay') {
                DB::table('ThanhToan')->insert([
                    'ID_DonHang' => $idDonHang,
                    'SoTien' => $thanhTien, // VNPay thanh toán số tiền đã trừ khuyến mãi
                    'PhuongThuc' => 'VNPay',
                    'TrangThaiGiaoDich' => 'ChoThanhToan',
                    'NgayGiaoDich' => now(),
                ]);
            }

            if ($phuongThucRequest !== 'VNPay') {
                $gioHang = DB::table('GioHang')->where('ID_KhachHang', $request->id_khachhang)->first();
                if ($gioHang) {
                    DB::table('ChiTietGioHang')->where('ID_GioHang', $gioHang->ID_GioHang)->delete();
                }
            }

            DB::commit();

            if ($phuongThucRequest === 'VNPay') {
                $vnp_Url = $this->createVNPayUrl($idDonHang, $thanhTien, $request->ip());
                return response()->json([
                    'status' => 'success',
                    'message' => 'Xác thực thành công, đang chuyển hướng...',
                    'redirect_url' => $vnp_Url
                ], 200);
            }

            $this->sendOrderEmail($idDonHang);
            return response()->json(['status' => 'success', 'message' => 'Đặt hàng COD thành công! Vui lòng kiểm tra email.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi hệ thống thanh toán: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Xử lý thất bại! ' . $e->getMessage()], 500);
        }
    }
    
    public function vnpayReturn(Request $request)
    {
        $vnp_ResponseCode = $request->get('vnp_ResponseCode');
        $vnp_TxnRef = $request->get('vnp_TxnRef');

        if ($vnp_ResponseCode == '00') {
            // Đã thanh toán nhưng đơn vẫn Chờ xác nhận cho tới khi admin duyệt.
            DB::table('ThanhToan')->where('ID_DonHang', $vnp_TxnRef)->update(['TrangThaiGiaoDich' => 'ThanhCong']);
            
            $chiTietDonHang = DB::table('ChiTietDonHang')->where('ID_DonHang', $vnp_TxnRef)->get();
            foreach ($chiTietDonHang as $item) {
                DB::table('SanPham')
                    ->where('ID_SanPham', $item->ID_SanPham)
                    ->decrement('SoLuongTon', $item->SoLuong);

                $sanPhamCheck = DB::table('SanPham')->where('ID_SanPham', $item->ID_SanPham)->first();
                if ($sanPhamCheck && $sanPhamCheck->SoLuongTon <= 0) {
                    DB::table('SanPham')
                        ->where('ID_SanPham', $item->ID_SanPham)
                        ->update(['TrangThai' => 'NgungKinhDoanh']);
                }
            }

            $donHang = DB::table('DonHang')->where('ID_DonHang', $vnp_TxnRef)->first();
            if ($donHang) {
                $gioHang = DB::table('GioHang')->where('ID_KhachHang', $donHang->ID_KhachHang)->first();
                if ($gioHang) {
                    DB::table('ChiTietGioHang')->where('ID_GioHang', $gioHang->ID_GioHang)->delete();
                }
            }

            $this->sendOrderEmail($vnp_TxnRef);
            return redirect('/don-hang')->with('success', 'Thanh toán đơn hàng qua VNPay thành công! Vui lòng kiểm tra email.');
        } else {
            DB::table('DonHang')->where('ID_DonHang', $vnp_TxnRef)->update(['TrangThaiDon' => 'DaHuy']);
            DB::table('ThanhToan')->where('ID_DonHang', $vnp_TxnRef)->update(['TrangThaiGiaoDich' => 'ThatBai']);

            return redirect('/thanhtoan')->with('error', 'Giao dịch thanh toán đã bị hủy. Đơn hàng đã cập nhật thành đã hủy!');
        }
    }

    public function theoDoiDonHang()
    {
        $idTaiKhoan = session('ID_TaiKhoan');
        if (!$idTaiKhoan) {
            return redirect('/Dangnhap')->with('error', 'Vui lòng đăng nhập để xem đơn hàng.');
        }

        $khachHang = DB::table('KhachHang')->where('ID_TaiKhoan', $idTaiKhoan)->first();
        if (!$khachHang) {
            return redirect('/')->with('error', 'Không tìm thấy hồ sơ khách hàng.');
        }

        $donHangs = DB::table('DonHang')
            ->where('ID_KhachHang', $khachHang->ID_KhachHang)
            ->orderBy('NgayDat', 'desc')
            ->get();

        return view('theo-doi-don-hang', compact('donHangs'));
    }

    private function sendOrderEmail(int $idDonHang)
    {
        try {
            $donHang = DB::table('DonHang')->where('ID_DonHang', $idDonHang)->first();
            if ($donHang) {
                $khachHang = DB::table('KhachHang')
                    ->join('TaiKhoan', 'KhachHang.ID_TaiKhoan', '=', 'TaiKhoan.ID_TaiKhoan')
                    ->where('KhachHang.ID_KhachHang', $donHang->ID_KhachHang)
                    ->first();
                
                if ($khachHang && $khachHang->Email) {
                    Mail::to($khachHang->Email)->send(new XacNhanDonHangMail($donHang));
                }
            }
        } catch (\Exception $e) {
            Log::error('Lỗi gửi email xác nhận đơn hàng: ' . $e->getMessage());
        }
    }


    private function createVNPayUrl(int $idDonHang, float $amount, string $ipAddress): string
    {
        $vnp_TmnCode = trim((string) env('VNPAY_TMN_CODE', ''));
        $vnp_HashSecret = trim((string) env('VNPAY_HASH_SECRET', ''));
        $vnp_Url = trim((string) env(
            'VNPAY_URL',
            'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'
        ));
        $vnp_Returnurl = trim((string) env('VNPAY_RETURN_URL', ''));

        if (
            $vnp_TmnCode === '' ||
            $vnp_HashSecret === '' ||
            $vnp_Url === '' ||
            $vnp_Returnurl === ''
        ) {
            throw new \RuntimeException('Thiếu cấu hình VNPay.');
        }

        if (!filter_var($vnp_Returnurl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('VNPAY_RETURN_URL không hợp lệ.');
        }

        $isProduction = app()->environment('production');

        if (
            $isProduction &&
            strtolower((string) parse_url($vnp_Returnurl, PHP_URL_SCHEME)) !== 'https'
        ) {
            throw new \RuntimeException(
                'VNPAY_RETURN_URL phải dùng HTTPS trên môi trường production.'
            );
        }

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => $vnp_TmnCode,
            'vnp_Amount' => (int) round($amount * 100),
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $ipAddress,
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang ' . $idDonHang,
            'vnp_OrderType' => 'billpayment',
            'vnp_ReturnUrl' => $vnp_Returnurl,
            'vnp_TxnRef' => (string) $idDonHang,
        ];

        ksort($inputData);

        $hashData = [];
        $queryData = [];

        foreach ($inputData as $key => $value) {
            $encodedKey = urlencode((string) $key);
            $encodedValue = urlencode((string) $value);

            $hashData[] = $encodedKey . '=' . $encodedValue;
            $queryData[] = $encodedKey . '=' . $encodedValue;
        }

        $secureHash = hash_hmac(
            'sha512',
            implode('&', $hashData),
            $vnp_HashSecret
        );

        return $vnp_Url . '?' . implode('&', $queryData)
            . '&vnp_SecureHash=' . $secureHash;
    }
}