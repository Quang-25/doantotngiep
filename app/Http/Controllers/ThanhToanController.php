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

        return view('thanhtoan', compact('khachHang', 'cartItems', 'tongTien'));
    }
    
    public function processCheckout(Request $request)
    {
        DB::beginTransaction();
        try {
            $phuongThucRequest = trim($request->phuong_thuc);
            $trangThaiDon = ($phuongThucRequest === 'VNPay') ? 'ChoXacNhan' : 'DangGiao';
            $phuongThucDonHang = ($phuongThucRequest === 'VNPay') ? 'VNPay' : 'COD';

            $idDonHang = DB::table('DonHang')->insertGetId([
                'ID_KhachHang' => $request->id_khachhang,
                'TongTien' => $request->tong_tien,
                'ThanhTien' => $request->tong_tien,
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
                    'SoTien' => $request->tong_tien,
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
                $vnp_Url = $this->createVNPayUrl($idDonHang, $request->tong_tien, $request->ip());
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
            DB::table('DonHang')->where('ID_DonHang', $vnp_TxnRef)->update(['TrangThaiDon' => 'DangGiao']);   
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
            return redirect('/')->with('success', 'Thanh toán đơn hàng qua VNPay thành công! Vui lòng kiểm tra email.');
        } else {
            DB::table('DonHang')->where('ID_DonHang', $vnp_TxnRef)->update(['TrangThaiDon' => 'DaHuy']);
            DB::table('ThanhToan')->where('ID_DonHang', $vnp_TxnRef)->update(['TrangThaiGiaoDich' => 'ThatBai']);

            return redirect('/thanhtoan')->with('error', 'Giao dịch thanh toán đã bị hủy. Đơn hàng đã cập nhật thành đã hủy!');
        }
    }

    private function sendOrderEmail($idDonHang)
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

    private function createVNPayUrl(int $idDonHang, float $amount, string $ipAddress)
    {
        $vnp_TmnCode = env('VNPAY_TMN_CODE', 'YOUR_TMN_CODE'); 
        $vnp_HashSecret = env('VNPAY_HASH_SECRET', 'YOUR_HASH_SECRET'); 
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = env('VNPAY_RETURN_URL', 'http://127.0.0.1:8000/thanh-toan-thanh-cong');

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $amount * 100,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $ipAddress,
            "vnp_Locale" => "vn",
            "vnp_OrderInfo" => "Khach hang thanh toan $idDonHang",
            "vnp_OrderType" => "billpayment",
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $idDonHang,
        );

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        return $vnp_Url;
    }
}