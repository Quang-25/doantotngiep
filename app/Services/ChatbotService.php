<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder;
use Exception;

class ChatbotService
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    // Vẫn giữ tham số $version để Controller gọi không bị lỗi, nhưng không còn dùng để rẽ nhánh nữa
    public function xuLyTinNhan(string $message, string $version = 'pro', $idKhachHang = null)
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

        // Tra cứu size giày, quần áo và váy trước khi chạy tư vấn sản phẩm chung.
        if (preg_match('/(size|kích cỡ|cỡ giày|mặc cỡ|mặc size|mang cỡ|mang size)/iu', $messageLower)
            && preg_match('/(giày|giay|quần|áo|váy|đầm)/iu', $messageLower)) {
            return $this->traCuuSizeSanPham($messageLower);
        }

        // 2. KỊCH BẢN TƯ VẤN SẢN PHẨM TRONG KHO
        if (preg_match('/(vợt|vot|phụ kiện|giày|balo|túi|quần|áo|váy|đầm|cước|quấn cán|mua)/iu', $messageLower)
            || preg_match('/(sản phẩm|mặt hàng).*(giá|dưới|trên|tầm|khoảng)/iu', $messageLower)) {
            $isNotOrderOrPolicy = !preg_match('/(đơn|bảo hành|đổi trả|thanh toán)/i', $messageLower);
            if ($isNotOrderOrPolicy) {
                return $this->tuVanSanPham($messageLower); // Đã xóa biến $version truyền vào
            }
        }

        // 3. KỊCH BẢN FAQ (Dò nhanh trong DB để tiết kiệm thời gian)
        $faqAnswer = $this->timKiemFAQ($messageLower);
        if ($faqAnswer) {
            return $faqAnswer;
        }

        // 4. KỊCH BẢN HỎI ĐÁP TỰ NHIÊN VỚI GEMINI
        // Mặc định mọi câu hỏi không khớp từ khóa đều được AI xử lý mượt mà
        return $this->troChuyenTuNhienVoiGemini($message);
    }

    // ==========================================
    // CÁC HÀM XỬ LÝ CHI TIẾT
    // ==========================================

    private function traCuuSizeSanPham(string $message)
    {
        $loaiSanPham = [];
        if (preg_match('/(giày|giay)/iu', $message)) $loaiSanPham[] = 'giày';
        if (preg_match('/(quần|áo)/iu', $message)) $loaiSanPham[] = 'quần áo';
        if (preg_match('/(váy|đầm)/iu', $message)) $loaiSanPham[] = 'váy';

        $size = null;
        if (preg_match('/(?:size|cỡ)\s*[:\-]?\s*(\d{2}|xxs|xxxl|xxl|xs|xl|s|m|l)\b/iu', $message, $matches)) {
            $size = mb_strtoupper($matches[1]);
        }

        $coCotSize = Schema::hasColumn('sanpham', 'SizeCoSan');
        $query = DB::table('sanpham')
            ->leftJoin('hinhanhsanpham', function ($join) {
                $join->on('sanpham.ID_SanPham', '=', 'hinhanhsanpham.ID_SanPham')
                    ->where('hinhanhsanpham.ThuTu', '=', 1);
            })
            ->where('sanpham.TrangThai', 'DangBan')
            ->where(function ($q) use ($loaiSanPham) {
                foreach ($loaiSanPham as $loai) {
                    if ($loai === 'quần áo') {
                        $q->orWhere('sanpham.TenSanPham', 'like', '%quần%')
                            ->orWhere('sanpham.TenSanPham', 'like', '%áo%');
                    } else {
                        $q->orWhere('sanpham.TenSanPham', 'like', '%' . $loai . '%');
                    }
                }
            });

        if ($size !== null && $coCotSize) {
            $query->where('sanpham.SizeCoSan', 'like', '%' . $size . '%');
        }

        $select = [
            'sanpham.ID_SanPham',
            'sanpham.TenSanPham',
            'sanpham.GiaBan',
            'sanpham.GiaKhuyenMai',
            'sanpham.HinhAnh',
            'hinhanhsanpham.DuongDan as HinhAnhPhu',
        ];
        if ($coCotSize) {
            $select[] = 'sanpham.SizeCoSan';
        }

        $sanPhams = $query->select($select)
            ->distinct()
            ->orderBy('sanpham.TenSanPham')
            ->limit(5)
            ->get();

        if ($sanPhams->isNotEmpty()) {
            $danhSach = '';
            foreach ($sanPhams as $sanPham) {
                $giaHienTai = !empty($sanPham->GiaKhuyenMai)
                    && $sanPham->GiaKhuyenMai > 0
                    && $sanPham->GiaKhuyenMai < $sanPham->GiaBan
                    ? $sanPham->GiaKhuyenMai
                    : $sanPham->GiaBan;
                $giaHtml = '<span style="color:#dc3545;font-weight:bold;">'
                    . number_format($giaHienTai, 0, ',', '.') . 'đ</span>';

                if ($giaHienTai < $sanPham->GiaBan) {
                    $giaHtml = '<del style="color:#999;margin-right:6px;">'
                        . number_format($sanPham->GiaBan, 0, ',', '.') . 'đ</del>' . $giaHtml;
                }

                $hinhAnh = $sanPham->HinhAnh ?: $sanPham->HinhAnhPhu;
                if (empty($hinhAnh)) {
                    $urlAnh = '/images/no-image.jpg';
                } elseif (preg_match('/^https?:\/\//i', $hinhAnh)) {
                    $urlAnh = $hinhAnh;
                } else {
                    $urlAnh = '/images/sanpham/' . ltrim($hinhAnh, '/');
                }

                $sizeHtml = $coCotSize && !empty($sanPham->SizeCoSan)
                    ? '<br><span>Size có sẵn: ' . e($sanPham->SizeCoSan) . '</span>'
                    : '<br><span>Vui lòng xem size trên trang chi tiết sản phẩm.</span>';
                $danhSach .= "<div class='d-flex align-items-center mb-3 p-2 border rounded bg-white shadow-sm'>"
                    . "<img src='" . e($urlAnh) . "' alt='" . e($sanPham->TenSanPham) . "' style='width:70px;height:70px;object-fit:contain;border-radius:6px;margin-right:12px;border:1px solid #eee;'>"
                    . "<div style='flex:1;min-width:0;'>"
                    . "<b style='font-size:14px;'>" . e($sanPham->TenSanPham) . "</b><br>"
                    . $giaHtml . $sizeHtml
                    . "<br><a href='/san-pham/" . (int) $sanPham->ID_SanPham . "' class='btn btn-sm btn-outline-primary mt-2' style='font-size:11px;padding:3px 10px;'>Xem chi tiết</a>"
                    . "</div></div>";
            }

            $phanHoi = 'Dạ, đây là các sản phẩm' . ($size ? ' có size ' . e($size) : ' với thông tin size') . ':<br><br>'
                . $danhSach;
            if (!$coCotSize) {
                $phanHoi .= '<br>Hiện cửa hàng chưa có dữ liệu size trực tiếp trên hệ thống; bạn vui lòng xem chi tiết sản phẩm để kiểm tra size.';
            }
            return $phanHoi;
        }

        if ($size !== null && $coCotSize) {
            return 'Dạ, hiện chưa tìm thấy sản phẩm' . ($loaiSanPham ? ' ' . e(implode(', ', $loaiSanPham)) : '')
                . ' có size ' . e($size) . ' trong kho.';
        }

        return 'Dạ, hiện chưa tìm thấy sản phẩm phù hợp trong kho. Bạn có thể xem các mẫu đang bán ở trang sản phẩm nhé.';
    }

    private function tuVanSanPham(string $message)
    {
        $query = DB::table('sanpham')->where('sanpham.TrangThai', 'DangBan');

        $query->leftJoin('thongsovot', 'sanpham.ID_SanPham', '=', 'thongsovot.ID_SanPham')
            ->leftJoin('thuonghieu', 'sanpham.ID_ThuongHieu', '=', 'thuonghieu.ID_ThuongHieu')
            ->leftJoin('hinhanhsanpham', function ($join) {
                $join->on('sanpham.ID_SanPham', '=', 'hinhanhsanpham.ID_SanPham')
                    ->where('hinhanhsanpham.ThuTu', '=', 1);
            });

        if (preg_match('/quần\s*áo/iu', $message)) {
            $query->where(function ($q) {
                $q->where('sanpham.TenSanPham', 'like', '%quần%')
                    ->orWhere('sanpham.TenSanPham', 'like', '%áo%')
                    ->orWhere('sanpham.MoTa', 'like', '%quần%')
                    ->orWhere('sanpham.MoTa', 'like', '%áo%');
            });
        } elseif (preg_match('/(vợt|giày|giay|balo|túi|quần|áo|váy|đầm|cước|quấn cán)/iu', $message, $m)) {
            $loai = mb_strtolower($m[1]);
            if ($loai == 'vot') $loai = 'vợt';
            if ($loai === 'giay') $loai = 'giày';
            if (in_array($loai, ['quần', 'áo', 'váy', 'đầm'], true)) {
                $query->where(function ($q) use ($loai) {
                    $q->where('sanpham.TenSanPham', 'like', '%' . $loai . '%')
                        ->orWhere('sanpham.MoTa', 'like', '%' . $loai . '%');
                });
            } else {
                $query->where('sanpham.TenSanPham', 'like', '%' . $loai . '%');
            }
            
            if ($loai === 'vợt') {
                $query->whereNotNull('thongsovot.ID_SanPham');
            }
        }

        if (preg_match('/(yonex|victor|lining|mizuno|kumpoo|kawasaki|apacs|vnb)/i', $message, $mBrand)) {
            $query->where('thuonghieu.TenThuongHieu', 'like', '%' . $mBrand[1] . '%');
        }

        $this->apDungBoLocGia($query, $message);

        if (preg_match('/(mới chơi|người mới|mới tập|cơ bản|nhập môn)/i', $message)) {
            $query->where('thongsovot.TrinhDoPhuHop', 'MoiChoi');
        } elseif (preg_match('/(chuyên nghiệp|nâng cao)/i', $message)) {
            $query->whereIn('thongsovot.TrinhDoPhuHop', ['NangCao', 'ChuyenNghiep']);
        } elseif (preg_match('/(trung bình|trung cấp)/i', $message)) {
            $query->where('thongsovot.TrinhDoPhuHop', 'TrungCap');
        }

        if (preg_match('/(tấn công|sức mạnh|đập cầu|smash)/i', $message)) {
            $query->where(function ($q) {
                $q->where('thongsovot.DiemCanBang', 'like', '%Nặng đầu%')
                    ->orWhere('thongsovot.DoCung', 'like', '%Cứng%');
            });
        } elseif (preg_match('/(phòng thủ|phản tạt|tốc độ|điều cầu)/i', $message)) {
            $query->where(function ($q) {
                $q->where('thongsovot.DiemCanBang', 'like', '%Nhẹ đầu%')
                    ->orWhere('thongsovot.DiemCanBang', 'like', '%Cân bằng%');
            });
        } elseif (preg_match('/(toàn diện|công thủ|linh hoạt)/i', $message)) {
            $query->where(function ($q) {
                $q->where('thongsovot.DiemCanBang', 'like', '%Cân bằng%')
                    ->orWhere('thongsovot.DoCung', 'like', '%Trung bình%')
                    ->orWhere('thongsovot.DoCung', 'like', '%Dẻo%');
            });
        }

        if (preg_match('/([2-5]u|f)/i', $message, $m)) {
            $query->where('thongsovot.TrongLuong', 'like', '%' . strtoupper($m[1]) . '%');
        }

        if (preg_match('/(nặng đầu)/i', $message)) {
            $query->where('thongsovot.DiemCanBang', 'like', '%Nặng đầu%');
        } elseif (preg_match('/(nhẹ đầu)/i', $message)) {
            $query->where('thongsovot.DiemCanBang', 'like', '%Nhẹ đầu%');
        } elseif (preg_match('/(cân bằng)/i', $message)) {
            $query->where('thongsovot.DiemCanBang', 'like', '%Cân bằng%');
        }

        if (preg_match('/(đũa cứng|thân cứng|vợt cứng)/i', $message)) {
            $query->where('thongsovot.DoCung', 'like', '%Cứng%');
        } elseif (preg_match('/(dẻo|thân dẻo)/i', $message)) {
            $query->where('thongsovot.DoCung', 'like', '%Dẻo%');
        }

        // FIX: Bổ sung lấy thêm cột GiaKhuyenMai
        $query->select(
            'sanpham.ID_SanPham',
            'sanpham.TenSanPham',
            'sanpham.GiaBan',
            'sanpham.GiaKhuyenMai', 
            'sanpham.HinhAnh',
            'hinhanhsanpham.DuongDan as HinhAnhPhu',
            'thuonghieu.TenThuongHieu',
            'thongsovot.TrinhDoPhuHop',
            'thongsovot.DiemCanBang',
            'thongsovot.TrongLuong',
            'thongsovot.DoCung'
        );

        $sanPhams = $query->inRandomOrder()->limit(3)->get();

        if ($sanPhams->isEmpty()) return "Rất tiếc, kho của hệ thống hiện chưa có mẫu nào khớp hoàn toàn với yêu cầu chi tiết này của bạn. Bạn thử thay đổi một vài tiêu chí xem sao nhé!";

        $dataTho = "";
        foreach ($sanPhams as $sp) {
            // FIX: LOGIC XỬ LÝ HIỂN THỊ GIÁ SALE & GIÁ GỐC
            if (!empty($sp->GiaKhuyenMai) && $sp->GiaKhuyenMai > 0 && $sp->GiaKhuyenMai < $sp->GiaBan) {
                $giaGocFmt = number_format($sp->GiaBan, 0, ',', '.');
                $giaSaleFmt = number_format($sp->GiaKhuyenMai, 0, ',', '.');
                // Gạch ngang giá gốc, bôi đỏ giá sale
                $chuoiGiaHtml = "<span style='text-decoration: line-through; color: #999; font-size: 12px; margin-right: 6px;'>{$giaGocFmt}đ</span>"
                              . "<span style='color: #dc3545; font-weight: bold; font-size: 15px;'>{$giaSaleFmt}đ</span>";
            } else {
                $giaBanFmt = number_format($sp->GiaBan, 0, ',', '.');
                $chuoiGiaHtml = "<span style='color: #dc3545; font-weight: bold; font-size: 15px;'>{$giaBanFmt}đ</span>";
            }

            $thongSoStr = "";
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
                . $chuoiGiaHtml // Chèn HTML giá đã xử lý vào đây
                . $thongSoStr
                . "<br><a href='/san-pham/{$sp->ID_SanPham}' target='_blank' class='btn btn-sm btn-outline-primary mt-2' style='font-size:11px; padding: 3px 10px;'>Xem chi tiết</a>"
                . "</div>"
                . "</div>";
        }

        return $this->goiGemini($message, $dataTho);
    }

    private function apDungBoLocGia(Builder $query, string $message): void
    {
        $amountPattern = '(\d+(?:[.,]\d{3})*(?:[.,]\d+)?)\s*(triệu|tr|nghìn|ngàn|k|đồng|đ)?';

        if (preg_match('/(?:dưới|thấp hơn|nhỏ hơn|ít hơn|không quá|tối đa)\s*' . $amountPattern . '/iu', $message, $matches)) {
            $mucGia = $this->chuyenGiaSangVnd($matches[1], $matches[2] ?? '');
            $query->whereRaw(
                'IF(sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai IS NOT NULL, sanpham.GiaKhuyenMai, sanpham.GiaBan) <= ?',
                [$mucGia]
            );
            return;
        }

        if (preg_match('/(?:trên|cao hơn|lớn hơn|từ)\s*' . $amountPattern . '(?:\s*trở lên)?/iu', $message, $matches)) {
            $mucGia = $this->chuyenGiaSangVnd($matches[1], $matches[2] ?? '');
            $query->whereRaw(
                'IF(sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai IS NOT NULL, sanpham.GiaKhuyenMai, sanpham.GiaBan) >= ?',
                [$mucGia]
            );
            return;
        }

        if (preg_match('/(?:khoảng giá|tầm giá|tầm|khoảng)\s*(?:là\s*)?' . $amountPattern . '/iu', $message, $matches)) {
            $mucGia = $this->chuyenGiaSangVnd($matches[1], $matches[2] ?? '');
            $query->whereRaw(
                'IF(sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai IS NOT NULL, sanpham.GiaKhuyenMai, sanpham.GiaBan) BETWEEN ? AND ?',
                [$mucGia * 0.9, $mucGia * 1.1]
            );
            return;
        }

        if (preg_match('/giá\s*(?:là\s*)?' . $amountPattern . '/iu', $message, $matches)) {
            $mucGia = $this->chuyenGiaSangVnd($matches[1], $matches[2] ?? '');
            $query->whereRaw(
                'IF(sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai IS NOT NULL, sanpham.GiaKhuyenMai, sanpham.GiaBan) = ?',
                [$mucGia]
            );
            return;
        }

        if (preg_match('/' . $amountPattern . '/iu', $message, $matches) && !empty($matches[2])) {
            $mucGia = $this->chuyenGiaSangVnd($matches[1], $matches[2]);
            $query->whereRaw(
                'IF(sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai IS NOT NULL, sanpham.GiaKhuyenMai, sanpham.GiaBan) = ?',
                [$mucGia]
            );
        }
    }

    private function chuyenGiaSangVnd(string $amount, string $unit): float
    {
        $unit = mb_strtolower($unit);
        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $amount)) {
            $value = (float) preg_replace('/[.,]/', '', $amount);
        } else {
            $value = (float) str_replace(',', '.', $amount);
        }

        if (in_array($unit, ['triệu', 'tr'], true)) return $value * 1000000;
        if (in_array($unit, ['nghìn', 'ngàn', 'k'], true)) return $value * 1000;
        if ($unit === '' && $value <= 10000) return $value * 1000;

        return $value;
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

            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.1]
                ]);

            if ($response->successful()) {
                return $response->json('candidates.0.content.parts.0.text') ?? $fallbackReply;
            }
            return $fallbackReply;
        } catch (Exception $e) {
            return $fallbackReply;
        }
    }

    private function troChuyenTuNhienVoiGemini(string $message)
    {
        $faqs = DB::table('cauhoichatbot')->where('TrangThai', 'HoatDong')->get();
        $nguCanh = "";
        foreach ($faqs as $faq) {
            $nguCanh .= "Hỏi: {$faq->TuKhoa} | Trả lời: {$faq->CauTraLoi}\n";
        }

        $prompt = "Bạn là trợ lý ảo thân thiện của cửa hàng cầu lông Badminton ProShop.\n"
            . "Khách hàng nhắn: '{$message}'\n\n"
            . "DƯỚI ĐÂY LÀ THÔNG TIN CHÍNH SÁCH CỦA CỬA HÀNG:\n"
            . "{$nguCanh}\n\n"
            . "LUẬT TRẢ LỜI:\n"
            . "1. Nếu khách chào hỏi, hãy chào lại một cách nhiệt tình.\n"
            . "2. Nếu khách hỏi về chính sách bảo hành, thanh toán, đổi trả... hãy tìm câu trả lời trong phần 'THÔNG TIN CHÍNH SÁCH CỦA CỬA HÀNG' ở trên và diễn đạt lại thật tự nhiên.\n"
            . "3. KHÔNG tự bịa đặt chính sách nếu thông tin trên không đề cập. Nếu không biết, hãy bảo khách liên hệ Hotline.\n"
            . "4. Trả lời định dạng HTML cơ bản (dùng <br> xuống dòng, <b> in đậm nếu cần). Không dùng markdown (**). Ngắn gọn dưới 3 câu.";

        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . env('GEMINI_API_KEY');

            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.5
                    ]
                ]);

            if ($response->successful()) {
                $text = $response->json('candidates.0.content.parts.0.text');
                if ($text) {
                    return str_replace(['**', '*'], ['<b>', '</b>'], $text);
                }
            }

            return "Dạ, hiện tại AI đang xử lý hơi nhiều yêu cầu. Bạn cần hỗ trợ gấp có thể liên hệ Hotline cửa hàng nhé!";
        } catch (Exception $e) {
            return "Xin lỗi bạn, máy chủ tư vấn đang tạm gián đoạn kết nối. Bạn thử lại sau ít phút nhé!";
        }
    }

    private function traCuuThongMinh(string $tuKhoa, ?int $idKhachHang)
    {
        $khachHang = DB::table('khachhang')->where('ID_KhachHang', $idKhachHang)->first();

        $query = DB::table('donhang')->where('ID_KhachHang', $idKhachHang);

        if (strlen($tuKhoa) >= 9) {
            $sdtNhap = ltrim($tuKhoa, '0');
            $sdtTaiKhoan = ltrim($khachHang->SoDienThoai ?? '', '0');

            if ($sdtNhap !== $sdtTaiKhoan) {
                return "🚫 Dạ, số điện thoại **{$tuKhoa}** không khớp với Số điện thoại đăng ký của tài khoản này. Hệ thống từ chối tra cứu để bảo mật thông tin ạ!";
            }
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