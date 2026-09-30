<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http; 
use Exception; 

class ChatbotService
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function xuLyTinNhan(string $message, string $version, $idKhachHang = null)
    {
        $messageLower = mb_strtolower($message);

        // 1. KỊCH BẢN TRA CỨU ĐƠN HÀNG
        $isTraCuuKeyword = preg_match('/(tra cứu|kiểm tra|xem|tình trạng).*(đơn|mã|sdt|số điện thoại)/i', $messageLower) || preg_match('/(đơn hàng của tôi|tra cuu don hang)/i', $messageLower);
        $isPureNumber = preg_match('/^\d{5,}$/', trim($message)); 

        if ($isTraCuuKeyword || $isPureNumber) {
            if (!$idKhachHang) {
                return "Dạ, bạn cần **đăng nhập tài khoản** trên website để sử dụng tính năng tra cứu đơn hàng cá nhân nhé!";
            }

            if (preg_match('/(\d+)/', $messageLower, $matches)) {
                $tuKhoa = $matches[1];
                $ketQua = $this->traCuuThongMinh($tuKhoa, $idKhachHang); 
                
                if ($ketQua) return $ketQua;
                return "Dạ, hệ thống không tìm thấy đơn hàng nào khớp với thông tin bạn nhập. Bạn kiểm tra lại giúp nhé!";
            } else {
                return "Dạ, để kiểm tra tình trạng đơn, bạn vui lòng cung cấp **Số điện thoại** đã đăng ký nhé! (Ví dụ: '0352143569')";
            }
        }

        // 2. KỊCH BẢN TƯ VẤN SẢN PHẨM
        if (preg_match('/(vợt|vot|phụ kiện|giày|balo|túi|quần|áo|cước|quấn cán|tư vấn|mua)/i', $messageLower)) {
            return $this->tuVanSanPham($messageLower);
        }

        // 3. KỊCH BẢN FAQ
        $faqAnswer = $this->timKiemFAQ($messageLower);
        if ($faqAnswer) return $faqAnswer;

        // 4. KỊCH BẢN MẶC ĐỊNH 
        return "Xin lỗi, hệ thống chưa hiểu ý bạn. Bạn có thể hỏi theo mẫu:<br>👉 <b>Tư vấn:</b> 'Tư vấn vợt Yonex mới chơi' hoặc 'Mua áo Victor'<br>👉 <b>Tra cứu:</b> 'Tra cứu đơn hàng'<br>👉 <b>Hỏi đáp:</b> 'Chính sách bảo hành'";
    }

    private function tuVanSanPham(string $message)
    {
        $query = DB::table('sanpham')->where('sanpham.TrangThai', 'DangBan');

        // JOIN bảng để lấy thông số, thương hiệu và hình ảnh
        $query->leftJoin('thongsovot', 'sanpham.ID_SanPham', '=', 'thongsovot.ID_SanPham')
              ->leftJoin('thuonghieu', 'sanpham.ID_ThuongHieu', '=', 'thuonghieu.ID_ThuongHieu')
              ->leftJoin('hinhanhsanpham', function($join) {
                  $join->on('sanpham.ID_SanPham', '=', 'hinhanhsanpham.ID_SanPham')
                       ->where('hinhanhsanpham.ThuTu', '=', 1);
              });

        // 1. TƯ VẤN THEO LOẠI SẢN PHẨM & PHỤ KIỆN
        if (preg_match('/(vợt|giày|balo|túi|quần|áo|cước|quấn cán)/i', $message, $m)) {
            $loai = mb_strtolower($m[1]);
            if ($loai == 'vot') $loai = 'vợt';
            $query->where('sanpham.TenSanPham', 'like', '%' . $loai . '%');
        }

        // 2. TƯ VẤN THEO THƯƠNG HIỆU
        if (preg_match('/(yonex|victor|lining|mizuno|kumpoo|kawasaki|apacs|vnb)/i', $message, $mBrand)) {
            $query->where('thuonghieu.TenThuongHieu', 'like', '%' . $mBrand[1] . '%');
        }

        // 3. TƯ VẤN THEO NGÂN SÁCH (Dưới X triệu/k)
        if (preg_match('/dưới\s*(\d+(?:\.\d+)?)\s*(triệu|tr|k|nghìn)/i', $message, $m)) {
            $tien = $m[1];
            if (strtolower($m[2]) === 'k' || strtolower($m[2]) === 'nghìn') {
                $query->where('sanpham.GiaBan', '<=', $tien * 1000);
            } else {
                $query->where('sanpham.GiaBan', '<=', $tien * 1000000);
            }
        }

        // 4. TƯ VẤN THEO TRÌNH ĐỘ (TrinhDoPhuHop)
        if (preg_match('/(mới chơi|cơ bản|nhập môn)/i', $message)) {
            $query->where('thongsovot.TrinhDoPhuHop', 'MoiChoi');
        } elseif (preg_match('/(chuyên nghiệp|nâng cao)/i', $message)) {
            $query->whereIn('thongsovot.TrinhDoPhuHop', ['NangCao', 'ChuyenNghiep']);
        } elseif (preg_match('/(trung bình|trung cấp)/i', $message)) {
            $query->where('thongsovot.TrinhDoPhuHop', 'TrungCap');
        }

        // 5. TƯ VẤN THEO LỐI CHƠI ĐẶC TRƯNG
        if (preg_match('/(tấn công|sức mạnh|đập cầu|smash)/i', $message)) {
            $query->where(function($q) {
                $q->where('thongsovot.DiemCanBang', 'like', '%Nặng đầu%')
                  ->orWhere('thongsovot.DoCung', 'like', '%Cứng%');
            });
        } elseif (preg_match('/(phòng thủ|phản tạt|tốc độ|điều cầu)/i', $message)) {
            $query->where(function($q) {
                $q->where('thongsovot.DiemCanBang', 'like', '%Nhẹ đầu%')
                  ->orWhere('thongsovot.DiemCanBang', 'like', '%Cân bằng%');
            });
        } elseif (preg_match('/(toàn diện|công thủ|linh hoạt)/i', $message)) {
            $query->where(function($q) {
                $q->where('thongsovot.DiemCanBang', 'like', '%Cân bằng%')
                  ->orWhere('thongsovot.DoCung', 'like', '%Trung bình%')
                  ->orWhere('thongsovot.DoCung', 'like', '%Dẻo%');
            });
        }

        // 6. TƯ VẤN THEO THÔNG SỐ CỤ THỂ (Trọng lượng, Cân bằng, Độ cứng)
        // Lọc Trọng lượng (2U, 3U, 4U, 5U, F...)
        if (preg_match('/([2-5]u|f)/i', $message, $m)) {
            $query->where('thongsovot.TrongLuong', 'like', '%' . strtoupper($m[1]) . '%');
        }
        // Lọc Điểm cân bằng
        if (preg_match('/(nặng đầu)/i', $message)) {
            $query->where('thongsovot.DiemCanBang', 'like', '%Nặng đầu%');
        } elseif (preg_match('/(nhẹ đầu)/i', $message)) {
            $query->where('thongsovot.DiemCanBang', 'like', '%Nhẹ đầu%');
        } elseif (preg_match('/(cân bằng)/i', $message)) {
            $query->where('thongsovot.DiemCanBang', 'like', '%Cân bằng%');
        }
        // Lọc Độ cứng
        if (preg_match('/(đũa cứng|thân cứng|vợt cứng)/i', $message)) {
            $query->where('thongsovot.DoCung', 'like', '%Cứng%');
        } elseif (preg_match('/(dẻo|thân dẻo)/i', $message)) {
            $query->where('thongsovot.DoCung', 'like', '%Dẻo%');
        }

        $query->select(
            'sanpham.ID_SanPham', 'sanpham.TenSanPham', 'sanpham.GiaBan', 'sanpham.HinhAnh', 
            'hinhanhsanpham.DuongDan as HinhAnhPhu', 'thuonghieu.TenThuongHieu',
            'thongsovot.TrinhDoPhuHop', 'thongsovot.DiemCanBang', 'thongsovot.TrongLuong', 'thongsovot.DoCung'
        );

        // Lấy ngẫu nhiên 3 sản phẩm khớp điều kiện để kết quả tự nhiên
        $sanPhams = $query->inRandomOrder()->limit(3)->get(); 

        if ($sanPhams->isEmpty()) return "Rất tiếc, kho của hệ thống hiện chưa có mẫu nào khớp hoàn toàn với yêu cầu chi tiết này của bạn. Bạn thử thay đổi một vài tiêu chí xem sao nhé!";

        $dataTho = "";
        foreach ($sanPhams as $sp) {
            $gia = number_format($sp->GiaBan, 0, ',', '.');
            
            $thongSoStr = "";
            // Nếu là vợt, hiển thị full thông số
            if (!empty($sp->TrongLuong)) {
                $trinhDo = $sp->TrinhDoPhuHop == 'MoiChoi' ? 'Mới chơi' : ($sp->TrinhDoPhuHop == 'TrungCap' ? 'Trung cấp' : ($sp->TrinhDoPhuHop == 'NangCao' ? 'Nâng cao' : 'Chuyên nghiệp'));
                $thongSoStr = "<br><span style='font-size:12px; color:#555;'><b>Thông số:</b> {$sp->TrongLuong} | {$sp->DiemCanBang} | Độ cứng: {$sp->DoCung} | {$trinhDo}</span>";
            }

            $hinhAnh = !empty($sp->HinhAnh) ? $sp->HinhAnh : $sp->HinhAnhPhu;
            
            if (empty($hinhAnh)) {
                $urlAnh = "/images/no-image.jpg";
            } elseif (preg_match('/^http/i', $hinhAnh)) {
                $urlAnh = $hinhAnh;
            } else {
                $urlAnh = "/images/sanpham/" . $hinhAnh;
            }

            $dataTho .= "<div class='d-flex align-items-center mb-3 p-2 border rounded bg-white shadow-sm'>"
                      . "<img src='{$urlAnh}' style='width: 70px; height: 70px; object-fit: cover; border-radius: 6px; margin-right: 12px; border: 1px solid #eee;'>"
                      . "<div style='flex:1;'>"
                      . "<b style='font-size:14px;'>{$sp->TenSanPham}</b><br>"
                      . "<span style='color: #dc3545; font-weight: bold; font-size: 15px;'>{$gia}đ</span>"
                      . $thongSoStr
                      . "<br><a href='/san-pham/{$sp->ID_SanPham}' target='_blank' class='btn btn-sm btn-outline-primary mt-2' style='font-size:11px; padding: 3px 10px;'>Xem chi tiết</a>"
                      . "</div>"
                      . "</div>";
        }

        return $this->goiGemini($message, $dataTho);
    }

    private function timKiemFAQ(string $message)
    {
        $faqs = DB::table('cauhoichatbot')->where('TrangThai', 'HoatDong')->get();
        foreach ($faqs as $faq) {
            $keywords = explode(',', mb_strtolower($faq->TuKhoa));
            foreach ($keywords as $kw) {
                if (!empty(trim($kw)) && strpos($message, trim($kw)) !== false) {
                    return $faq->CauTraLoi;
                }
            }
        }
        return null;
    }

    private function goiGemini(string $cauHoi, string $dataTho)
    {
        $fallbackReply = "Dạ, hệ thống gợi ý cho bạn các mẫu phù hợp nhất dưới đây ạ:<br><br>" . $dataTho;

        $prompt = "Bạn là tư vấn viên của hệ thống. Khách hỏi: '{$cauHoi}'.\n"
                . "Dưới đây là danh sách sản phẩm KHỚP CHÍNH XÁC với yêu cầu (Đã được định dạng sẵn HTML):\n\n{$dataTho}\n"
                . "Luật BẮT BUỘC phải tuân thủ nghiêm ngặt:\n"
                . "1. Mở đầu bằng một câu chào và tư vấn ngắn gọn, lịch sự, đúng trọng tâm câu hỏi.\n"
                . "2. TUYỆT ĐỐI GIỮ NGUYÊN VẸN toàn bộ khối HTML chứa hình ảnh <div class='d-flex...'>...</div> mà tôi cung cấp. Không được tự ý xóa thẻ <img> hay <a>.\n"
                . "3. Không tự bịa thêm thông số, không bịa tên sản phẩm ngoài danh sách trên.";

        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . env('GEMINI_API_KEY');
            
            $response = Http::timeout(8)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.1 
                    ]
                ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text') ?? $fallbackReply;
            }
            
            return $fallbackReply;

        } catch (Exception $e) {
            return $fallbackReply;
        }
    }

    private function traCuuThongMinh(string $tuKhoa, ?int $idKhachHang)
    {
        // 1. TÌM THÔNG TIN TÀI KHOẢN TRONG BẢNG KHACHHANG
        $khachHang = DB::table('khachhang')->where('ID_KhachHang', $idKhachHang)->first();
        
        $query = DB::table('donhang')->where('ID_KhachHang', $idKhachHang); 
        
        if (strlen($tuKhoa) >= 9) {
            $sdtNhap = ltrim($tuKhoa, '0');
            $sdtTaiKhoan = ltrim($khachHang->SoDienThoai ?? '', '0');
            
            // 2. CHỐT CHẶN BẢO MẬT: Bắt buộc SĐT gõ vào chat phải bằng SĐT đăng ký account
            if ($sdtNhap !== $sdtTaiKhoan) {
                return "🚫 Dạ, số điện thoại **{$tuKhoa}** không khớp với Số điện thoại đăng ký của tài khoản này. Hệ thống từ chối tra cứu để bảo mật thông tin ạ!";
            }

            // Nếu đúng thì mới cho phép truy vấn đơn hàng
            $query->where('SoDienThoaiNhan', 'like', '%' . $sdtNhap);
        } else {
            $query->where('ID_DonHang', $tuKhoa);
        }

        $donHang = $query->orderBy('NgayDat', 'desc')->first();

        if (!$donHang) return null;

        $mapTrangThai = [
            'ChoXacNhan' => '<span style="color:#ffc107; font-weight:bold;">Chờ xác nhận ⏳</span>',
            'DangGiao'   => '<span style="color:#0d6efd; font-weight:bold;">Đang giao hàng 🚚</span>',
            'HoanThanh'  => '<span style="color:#198754; font-weight:bold;">Đã hoàn thành ✅</span>',
            'DaHuy'      => '<span style="color:#dc3545; font-weight:bold;">Đã hủy ❌</span>'
        ];
        $tt = $mapTrangThai[$donHang->TrangThaiDon] ?? $donHang->TrangThaiDon;
        
        $tongTien = number_format($donHang->ThanhTien, 0, ',', '.');
        $ngayDat = date('d/m/Y H:i', strtotime($donHang->NgayDat));

        return "<div class='p-2 border rounded bg-white shadow-sm'>"
             . "📦 <b>Thông tin đơn hàng của bạn:</b><br>"
             . "▪️ <b>Mã đơn:</b> #{$donHang->ID_DonHang}<br>"
             . "▪️ <b>Ngày đặt:</b> {$ngayDat}<br>"
             . "▪️ <b>Người nhận:</b> {$donHang->TenNguoiNhan}<br>"
             . "▪️ <b>Số điện thoại:</b> {$donHang->SoDienThoaiNhan}<br>"
             . "▪️ <b>Tổng tiền:</b> <b style='color:#dc3545;'>{$tongTien}đ</b><br>"
             . "▪️ <b>Trạng thái:</b> {$tt}"
             . "</div>";
    }
}