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

    // Vẫn giữ tham số $version để Controller gọi không bị lỗi, nhưng không còn dùng để rẽ nhánh nữa.
    // $history: các tin nhắn trước trong cùng phiên chat [['sender' => 'KhachHang'|'Chatbot', 'content' => ...]].
    public function xuLyTinNhan(string $message, string $version = 'pro', $idKhachHang = null, array $history = [])
    {
        $messageLower = mb_strtolower(trim($message));

        // 0. CHỌN TRONG CÁC MẪU VỪA GỢI Ý ("tôi chơi chuyên nghiệp, chọn cho tôi trong các mẫu bên trên")
        if ($this->laChonTrongMauDaGoiY($messageLower)) {
            $pickReply = $this->chonTrongMauDaGoiY($message, $history);
            if ($pickReply !== null) {
                return $pickReply;
            }
        }

        // 0''. CHÀO HỎI / CHÍNH SÁCH CỬA HÀNG / KIẾN THỨC CẦU LÔNG: trả lời có sẵn, không cần Gemini
        $cannedReply = $this->traLoiCauHoiCoSan($messageLower);
        if ($cannedReply !== null) {
            return $cannedReply;
        }

        // 0'a. HỎI MỘT LOẠI VỢT / GIÀY CÒN HÀNG KHÔNG ("vợt yonex còn hàng không")
        // => hiện các mẫu còn hàng + số lượng, rồi hỏi tiếp để tư vấn chọn mẫu.
        $stockReply = $this->lietKeConHangTheoLoai($message, $messageLower);
        if ($stockReply !== null) {
            return $stockReply;
        }

        // 0'. HỎI DANH SÁCH VỢT THEO TRÌNH ĐỘ ("cho tôi các loại vợt phù hợp người chơi chuyên nghiệp")
        // => chỉ liệt kê vợt đúng trình độ, rồi hỏi tiếp lối chơi / ngân sách để tư vấn chọn một cây.
        $levelListReply = $this->lietKeVotTheoTrinhDo($message, $messageLower);
        if ($levelListReply !== null) {
            return $levelListReply;
        }

        // 0a. KỊCH BẢN SO SÁNH SẢN PHẨM (hai mẫu khách nêu tên, các mẫu vừa tư vấn, hoặc ngẫu nhiên)
        if ($this->laYeuCauSoSanh($messageLower)) {
            $comparison = $this->soSanhSanPham($message, $messageLower, $history);
            if ($comparison !== null) {
                return $comparison;
            }
        }

        // 0a2. CÂU HỎI CÓ Ý ĐỊNH RÕ (khuyến mãi, mã giảm giá, thông tin một mẫu, rẻ/đắt nhất, thương hiệu)
        $intentReply = $this->xuLyTheoYDinh($message, $messageLower);
        if ($intentReply !== null) {
            return $intentReply;
        }

        // 0b. ĐANG HỎI LÀM RÕ NHU CẦU: khách trả lời câu hỏi của chatbot
        $phienTuVan = $this->layPhienTuVan($history);
        if ($phienTuVan !== null) {
            $interviewReply = $this->xuLyTraLoiPhienTuVan($message, $phienTuVan);
            if ($interviewReply !== null) {
                return $interviewReply;
            }
        }

        // 0c. VỪA TƯ VẤN XONG: khách hỏi mẫu khác, rẻ hơn, chọn giữa các mẫu, hoặc đổi tiêu chí
        $afterAdviceReply = $this->xuLySauKetQuaTuVan($message, $history);
        if ($afterAdviceReply !== null) {
            return $afterAdviceReply;
        }

        // 0d. KHÁCH NÊU NHU CẦU (vợt / giày / phụ kiện) => tư vấn theo nhu cầu, hỏi làm rõ nếu còn thiếu
        $loaiTuVan = $this->layLoaiTuVanBatDau($messageLower);
        if ($loaiTuVan !== null) {
            return $this->tiepTucTuVan($loaiTuVan, $this->chuanHoaTraLoiTuVan($message, null), false, false, true);
        }

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
                if ($this->laYeuCauVot($messageLower)
                    && preg_match('/(astrox|nanoflare|arcsaber|duora|voltric|thruster|ryuga|axforce|power cushion|jetspeed|auraspeed|halbertec|tectonic|\b\d{2,3}z+\d*\b)/iu', $messageLower) !== 1) {
                    return $this->tiepTucTuVan(
                        'vot',
                        $this->chuanHoaTraLoiTuVan($message, null),
                        false,
                        false,
                        true
                    );
                }
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
    // TƯ VẤN THEO NHU CẦU KHÁCH HÀNG (vợt, giày, phụ kiện)
    // Luồng: khách nêu nhu cầu -> chatbot hỏi làm rõ -> khách trả lời -> chatbot phân tích nhu cầu,
    // nói rõ nên chọn loại nào và vì sao -> giới thiệu 1-2 mẫu kèm lý do -> kết luận
    // -> khách hỏi tiếp (so sánh, mẫu khác, rẻ hơn, đổi tiêu chí).
    // Câu hỏi của chatbot có marker ẩn <!--tuvan:loai:y_dang_hoi-->; kết quả tư vấn có
    // <!--tuvan-loai:..--><!--tuvan-ketqua:(nhu cầu, base64)--><!--tuvan-da-xem:id,id--> để nhớ ngữ cảnh.
    // ==========================================

    // Các ý cần hỏi theo thứ tự cho từng loại sản phẩm.
    private const KICH_BAN_TU_VAN = [
        'vot' => ['trinh_do', 'loi_choi', 'ngan_sach', 'chi_tiet'],
        'giay' => ['giay_size', 'giay_nhu_cau', 'ngan_sach'],
        'phu_kien' => ['pk_loai', 'pk_nhu_cau'],
        'quan_ao' => ['qa_loai', 'qa_size', 'qa_nhu_cau'],
    ];

    // Ý hỏi thêm cho sâu: nếu khách nói đủ ý chính ngay câu đầu thì tư vấn luôn và chỉ mời bổ sung.
    private const Y_HOI_THEM = ['chi_tiet'];

    // Khách trả lời "không biết / sao cũng được" thì dùng giá trị mặc định này.
    private const MAC_DINH_KHI_BO_QUA = [
        'trinh_do' => 'trình độ chưa rõ',
        'loi_choi' => 'lối chơi toàn diện',
        'ngan_sach' => 'không giới hạn ngân sách',
        'chi_tiet' => 'chơi phong trào',
        'giay_size' => 'size chưa rõ',
        'giay_nhu_cau' => 'nhu cầu chung',
        'pk_loai' => 'bộ phụ kiện cơ bản',
        'pk_nhu_cau' => 'nhu cầu chung',
        'qa_loai' => 'bộ quần áo',
        'qa_size' => 'size chưa rõ',
        'qa_nhu_cau' => 'nhu cầu chung',
    ];

    private const THUONG_HIEU_PATTERN = '/(yonex|victor|lining|li-ning|mizuno|kumpoo|kawasaki|apacs|vnb)/iu';

    // "1-2 triệu", "từ 2 đến 3 triệu", "500k - 1tr" (vế sau bắt buộc có đơn vị tiền).
    private const KHOANG_GIA_PATTERN = '/(?:từ|khoảng|tầm)?\s*(\d+(?:[.,]\d+)?)\s*(triệu|tr|củ|nghìn|ngàn|k)?\s*(?:-|–|đến|tới)\s*(\d+(?:[.,]\d+)?)\s*(triệu|tr|củ|nghìn|ngàn|k)(?!\p{L})/iu';

    // Nhu cầu khách nói => từ khóa tìm trong mô tả sản phẩm của cửa hàng (lý do luôn dựa trên dữ liệu thật).
    private const NHU_CAU_THEO_MO_TA = [
        ['for' => ['giay'], 'label' => 'êm, giảm chấn', 'need' => '/((?<!\p{L})êm(?!\p{L})|giảm chấn|đau gối|đau gót|khớp|bật nhảy)/iu', 'desc' => ['giảm chấn', 'êm', 'đệm', 'hấp thụ', 'nhận sốc']],
        ['for' => ['giay'], 'label' => 'nhẹ, linh hoạt', 'need' => '/(nhẹ|linh hoạt|tốc độ|nhanh|bứt tốc)/iu', 'desc' => ['linh hoạt', 'tốc độ', 'nhẹ', 'bứt tốc']],
        ['for' => ['giay', 'cuoc'], 'label' => 'độ bền cao', 'need' => '/(bền|mòn|đứt|chơi nhiều|xi măng|ngoài trời)/iu', 'desc' => ['bền', 'mài mòn', 'chịu lực']],
        ['for' => ['giay'], 'label' => 'thoáng khí', 'need' => '/(thoáng|nóng|mồ hôi chân|hầm)/iu', 'desc' => ['thoáng']],
        ['for' => ['giay'], 'label' => 'ổn định, ôm chân', 'need' => '/(ổn định|lật|chắc chân|ôm chân|cổ chân|thon|hẹp)/iu', 'desc' => ['ổn định', 'ôm', 'cổ giày']],
        ['for' => ['giay'], 'label' => 'hợp chơi phong trào', 'need' => '/(phong trào|giải trí|mới chơi|người mới|tập luyện)/iu', 'desc' => ['phong trào', 'mọi trình độ', 'mọi người', 'tập luyện']],
        ['for' => ['giay'], 'label' => 'hợp thi đấu', 'need' => '/(thi đấu|giải|chuyên nghiệp)/iu', 'desc' => ['thi đấu', 'cao cấp']],
        ['for' => ['cuoc'], 'label' => 'hỗ trợ lực, giảm sốc', 'need' => '/(trợ lực|tay yếu|lực yếu|đau tay|đau khuỷu|chấn thương|mới chơi|người mới)/iu', 'desc' => ['hỗ trợ lực', 'hấp thụ sốc']],
        ['for' => ['cuoc'], 'label' => 'tiếng nổ đanh, cảm giác nảy', 'need' => '/(nảy|tiếng nổ|âm thanh|đanh|cảm giác)/iu', 'desc' => ['âm thanh', 'vang', 'đanh', 'nảy']],
        ['for' => ['quan_can'], 'label' => 'thấm hút mồ hôi, chống trơn', 'need' => '/(mồ hôi|trơn|ướt|bám)/iu', 'desc' => ['thấm hút', 'mồ hôi', 'bám', 'trượt tay']],
        ['for' => ['quan_can'], 'label' => 'độ dày vừa tay', 'need' => '/(dày|mỏng|cầm chắc)/iu', 'desc' => ['độ dày', 'dày']],
        ['for' => ['bao_vot'], 'label' => 'đựng vợt và phụ kiện', 'need' => '/(nhiều đồ|nhiều vợt|\d+\s*(?:cây\s*)?vợt|giày|quần áo|ngăn|đựng)/iu', 'desc' => ['đựng', 'ngăn', 'phụ kiện']],
        ['for' => ['qua_cau'], 'label' => 'cầu bền, đánh ngoài trời', 'need' => '/(ngoài trời|cầu nhựa|nhựa|tập)/iu', 'desc' => ['nhựa', 'ngoài trời', 'độ bền']],
        ['for' => ['qua_cau'], 'label' => 'đường cầu chuẩn', 'need' => '/(trong nhà|lông vũ|thi đấu|chuẩn)/iu', 'desc' => ['tốc độ bay', 'cao cấp']],
        ['for' => ['quan_ao'], 'label' => 'thấm hút mồ hôi, thoáng mát', 'need' => '/(thấm hút|mồ hôi|(?<!\p{L})mát(?!\p{L})|thoáng|nóng|tập luyện|đi tập)/iu', 'desc' => ['thấm hút', 'mát', 'thoáng']],
        ['for' => ['quan_ao'], 'label' => 'co giãn, thoải mái vận động', 'need' => '/(co giãn|thoải mái|vận động|dễ chịu|mềm|bật nhảy)/iu', 'desc' => ['co giãn', 'thoải mái', 'mềm mại', 'êm ái']],
        ['for' => ['quan_ao'], 'label' => 'giữ ấm, chống lạnh', 'need' => '/(giữ ấm|(?<!\p{L})ấm(?!\p{L})|lạnh|mùa đông|chống gió|khởi động)/iu', 'desc' => ['chống lạnh', 'lót bông', 'giữ ấm', '2 lớp']],
        ['for' => ['quan_ao'], 'label' => 'lớp bảo hộ bên trong', 'need' => '/(bảo hộ|kín đáo|tự tin|(?<!\p{L})lộ(?!\p{L})|quần trong)/iu', 'desc' => ['bảo hộ', 'tự tin']],
        ['for' => ['quan_ao'], 'label' => 'hợp thi đấu', 'need' => '/(thi đấu|đấu giải|(?<!\p{L})giải(?!\p{L})|lên sân)/iu', 'desc' => ['thi đấu', 'lên sân']],
        ['for' => ['quan_ao'], 'label' => 'giá phải chăng', 'need' => '/((?<!\p{L})rẻ|giá mềm|phải chăng|tiết kiệm|sinh viên)/iu', 'desc' => ['phải chăng', 'giá rẻ', 'giá tốt']],
    ];

    // Bảng size đồ thể thao (đến cân nặng kg / chiều cao cm thì mặc size đó).
    private const BANG_SIZE_QUAN_AO = [
        'nam' => ['S' => [55, 165], 'M' => [62, 170], 'L' => [70, 175], 'XL' => [78, 180], 'XXL' => [999, 999]],
        'nu' => ['XS' => [42, 150], 'S' => [48, 155], 'M' => [54, 160], 'L' => [60, 165], 'XL' => [66, 170], 'XXL' => [999, 999]],
    ];

    // ---------- Đọc nhu cầu từ câu chữ của khách (câu nói sau ghi đè câu nói trước) ----------

    // Trả về giá trị của mẫu được nhắc tới SAU CÙNG trong đoạn text.
    private function layGiaTriSauCung(string $text, array $patterns): ?string
    {
        $best = null;
        $bestOffset = -1;
        foreach ($patterns as $value => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $offset = end($matches[0])[1];
                if ($offset > $bestOffset) {
                    $bestOffset = $offset;
                    $best = (string) $value;
                }
            }
        }

        return $best;
    }

    // Product data supports only MoiChoi / NangCao / ChuyenNghiep.
    private function layMaTrinhDo(string $text): ?string
    {
        $lower = mb_strtolower($text);
        $level = $this->layGiaTriSauCung($lower, [
            'MoiChoi' => '/(người mới|mới chơi|mới tập|nhập môn|newbie|chưa biết chơi|tập chơi)/iu',
            'NangCao' => '/nâng cao/iu',
            'ChuyenNghiep' => '/chuyên nghiệp/iu',
            'ChuaRo' => '/trình độ chưa rõ/iu',
        ]);

        return $level;
    }

    private function tenTrinhDo(?string $level): string
    {
        return match ($level) {
            'MoiChoi' => 'người mới chơi',
            'NangCao' => 'trình độ nâng cao',
            'ChuyenNghiep' => 'trình độ chuyên nghiệp',
            'ChuaRo' => 'chưa rõ trình độ',
            default => '',
        };
    }

    private function layLoiChoi(string $text): ?string
    {
        return $this->layGiaTriSauCung(mb_strtolower($text), [
            'tan_cong' => '/(tấn công|đập cầu|smash|thiên công|đánh công|sức mạnh)/iu',
            'phong_thu' => '/(phòng thủ|thiên thủ|phản tạt|đỡ cầu)/iu',
            'toan_dien' => '/(toàn diện|công thủ|cả công lẫn thủ)/iu',
        ]);
    }

    // Đánh đơn/đôi, mục đích (phong trào/thi đấu), số buổi mỗi tuần.
    private function layCachChoi(string $text): array
    {
        $lower = mb_strtolower($text);
        $frequency = null;
        if (preg_match_all('/(\d+)\s*(?:buổi|lần|hôm)/iu', $lower, $matches)) {
            $frequency = (int) end($matches[1]);
        } elseif (preg_match('/(hàng ngày|mỗi ngày)/iu', $lower)) {
            $frequency = 7;
        }

        return [
            'format' => $this->layGiaTriSauCung($lower, [
                'doi' => '/(?:đánh|chơi)\s*đôi/iu',
                'don' => '/(?:đánh|chơi)\s*đơn/iu',
                'ca_hai' => '/cả đơn (?:và|lẫn) đôi|cả đơn lẫn đôi|cả đơn|cả đôi/iu',
            ]),
            'purpose' => $this->layGiaTriSauCung($lower, [
                'thi_dau' => '/(thi đấu|đánh giải|giải đấu)/iu',
                'phong_trao' => '/(phong trào|giải trí|tập luyện|câu lạc bộ|\bclb\b)/iu',
            ]),
            'frequency' => $frequency,
        ];
    }

    // 'dau_tay' / 'yeu' / 'khoe' / 'binh_thuong' hoặc null.
    private function layTheLuc(string $text): ?string
    {
        return $this->layGiaTriSauCung(mb_strtolower($text), [
            'dau_tay' => '/((?:đau|chấn thương).{0,12}(?:khuỷu|cổ tay|tay|vai))/iu',
            'yeu' => '/(lực tay yếu|cổ tay yếu|tay yếu|lực yếu|ít lực|lực tay kém)/iu',
            'khoe' => '/(lực tay tốt|(?:lực tay|tay|cổ tay)\s*(?:khỏe|khoẻ|mạnh|tốt))/iu',
            'binh_thuong' => '/((?:lực tay|tay)\s*(?:bình thường|trung bình|ổn))/iu',
        ]);
    }

    // Cỡ cán theo bàn tay (cột ChuViTayCam): 'G4' tay to, 'G5' tay nhỏ, hoặc null.
    private function layCoCan(string $text): ?string
    {
        return $this->layGiaTriSauCung(mb_strtolower($text), [
            'G4' => '/(\bg4\b|(?:bàn tay|tay)\s*(?:hơi|khá|rất)?\s*(?:to|lớn|dày)(?!\p{L}))/iu',
            'G5' => '/(\bg5\b|(?:bàn tay|tay)\s*(?:hơi|khá|rất)?\s*(?:nhỏ|bé)(?!\p{L}))/iu',
        ]);
    }

    // Mức căng tối đa ghi trên vợt, ví dụ "20-28 lbs" => 28.
    private function layMucCangToiDa(string $tension): int
    {
        preg_match_all('/\d+/', $tension, $matches);

        return $matches[0] ? max(array_map('intval', $matches[0])) : 0;
    }

    private function muonVotNhe(string $text): bool
    {
        return preg_match('/((?<!đầu )nhẹ(?! đầu)(?! hơn)|dễ điều khiển|dễ đánh|đỡ mỏi)/iu', mb_strtolower($text)) === 1;
    }

    // Ngân sách khách nêu sau cùng: ['min' => ?float, 'max' => ?float, 'label' => string] hoặc null.
    private function layNganSach(string $text): ?array
    {
        $lower = mb_strtolower($text);
        $amount = '(\d+(?:[.,]\d+)?)\s*(triệu|tr|củ|nghìn|ngàn|k)(?!\p{L})';
        $found = [];
        $collect = function (string $pattern, callable $build) use ($lower, &$found) {
            if (preg_match_all($pattern, $lower, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $found[$match[0][1]] = $build($match);
                }
            }
        };
        $money = fn ($number, $unit) => $this->chuyenGiaSangVnd($number, mb_strtolower($unit) === 'củ' ? 'triệu' : $unit);

        $collect(self::KHOANG_GIA_PATTERN, function ($match) use ($money) {
            $upperUnit = $match[4][0];
            $lowerUnit = ($match[2][0] ?? '') !== '' ? $match[2][0] : $upperUnit;
            $prices = [$money($match[1][0], $lowerUnit), $money($match[3][0], $upperUnit)];
            sort($prices);

            return ['min' => $prices[0], 'max' => $prices[1], 'label' => trim($match[0][0])];
        });
        $collect('/(?:dưới|không quá|tối đa|chỉ có|ngân sách(?:\s*(?:tối đa|là|có))?)\s*' . $amount . '/iu', fn ($match) => [
            'min' => null, 'max' => $money($match[1][0], $match[2][0]), 'label' => trim($match[0][0]),
        ]);
        // "khoảng / tầm 1 triệu" khi tư vấn = tối đa khoảng đó (cho nhỉnh hơn 15%).
        $collect('/(?:khoảng|tầm)(?:\s*giá)?\s*' . $amount . '/iu', fn ($match) => [
            'min' => null, 'max' => $money($match[1][0], $match[2][0]) * 1.15, 'label' => trim($match[0][0]),
        ]);
        $collect('/(?:trên|từ|hơn)\s*' . $amount . '(?!\s*(?:-|–|đến|tới))/iu', fn ($match) => [
            'min' => $money($match[1][0], $match[2][0]), 'max' => null, 'label' => trim($match[0][0]),
        ]);
        $collect('/(không giới hạn(?: ngân sách)?|bao nhiêu cũng được|tiền nào cũng được)/iu', fn ($match) => [
            'min' => null, 'max' => null, 'label' => 'không giới hạn ngân sách',
        ]);

        if (!$found) {
            return null;
        }
        ksort($found);

        return end($found);
    }

    private function apDungNganSach(Builder $query, ?array $budget): void
    {
        $price = 'IF(sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai IS NOT NULL, sanpham.GiaKhuyenMai, sanpham.GiaBan)';
        if (!empty($budget['max'])) {
            $query->whereRaw($price . ' <= ?', [$budget['max']]);
        }
        if (!empty($budget['min'])) {
            $query->whereRaw($price . ' >= ?', [$budget['min']]);
        }
    }

    private function coGioiHanNganSach(?array $budget): bool
    {
        return !empty($budget['max']) || !empty($budget['min']);
    }

    private function laySizeGiay(string $text): ?string
    {
        return preg_match_all('/(?:size|cỡ|số|chân)\s*(3[4-9]|4[0-7])\b/iu', mb_strtolower($text), $matches)
            ? end($matches[1])
            : null;
    }

    private function laYeuCauPhuKien(string $message): bool
    {
        return preg_match('/(phụ kiện|phu kien|cước|dây cước|dây đan|quấn cán|quan can|overgrip|bao vợt|túi vợt|túi đựng vợt|túi cầu lông|balo cầu lông|ống cầu|quả cầu|cầu nhựa|cầu lông vũ)/iu', $message) === 1;
    }

    private function laYeuCauVot(string $message): bool
    {
        return !$this->laYeuCauPhuKien($message) && preg_match('/(vợt|\bvot\b)/iu', $message) === 1;
    }

    // cuoc / quan_can / bao_vot / qua_cau / tat_ca
    private function layLoaiPhuKien(string $message): string
    {
        return $this->layGiaTriSauCung(mb_strtolower($message), [
            'cuoc' => '/(cước|dây cước|dây đan|dây vợt)/iu',
            'quan_can' => '/(quấn cán|quan can|overgrip)/iu',
            'bao_vot' => '/(bao vợt|túi vợt|túi đựng vợt|túi cầu lông|balo)/iu',
            'qua_cau' => '/(ống cầu|quả cầu|cầu nhựa|cầu lông vũ)/iu',
        ]) ?? 'tat_ca';
    }

    private function tenLoaiPhuKien(string $type): string
    {
        return match ($type) {
            'cuoc' => 'cước đan vợt',
            'quan_can' => 'quấn cán',
            'bao_vot' => 'túi/bao vợt',
            'qua_cau' => 'ống cầu',
            default => 'bộ phụ kiện cơ bản',
        };
    }

    private function layNhuCauTheoMoTa(string $type, string $text): array
    {
        $lower = mb_strtolower($text);

        return array_values(array_filter(
            self::NHU_CAU_THEO_MO_TA,
            fn ($need) => in_array($type, $need['for'], true) && preg_match($need['need'], $lower) === 1
        ));
    }

    // Những nhu cầu được mô tả sản phẩm của cửa hàng nhắc tới.
    private function nhuCauCoTrongMoTa(object $product, array $needs): array
    {
        $description = mb_strtolower(strip_tags((string) ($product->MoTa ?? '')));
        $matched = [];
        foreach ($needs as $need) {
            foreach ($need['desc'] as $keyword) {
                if (str_contains($description, $keyword)) {
                    $matched[] = $need['label'];
                    break;
                }
            }
        }

        return $matched;
    }

    // ---------- Bắt đầu và theo dõi phiên hỏi làm rõ ----------

    // Khách nêu nhu cầu tư vấn loại nào: vot / giay / phu_kien, hoặc null.
    private function layLoaiTuVanBatDau(string $message): ?string
    {
        if ($this->laYeuCauSoSanh($message) || preg_match('/(đơn hàng|tra cứu|bảo hành|đổi trả|thanh toán)/iu', $message)) {
            return null;
        }
        $asksAdvice = preg_match('/(tư vấn|tu van|gợi ý|goi y|nên mua|nên chọn|nên dùng|nên lấy|muốn mua|cần mua|định mua|mua|chọn|tìm|loại nào|mẫu nào|cái nào|đôi nào|cây nào|vợt nào|nào tốt|phù hợp|hợp với|giúp|cần gì|cần những gì|đi kèm|cho\s+(?:người|mình|tôi|em|nữ|nam|trẻ|bé))/iu', $message) === 1;

        // Khách kể trình độ / lối chơi mà không nhắc tới loại sản phẩm nào => đang hỏi về vợt.
        // (vd: "Tôi chơi nâng cao, thích công thủ toàn diện, ngân sách dưới 4 triệu")
        if ($this->layLoaiSanPhamNhacToi($message) === null) {
            $hasLevel = in_array($this->layMaTrinhDo($message), ['MoiChoi', 'NangCao', 'ChuyenNghiep'], true);
            $hasStyle = $this->layLoiChoi($message) !== null;
            $criteriaCount = (int) $hasLevel + (int) $hasStyle + (int) ($this->layNganSach($message) !== null);

            return $criteriaCount >= 2 || ($asksAdvice && ($hasLevel || $hasStyle)) ? 'vot' : null;
        }
        // Quần áo / váy: hỏi tư vấn hoặc hỏi size ("40kg nên mặc áo size gì") => tư vấn theo nhu cầu.
        if ($this->layLoaiSanPhamNhacToi($message) === 'quan_ao'
            && ($asksAdvice || preg_match('/(size|kích cỡ|(?<!\p{L})cỡ|\d\s*(?:kg|ký|kí|cân)|cân nặng|chiều cao|nên mặc|mặc gì|mặc vừa)/iu', $message) === 1)) {
            return 'quan_ao';
        }
        if (!$asksAdvice) {
            return null;
        }
        if ($this->laYeuCauPhuKien($message)) {
            return 'phu_kien';
        }
        if (preg_match('/(giày|giay)/iu', $message)) {
            return 'giay';
        }
        // Khách đã nêu thông số cụ thể (4U, G5, đầu nặng...) hoặc tên mẫu => tìm thẳng sản phẩm.
        $hasExactSpecs = preg_match('/(\b[2-5]u\b|\bg[3-5]\b|nặng đầu|đầu nặng|nhẹ đầu|đầu nhẹ|cân bằng|đũa cứng|thân cứng|thân dẻo|\d+\s*lbs?)/iu', $message) === 1;
        $namesModel = preg_match('/(astrox|nanoflare|arcsaber|duora|voltric|thruster|ryuga|axforce|power cushion|jetspeed|auraspeed|halbertec|tectonic|\b\d{2,3}z+\d*\b)/iu', $message) === 1;

        return $this->laYeuCauVot($message) && !$hasExactSpecs && !$namesModel ? 'vot' : null;
    }

    // Loại sản phẩm được nhắc tới trong câu (để biết khách có chuyển chủ đề không).
    private function layLoaiSanPhamNhacToi(string $message): ?string
    {
        return match (true) {
            $this->laYeuCauPhuKien($message) => 'phu_kien',
            preg_match('/(giày|giay)/iu', $message) === 1 => 'giay',
            preg_match('/(quần|áo|váy|đầm)/iu', $message) === 1 => 'quan_ao',
            $this->laYeuCauVot($message) => 'vot',
            default => null,
        };
    }

    // Mỗi ý cần hỏi đã có câu trả lời chưa.
    private function layTrangThaiTuVan(string $loai, string $text): array
    {
        $lower = mb_strtolower($text);
        $checks = [
            'trinh_do' => fn () => $this->layMaTrinhDo($lower) !== null,
            'loi_choi' => fn () => $this->layLoiChoi($lower) !== null,
            'ngan_sach' => fn () => $this->layNganSach($lower) !== null,
            'chi_tiet' => fn () => array_filter($this->layCachChoi($lower)) !== [] || $this->layTheLuc($lower) !== null
                || $this->layCoCan($lower) !== null,
            'giay_size' => fn () => $this->laySizeGiay($lower) !== null || str_contains($lower, 'size chưa rõ'),
            'giay_nhu_cau' => fn () => preg_match('/((?<!\p{L})êm(?!\p{L})|giảm chấn|đau gối|đau gót|khớp|bám|trơn|nhẹ|linh hoạt|tốc độ|nhanh|bền|mòn|thoáng|nóng|ổn định|lật|chắc chân|ôm chân|phong trào|thi đấu|tập luyện|xi măng|thảm|sàn gỗ|sân gỗ|ngoài trời|trong nhà|nhu cầu chung)/iu', $lower) === 1,
            'pk_loai' => fn () => $this->layLoaiPhuKien($lower) !== 'tat_ca'
                || preg_match('/(bộ phụ kiện cơ bản|bộ cơ bản|đủ bộ|tất cả|mấy món|những gì|phụ kiện gì|cần gì|cần thêm|đi kèm)/iu', $lower) === 1,
            'pk_nhu_cau' => fn () => preg_match('/(bền|đứt|nảy|tiếng nổ|âm thanh|cảm giác|trợ lực|lực tay|lực yếu|(?<!\p{L})êm(?!\p{L})|đau|mồ hôi|trơn|bám|dày|mỏng|đựng|ngăn|\d+\s*(?:cây\s*)?vợt|ngoài trời|trong nhà|tập|thi đấu|nhựa|lông vũ|lbs|căng|mới chơi|người mới|chơi lâu|nhu cầu chung|chưa có gì|đã có)/iu', $lower) === 1,
            'qa_loai' => fn () => $this->layLoaiQuanAo($lower) !== null,
            'qa_size' => fn () => $this->laySizeQuanAo($lower) !== null || $this->layCanNang($lower) !== null
                || $this->layChieuCao($lower) !== null || str_contains($lower, 'size chưa rõ'),
            'qa_nhu_cau' => fn () => $this->layNhuCauTheoMoTa('quan_ao', $lower) !== []
                || preg_match('/(tập|thi đấu|hằng ngày|hàng ngày|đi chơi|mặc thường|mặc nhà|nhu cầu chung|chơi phong trào|giao lưu)/iu', $lower) === 1,
        ];

        $state = [];
        foreach (self::KICH_BAN_TU_VAN[$loai] as $slot) {
            $state[$slot] = $checks[$slot]();
        }

        return $state;
    }

    // Câu trả lời có thông tin dùng được cho việc tư vấn loại sản phẩm này không.
    private function laThongTinTuVan(string $loai, string $text): bool
    {
        $lower = mb_strtolower($text);

        return in_array(true, $this->layTrangThaiTuVan($loai, $lower), true)
            || preg_match(self::THUONG_HIEU_PATTERN, $lower) === 1
            || ($loai === 'vot' && ($this->layTheLuc($lower) !== null || $this->muonVotNhe($lower) || $this->layCoCan($lower) !== null))
            || ($loai === 'quan_ao' && $this->layGioiTinh($lower) !== null);
    }

    // Đổi câu trả lời ngắn ("3 triệu", "42", "cả hai", "không biết"...) thành tiêu chí đọc được.
    private function chuanHoaTraLoiTuVan(string $message, ?string $slot): string
    {
        $text = trim($message);
        $lower = mb_strtolower($text);
        $isSkip = preg_match('/(không biết|ko biết|chưa biết|chưa rõ|không rõ|không chắc|tùy|tuỳ|sao cũng được|gì cũng được|bỏ qua|không quan trọng)/iu', $lower) === 1;

        if ($this->layNganSach($lower) === null) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(triệu|tr|củ|nghìn|ngàn|k)(?!\p{L})/iu', $lower, $matches)) {
                $text .= ' ngân sách ' . $matches[1] . ' ' . $matches[2];
            } elseif ($slot === 'ngan_sach' && preg_match('/^\D*?(\d+(?:[.,]\d+)?)\D*$/u', $lower, $matches)
                && (float) str_replace(',', '.', $matches[1]) <= 100) {
                $text .= ' ngân sách ' . $matches[1] . ' triệu';
            }
        }
        if ($slot === 'loi_choi' && $this->layLoiChoi($lower) === null && preg_match('/(cả hai|đều được|cả công)/iu', $lower)) {
            $text .= ' lối chơi toàn diện';
        }
        // Đang hỏi cần món phụ kiện nào mà khách chỉ kể nhu cầu => tư vấn bộ phụ kiện cơ bản.
        if ($slot === 'pk_loai' && $this->layLoaiPhuKien($lower) === 'tat_ca') {
            $text .= ' bộ phụ kiện cơ bản';
        }
        if ($slot === 'giay_size' && preg_match('/^\D*?(3[4-9]|4[0-7])\D*$/u', $lower, $matches)) {
            $text .= ' size ' . $matches[1];
        }
        // Đang hỏi size quần áo: "M" => size M, "45" => nặng 45kg, "160" => cao 160cm.
        if ($slot === 'qa_size' && $this->laySizeQuanAo($lower) === null && $this->layCanNang($lower) === null && $this->layChieuCao($lower) === null) {
            if (preg_match('/^\W*(xxl|xl|xs|s|m|l)\W*$/iu', $lower, $matches)) {
                $text .= ' size ' . mb_strtoupper($matches[1]);
            } elseif (preg_match('/^\D*?(\d{2,3})\D*$/u', $lower, $matches)) {
                $number = (int) $matches[1];
                $text .= $number >= 130 && $number <= 200 ? ' cao ' . $number . 'cm' : ($number >= 25 && $number <= 120 ? ' nặng ' . $number . 'kg' : '');
            }
        }
        if ($slot === 'chi_tiet' && $this->layTheLuc($lower) === null) {
            if (preg_match('/(khỏe|khoẻ|mạnh)/iu', $lower)) {
                $text .= ' lực tay tốt';
            } elseif (preg_match('/(yếu|kém)/iu', $lower)) {
                $text .= ' lực tay yếu';
            }
        }
        if ($isSkip && $slot !== null && isset(self::MAC_DINH_KHI_BO_QUA[$slot])) {
            $text .= ' ' . self::MAC_DINH_KHI_BO_QUA[$slot];
        }

        return $text;
    }

    // Đọc lại lịch sử: phiên đang hỏi ý nào, khách đã trả lời gì, bỏ qua ý nào.
    private function layPhienTuVan(array $history): ?array
    {
        $session = null;
        foreach ($history as $entry) {
            $content = (string) ($entry['content'] ?? '');
            $sender = $entry['sender'] ?? null;
            if ($sender === 'KhachHang') {
                $newType = $this->layLoaiTuVanBatDau(mb_strtolower($content));
                // Đang tư vấn quần áo mà khách trả lời có nhắc "áo/quần" => vẫn là câu trả lời, không mở phiên mới.
                $isClothesAnswer = $newType === 'quan_ao' && ($session['loai'] ?? null) === 'quan_ao';
                // Đang tư vấn vợt, khách chỉ kể tiêu chí (không nhắc loại sản phẩm) => là câu trả lời, giữ thông tin cũ.
                $isRacketCriteriaAnswer = $newType === 'vot' && ($session['loai'] ?? null) === 'vot'
                    && $this->layLoaiSanPhamNhacToi(mb_strtolower($content)) === null;
                if (($session['slot'] ?? null) !== null && ($newType === null || $isClothesAnswer || $isRacketCriteriaAnswer)) {
                    $session = $this->ghiNhanTraLoiTuVan($session, $content);
                } else {
                    $session = ['loai' => $newType, 'request' => $this->chuanHoaTraLoiTuVan($content, null), 'skipped' => [], 'slot' => null];
                }
                continue;
            }
            // Câu hỏi có kèm nhu cầu đã gom sẵn => phiên bắt đầu từ nhu cầu đó.
            if ($sender === 'Chatbot' && preg_match('/<!--tuvan:(vot|giay|phu_kien|quan_ao):(\w+)--><!--tuvan-yeucau:([A-Za-z0-9+\/=]+)-->/', $content, $matches)) {
                $session = ['loai' => $matches[1], 'request' => (string) base64_decode($matches[3], true), 'skipped' => [], 'slot' => $matches[2]];
                continue;
            }
            if ($sender === 'Chatbot' && $session !== null) {
                if (preg_match('/<!--tuvan:(vot|giay|phu_kien|quan_ao):(\w+)-->/', $content, $matches)) {
                    $session['loai'] = $matches[1];
                    $session['slot'] = $matches[2];
                } else {
                    $session = null;
                }
            }
        }

        return ($session['slot'] ?? null) !== null ? $session : null;
    }

    // Thêm câu trả lời vào phiên. Khách lờ câu đang hỏi (không trả lời ý chính nào) thì không hỏi lại ý đó.
    private function ghiNhanTraLoiTuVan(array $session, string $message): array
    {
        $answer = $this->chuanHoaTraLoiTuVan($message, $session['slot']);
        if (!$this->laThongTinTuVan($session['loai'], $answer)) {
            return $session;
        }

        $session['request'] = trim($session['request'] . ' ' . $answer);
        if ($session['slot'] !== 'tat_ca' && !in_array(true, $this->layTrangThaiTuVan($session['loai'], $answer), true)) {
            $session['skipped'][] = $session['slot'];
        }

        return $session;
    }

    private function yeuCauKemMacDinh(array $session, array $skipped): string
    {
        $request = $session['request'];
        $state = $this->layTrangThaiTuVan($session['loai'], $request);
        foreach ($skipped as $slot) {
            if (empty($state[$slot])) {
                $request .= ' ' . self::MAC_DINH_KHI_BO_QUA[$slot];
            }
        }

        return $request;
    }

    private function xuLyTraLoiPhienTuVan(string $message, array $session): ?string
    {
        $lower = mb_strtolower(trim($message));
        $loai = $session['loai'];
        if (preg_match('/^(thôi|hủy|huỷ|dừng|không cần nữa|khỏi)/iu', $lower)) {
            return 'Dạ vâng, mình dừng phần tư vấn nhé. Khi cần, bạn cứ kể nhu cầu để mình hỗ trợ lại ạ.';
        }
        if (preg_match('/(đơn hàng|tra cứu|bảo hành|đổi trả|thanh toán)/iu', $lower)) {
            return null;
        }
        // Khách nêu một nhu cầu mới => bắt đầu lại từ nhu cầu đó.
        $newType = $this->layLoaiTuVanBatDau($lower);
        $isRacketCriteriaAnswer = $newType === 'vot' && $loai === 'vot' && $this->layLoaiSanPhamNhacToi($lower) === null;
        if ($newType !== null && !($newType === 'quan_ao' && $loai === 'quan_ao') && !$isRacketCriteriaAnswer) {
            return $this->tiepTucTuVan($newType, $this->chuanHoaTraLoiTuVan($message, null), false, false, true);
        }
        // Khách chuyển sang hỏi loại sản phẩm khác => thoát phiên (riêng phụ kiện thì được nhắc tới vợt).
        $mentioned = $this->layLoaiSanPhamNhacToi($lower);
        if ($mentioned !== null && $mentioned !== $loai && !($loai === 'phu_kien' && $mentioned === 'vot')) {
            return null;
        }
        if ($loai === 'vot' && $session['slot'] === 'trinh_do'
            && preg_match('/(trung cấp|trình độ trung bình|chơi khá)/iu', $lower)) {
            return 'Dạ, dữ liệu vợt hiện chia trình độ thành ba nhóm: mới chơi, nâng cao và chuyên nghiệp. Bạn thấy mình gần với nhóm nào nhất ạ?<!--tuvan:vot:trinh_do-->';
        }

        $updated = $this->ghiNhanTraLoiTuVan($session, $message);
        if ($updated !== $session) {
            return $this->tiepTucTuVan($loai, $this->yeuCauKemMacDinh($updated, $updated['skipped']));
        }

        $request = $this->yeuCauKemMacDinh($session, $session['skipped']);
        // Khách hỏi xen một câu kiến thức => để FAQ/AI trả lời, rồi hỏi tiếp ý đang dở.
        if (preg_match('/(là gì|khác gì|khác nhau|nghĩa là|tại sao|vì sao|như thế nào|thế nào là|có nên)/iu', $lower)) {
            $answer = $this->timKiemFAQ($lower) ?? $this->troChuyenTuNhienVoiGemini($message);
            return $answer . '<br><br>' . $this->tiepTucTuVan($loai, $request, true);
        }
        // Khách muốn xem tư vấn luôn => dùng thông tin đã có, phần còn thiếu lấy mặc định.
        if (preg_match('/(tư vấn luôn|gợi ý luôn|đề xuất luôn|tìm luôn|xem luôn|cho (?:mình|tôi|em) xem|cây nào|mẫu nào|vợt nào|đôi nào|cái nào|nên mua|bỏ qua|không cần hỏi)/iu', $lower)) {
            return $this->ketLuanTuVan($loai, $this->yeuCauKemMacDinh($session, self::KICH_BAN_TU_VAN[$loai]));
        }

        return $this->tiepTucTuVan($loai, $request, false, true);
    }

    // Hỏi ý còn thiếu (câu hỏi mở), hoặc đủ thông tin thì phân tích và tư vấn.
    private function tiepTucTuVan(
        string $loai,
        string $request,
        bool $afterSideQuestion = false,
        bool $notUnderstood = false,
        bool $isFirstTurn = false,
        ?string $lead = null,
        bool $ghiNhoYeuCau = false
    ): string {
        $state = $this->layTrangThaiTuVan($loai, $request);
        $missing = array_values(array_filter(self::KICH_BAN_TU_VAN[$loai], fn ($slot) => !$state[$slot]));
        $missingMain = array_values(array_diff($missing, self::Y_HOI_THEM));
        if ($isFirstTurn && $loai === 'vot' && $this->coCanShortlistVot($request, $state)) {
            return $this->traDanhSachVotCanLamRo($request, $missingMain, $missing);
        }
        if (!$missing || !$missingMain) {
            return $this->ketLuanTuVan($loai, $request, ['invite_more' => $missing]);
        }

        $known = $this->tomTatNhuCau($loai, $request);
        $prefix = $notUnderstood ? 'Dạ, mình chưa rõ ý bạn lắm. Bạn kể thêm giúp mình nhé: ' : ($lead ?? 'Dạ, ');

        // Vợt, khách chưa nói gì về nhu cầu: hỏi gộp 3 ý chính.
        if ($loai === 'vot' && $known === '' && !$afterSideQuestion) {
            return $prefix
                . 'để tư vấn vợt sát nhu cầu hơn, bạn cho mình biết trình độ (mới chơi, nâng cao hay chuyên nghiệp), lối chơi (tấn công, phòng thủ hay toàn diện) và ngân sách dự kiến nhé. Nếu đã có yêu cầu về trọng lượng hoặc cỡ cán, bạn gửi kèm giúp mình ạ.'
                . '<!--tuvan:vot:tat_ca-->';
        }

        $slot = $missing[0];
        if ($afterSideQuestion) {
            $intro = 'Quay lại phần tư vấn cho bạn nhé.';
        } elseif ($known === '') {
            $intro = $prefix . 'để chọn đúng món hợp với bạn, mình hỏi bạn vài câu nhé.';
        } else {
            $intro = $prefix . 'mình đã ghi nhận: <b>' . e($known) . '</b>.'
                . (count($missing) > 1 ? ' Mình hỏi thêm một chút để tư vấn sát hơn nhé.' : ' Còn một câu nữa thôi ạ.');
        }
        // Quần áo: đã có cân nặng/chiều cao thì trả lời size sơ bộ ngay, rồi hỏi tiếp để chọn mẫu.
        if ($loai === 'quan_ao' && ($sizeNote = $this->moTaSizeQuanAo(mb_strtolower($request))) !== null) {
            $intro .= '<br>' . $sizeNote;
        }

        return $intro . '<br>' . $this->cauHoiTuVan($slot, $loai, $request) . sprintf('<!--tuvan:%s:%s-->', $loai, $slot)
            // Nhu cầu đã gom sẵn (vd: giữ số đo khi đổi món) để câu trả lời sau được ghép đúng.
            . ($ghiNhoYeuCau ? sprintf('<!--tuvan-yeucau:%s-->', base64_encode($request)) : '');
    }

    private function cauHoiTuVan(string $slot, string $loai, string $request): string
    {
        return match ($slot) {
            'trinh_do' => 'Bạn chơi cầu lông được bao lâu rồi, và tự thấy mình ở mức mới chơi, nâng cao hay chuyên nghiệp ạ?',
            'loi_choi' => 'Khi ra sân bạn thích đánh thế nào: hay lên đập cầu tấn công, thiên về phòng thủ – phản tạt, hay đánh công thủ toàn diện?',
            'ngan_sach' => $loai === 'giay'
                ? 'Bạn dự định chi khoảng bao nhiêu cho đôi giày này ạ?'
                : 'Bạn dự định đầu tư khoảng bao nhiêu cho cây vợt này ạ?',
            'chi_tiet' => 'Bạn chủ yếu đánh đơn hay đánh đôi, chơi phong trào hay tập thi đấu, một tuần mấy buổi? Lực tay của bạn thế nào, có hay bị đau khuỷu tay hoặc cổ tay không, và bàn tay bạn to hay nhỏ (để chọn cỡ cán G4/G5) ạ?',
            'giay_size' => 'Bạn thường mang giày size bao nhiêu, và bàn chân của bạn bè (rộng) hay thon ạ?',
            'giay_nhu_cau' => 'Bạn hay chơi trên sân nào (thảm, sàn gỗ hay xi măng/ngoài trời), chơi phong trào hay thi đấu, và ưu tiên điều gì nhất ở đôi giày: êm – giảm chấn, nhẹ – linh hoạt, ôm chân – ổn định hay bền? Nếu hay đau gối hoặc cổ chân, bạn nói mình biết nhé.',
            'pk_loai' => 'Bạn đang cần món nào: cước đan vợt, quấn cán, túi đựng vợt, ống cầu, hay muốn mình tư vấn một bộ phụ kiện cơ bản ạ?',
            'qa_loai' => 'Bạn đang cần áo cầu lông, quần, váy, áo khoác/hoodie giữ ấm, hay cả bộ quần áo ạ?',
            'qa_size' => 'Bạn cho mình xin cân nặng và chiều cao (ví dụ: 52kg, 1m60) hoặc size bạn hay mặc'
                . ($this->layGioiTinh(mb_strtolower($request)) === null ? ', và bạn là nam hay nữ' : '') . ' để mình chọn size vừa nhé.',
            'qa_nhu_cau' => 'Bạn mặc chủ yếu để tập luyện hằng ngày hay đi thi đấu, và ưu tiên điều gì: vải thấm hút – mát, co giãn thoải mái, giữ ấm hay giá mềm?'
                . ($this->layGioiTinh(mb_strtolower($request)) === null ? ' Bạn là nam hay nữ để mình chọn đúng form ạ?' : '')
                . ' Nếu có ngân sách dự kiến, bạn nhắn kèm luôn nhé.',
            default => match ($this->layLoaiPhuKien($request)) {
                'cuoc' => 'Bạn muốn cước bền, ít đứt hay cảm giác nảy, tiếng nổ đanh? Lực tay của bạn thế nào và bạn thường căng khoảng bao nhiêu lbs ạ?',
                'quan_can' => 'Tay bạn có hay ra mồ hôi, bị trơn khi cầm vợt không? Bạn thích cán cầm dày, chắc tay hay mỏng để cảm giác rõ hơn?',
                'bao_vot' => 'Bạn thường mang theo mấy cây vợt, và có cần đựng thêm giày, quần áo không ạ?',
                'qua_cau' => 'Bạn hay đánh ngoài trời hay trong nhà, dùng cầu để tập luyện hay để thi đấu ạ?',
                default => 'Bạn mới bắt đầu chơi hay đã chơi lâu, tay có hay ra mồ hôi không, và bạn đã có sẵn những món nào rồi ạ?',
            },
        };
    }

    private function tomTatNhuCau(string $loai, string $request): string
    {
        $lower = mb_strtolower($request);
        $summary = [];

        if ($loai === 'vot') {
            $summary[] = $this->tenTrinhDo($this->layMaTrinhDo($lower));
            $summary[] = match ($this->layLoiChoi($lower)) {
                'tan_cong' => 'thích tấn công',
                'phong_thu' => 'thiên phòng thủ',
                'toan_dien' => 'công thủ toàn diện',
                default => '',
            };
            $playing = $this->layCachChoi($lower);
            $summary[] = match ($playing['format']) {
                'doi' => 'đánh đôi',
                'don' => 'đánh đơn',
                'ca_hai' => 'chơi cả đơn và đôi',
                default => '',
            };
            $summary[] = match ($playing['purpose']) {
                'thi_dau' => 'tập thi đấu',
                'phong_trao' => 'chơi phong trào',
                default => '',
            };
            $summary[] = $playing['frequency'] !== null ? ($playing['frequency'] >= 7 ? 'chơi hằng ngày' : $playing['frequency'] . ' buổi/tuần') : '';
            $summary[] = match ($this->layTheLuc($lower)) {
                'dau_tay' => 'hay đau tay',
                'yeu' => 'lực tay yếu',
                'khoe' => 'lực tay tốt',
                'binh_thuong' => 'lực tay bình thường',
                default => '',
            };
            $summary[] = $this->muonVotNhe($lower) ? 'muốn vợt nhẹ, dễ điều khiển' : '';
            $grip = $this->layCoCan($lower);
            $summary[] = $grip !== null ? 'cán ' . $grip : '';
        } elseif ($loai === 'giay') {
            $size = $this->laySizeGiay($lower);
            $summary[] = $size !== null ? 'size ' . $size : '';
            $summary[] = preg_match('/(bè|rộng)/iu', $lower) ? 'bàn chân bè' : (preg_match('/(thon|hẹp)/iu', $lower) ? 'bàn chân thon' : '');
            $summary[] = $this->layMatSan($lower) ?? '';
            foreach ($this->layNhuCauTheoMoTa('giay', $lower) as $need) {
                $summary[] = 'ưu tiên ' . $need['label'];
            }
        } elseif ($loai === 'quan_ao') {
            $type = $this->layLoaiQuanAo($lower);
            $summary[] = $type !== null ? $this->tenLoaiQuanAo($type) : '';
            $gender = $this->layGioiTinh($lower);
            $summary[] = $gender === 'nu' ? 'nữ' : ($gender === 'nam' ? 'nam' : '');
            $weight = $this->layCanNang($lower);
            $summary[] = $weight !== null ? 'nặng ' . $weight . 'kg' : '';
            $height = $this->layChieuCao($lower);
            $summary[] = $height !== null ? 'cao ' . $height . 'cm' : '';
            $size = $this->laySizeQuanAo($lower);
            $summary[] = $size !== null ? 'mặc size ' . $size : '';
            foreach ($this->layNhuCauTheoMoTa('quan_ao', $lower) as $need) {
                $summary[] = 'cần ' . $need['label'];
            }
        } else {
            $type = $this->layLoaiPhuKien($lower);
            $summary[] = $this->tenLoaiPhuKien($type);
            foreach ($this->layNhuCauTheoMoTa($type, $lower) as $need) {
                $summary[] = 'cần ' . $need['label'];
            }
        }

        if (preg_match(self::THUONG_HIEU_PATTERN, $lower, $brand)) {
            $summary[] = 'thương hiệu ' . ucfirst($brand[1]);
        }
        $budget = $this->layNganSach($lower);
        if ($budget !== null) {
            $summary[] = $this->coGioiHanNganSach($budget) ? 'ngân sách ' . preg_replace('/^ngân sách\s*/u', '', $budget['label']) : $budget['label'];
        }

        return implode(', ', array_unique(array_filter($summary)));
    }

    // ---------- Sau khi đã tư vấn: mẫu khác, rẻ hơn, chọn giữa các mẫu, đổi tiêu chí ----------

    // Kết quả tư vấn gần nhất trong cuộc trò chuyện (loại, nhu cầu, các mẫu đã xem).
    private function layNguCanhTuVan(array $history, bool $onlyLastReply = false): ?array
    {
        foreach (array_reverse($history) as $entry) {
            if (($entry['sender'] ?? null) !== 'Chatbot') {
                continue;
            }
            $content = (string) ($entry['content'] ?? '');
            if (preg_match('/<!--tuvan-loai:(vot|giay|phu_kien|quan_ao)-->/', $content, $typeMatch)
                && preg_match('/<!--tuvan-ketqua:([A-Za-z0-9+\/=]+)-->/', $content, $requestMatch)) {
                preg_match('/<!--tuvan-da-xem:([\d,]*)-->/', $content, $seenMatch);
                preg_match_all('/\/san-pham\/(\d+)/', $content, $shownMatches);

                return [
                    'loai' => $typeMatch[1],
                    'request' => (string) base64_decode($requestMatch[1], true),
                    'seen' => array_values(array_map('intval', array_filter(explode(',', $seenMatch[1] ?? '')))),
                    'shown' => array_values(array_unique(array_map('intval', $shownMatches[1]))),
                ];
            }
            if ($onlyLastReply) {
                return null;
            }
        }

        return null;
    }

    private function xuLySauKetQuaTuVan(string $message, array $history): ?string
    {
        $context = $this->layNguCanhTuVan($history, true);
        if ($context === null || $context['request'] === '') {
            return null;
        }
        $loai = $context['loai'];
        $lower = mb_strtolower(trim($message));
        $mentioned = $this->layLoaiSanPhamNhacToi($lower);
        if ($mentioned !== null && $mentioned !== $loai) {
            return null;
        }

        // Khách đổi sang món khác (áo -> váy, cước -> quấn cán) hoặc nhờ tư vấn lại một món
        // => tư vấn lại: chỉ giữ thông tin cá nhân còn đúng, hỏi lại nhu cầu cho món mới rồi mới gợi ý sản phẩm.
        $restart = $this->tuVanLaiKhiDoiMon($message, $lower, $context);
        if ($restart !== null) {
            return $restart;
        }

        // "Cây nào tốt hơn?" khi đang có từ 2 mẫu => chọn giữa các mẫu vừa tư vấn.
        if (count($context['shown']) >= 2
            && preg_match('/((?:cây|mẫu|đôi|cái|loại) nào (?:tốt hơn|hợp hơn|nên mua|nên chọn|nên lấy)|nên (?:mua|chọn|lấy) (?:cây|mẫu|đôi|cái|loại) nào)/iu', $lower)
            && !preg_match('/(khác|nữa|còn)/iu', $lower)) {
            return $this->ketLuanTuVan($loai, $context['request'], [
                'intro' => 'Dạ, trong các mẫu vừa tư vấn, mình phân tích lại để chọn ra một mẫu hợp nhất với bạn.',
                'only' => $context['shown'],
                'seen' => $context['seen'],
            ]);
        }

        // Muốn xem mẫu khác / rẻ hơn / nhẹ hơn mà vẫn đúng nhu cầu.
        if (preg_match('/(khác|tốt hơn|rẻ hơn|nhẹ hơn|đắt hơn|cao cấp hơn|xịn hơn|còn (?:cây|mẫu|đôi|vợt|loại|cái) nào)/iu', $lower)) {
            return $this->ketLuanTuVan($loai, $context['request'], [
                'intro' => 'Dạ, ngoài các mẫu đã tư vấn, đây là lựa chọn khác vẫn bám đúng nhu cầu của bạn.',
                'exclude' => $context['seen'] ?: $context['shown'],
                'seen' => $context['seen'],
                'cheaperThan' => preg_match('/(rẻ hơn|giá thấp hơn|tiết kiệm hơn)/iu', $lower) ? $context['shown'] : [],
                'lighterThan' => preg_match('/nhẹ hơn/iu', $lower) ? $context['shown'] : [],
            ]);
        }

        // Khách bổ sung / đổi tiêu chí => phân tích lại toàn bộ (câu mới ghi đè câu cũ).
        $answer = $this->chuanHoaTraLoiTuVan($message, null);
        if ($this->laThongTinTuVan($loai, $answer)) {
            return $this->ketLuanTuVan($loai, trim($context['request'] . ' ' . $answer), [
                'intro' => 'Dạ, mình cập nhật lại phần tư vấn theo thông tin mới của bạn.',
                'seen' => $context['seen'],
            ]);
        }

        return null;
    }

    private function tuVanLaiKhiDoiMon(string $message, string $lower, array $context): ?string
    {
        $old = mb_strtolower($context['request']);
        $freshAsk = preg_match('/(tư vấn|(?<!\p{L})tìm|muốn mua|cần mua|chọn giúp|gợi ý)/iu', $lower) === 1
            && !preg_match('/(khác|rẻ hơn|đắt hơn|tốt hơn)/iu', $lower);

        if ($context['loai'] === 'quan_ao') {
            $newType = $this->layLoaiQuanAo($lower);
            $oldType = $this->layLoaiQuanAo($old);
            if ($newType === null || ($newType === $oldType && !$freshAsk)) {
                return null;
            }
            // Giữ số đo và giới tính (câu mới nói sau sẽ ghi đè), bỏ nhu cầu / size / ngân sách của món cũ.
            $kept = [];
            if (($weight = $this->layCanNang($old)) !== null) {
                $kept[] = 'nặng ' . $weight . 'kg';
            }
            if (($height = $this->layChieuCao($old)) !== null) {
                $kept[] = 'cao ' . $height . 'cm';
            }
            if (($gender = $this->layGioiTinh($old)) !== null) {
                $kept[] = $gender === 'nu' ? 'nữ' : 'nam';
            }
            $request = trim(implode(' ', $kept) . ' ' . $this->chuanHoaTraLoiTuVan($message, null));
            $lead = 'Dạ, mình ' . ($newType !== $oldType ? 'chuyển sang tư vấn ' : 'tư vấn lại ') . $this->tenLoaiQuanAo($newType) . ' cho bạn, ';

            return $this->tiepTucTuVan('quan_ao', $request, false, false, false, $lead, true);
        }

        if ($context['loai'] === 'phu_kien') {
            $newType = $this->layLoaiPhuKien($lower);
            if ($newType === 'tat_ca' || $newType === $this->layLoaiPhuKien($old)) {
                return null;
            }
            $lead = 'Dạ, mình chuyển sang tư vấn ' . $this->tenLoaiPhuKien($newType) . ' cho bạn, ';

            return $this->tiepTucTuVan('phu_kien', $this->chuanHoaTraLoiTuVan($message, null), false, false, false, $lead, true);
        }

        return null;
    }

    // ---------- Phân tích nhu cầu & tư vấn (khuyên trước, sản phẩm sau) ----------

    private function ketLuanTuVan(string $loai, string $request, array $options = []): string
    {
        return match ($loai) {
            'giay' => $this->ketLuanTuVanGiay($request, $options),
            'phu_kien' => $this->ketLuanTuVanPhuKien($request, $options),
            'quan_ao' => $this->ketLuanTuVanQuanAo($request, $options),
            default => $this->ketLuanTuVanVot($request, $options),
        };
    }

    private function truyVanSanPhamDangBan(bool $chiConHang = true): Builder
    {
        return DB::table('sanpham')
            ->where('sanpham.TrangThai', 'DangBan')
            ->when($chiConHang, fn ($query) => $query->where('sanpham.SoLuongTon', '>', 0))
            ->leftJoin('thongsovot', 'sanpham.ID_SanPham', '=', 'thongsovot.ID_SanPham')
            ->leftJoin('thuonghieu', 'sanpham.ID_ThuongHieu', '=', 'thuonghieu.ID_ThuongHieu')
            ->leftJoin('danhmuc', 'sanpham.ID_DanhMuc', '=', 'danhmuc.ID_DanhMuc')
            ->leftJoin('hinhanhsanpham', function ($join) {
                $join->on('sanpham.ID_SanPham', '=', 'hinhanhsanpham.ID_SanPham')
                    ->where('hinhanhsanpham.ThuTu', '=', 1);
            })
            ->select([
                'sanpham.ID_SanPham', 'sanpham.TenSanPham', 'sanpham.GiaBan', 'sanpham.GiaKhuyenMai',
                'sanpham.SoLuongTon', 'sanpham.HinhAnh', 'sanpham.MoTa', 'sanpham.SizeCoSan',
                'hinhanhsanpham.DuongDan as HinhAnhPhu', 'thuonghieu.TenThuongHieu', 'danhmuc.TenDanhMuc',
                'thongsovot.TrinhDoPhuHop', 'thongsovot.DiemCanBang', 'thongsovot.TrongLuong',
                'thongsovot.DoCung', 'thongsovot.ChuViTayCam', 'thongsovot.MucCangToiDa',
            ]);
    }

    // Lọc theo ngân sách, thương hiệu và các lựa chọn: chỉ trong / loại trừ mẫu, rẻ hơn / nhẹ hơn mẫu đã xem.
    private function layUngVienTuVan(Builder $query, string $lower, array $options, bool $applyBudget = true): \Illuminate\Support\Collection
    {
        // Khách bảo chọn trong các mẫu vừa gợi ý => chỉ xét đúng các mẫu đó, không lọc thêm.
        if (!empty($options['chi_trong'])) {
            return $query->whereIn('sanpham.ID_SanPham', $options['chi_trong'])->get()->unique('ID_SanPham')->values();
        }
        if ($applyBudget) {
            $this->apDungNganSach($query, $this->layNganSach($lower));
        }
        if (preg_match(self::THUONG_HIEU_PATTERN, $lower, $brand)) {
            $query->where('thuonghieu.TenThuongHieu', 'like', '%' . str_replace('-', '', $brand[1]) . '%');
        }
        $candidates = $query->get()->unique('ID_SanPham')->values();
        // Khách đang tìm đồ khuyến mãi => chỉ tư vấn trong các mẫu đang giảm giá thật.
        if ($this->laHoiKhuyenMai($lower)) {
            $discounted = $candidates->filter(fn ($product) => $this->phanTramGiamGia($product) > 0);
            $candidates = $discounted->isNotEmpty() ? $discounted : $candidates;
        }

        if (!empty($options['only'])) {
            $candidates = $candidates->filter(fn ($product) => in_array((int) $product->ID_SanPham, $options['only'], true));
        }
        if (!empty($options['exclude'])) {
            $candidates = $candidates->reject(fn ($product) => in_array((int) $product->ID_SanPham, $options['exclude'], true));
        }
        $candidates = $this->locTheoThongSoBatBuoc($candidates, $lower);
        if (!empty($options['cheaperThan'])) {
            $reference = DB::table('sanpham')->whereIn('ID_SanPham', $options['cheaperThan'])->get(['GiaBan', 'GiaKhuyenMai'])
                ->min(fn ($product) => $this->giaHienTai($product));
            if ($reference !== null) {
                $candidates = $candidates->filter(fn ($product) => $this->giaHienTai($product) < $reference);
            }
        }
        if (!empty($options['lighterThan'])) {
            $lightest = DB::table('thongsovot')->whereIn('ID_SanPham', $options['lighterThan'])->pluck('TrongLuong')
                ->max(fn ($weight) => $this->laySoU((string) $weight));
            $candidates = $candidates->filter(fn ($product) => $this->laySoU((string) ($product->TrongLuong ?? '')) > (int) $lightest);
        }

        return $candidates->values();
    }

    private function locTheoThongSoBatBuoc(\Illuminate\Support\Collection $candidates, string $lower): \Illuminate\Support\Collection
    {
        if (preg_match('/\b([2-5])\s*U\b/iu', $lower, $requestedWeight)) {
            $candidates = $candidates->filter(
                fn ($product) => $this->laySoU((string) ($product->TrongLuong ?? '')) === (int) $requestedWeight[1]
            );
        }
        if (preg_match('/\b(G[3-5])\b/iu', $lower, $requestedGrip)) {
            $requestedGrip = strtoupper($requestedGrip[1]);
            $candidates = $candidates->filter(
                fn ($product) => preg_match('/\b' . preg_quote($requestedGrip, '/') . '\b/iu', (string) ($product->ChuViTayCam ?? '')) === 1
            );
        }

        return $candidates->values();
    }

    private function giaHienTai(object $product): float
    {
        return !empty($product->GiaKhuyenMai) && $product->GiaKhuyenMai > 0 && $product->GiaKhuyenMai < $product->GiaBan
            ? (float) $product->GiaKhuyenMai
            : (float) $product->GiaBan;
    }

    private function phanTramGiamGia(object $product): int
    {
        return $this->giaHienTai($product) < $product->GiaBan
            ? (int) round(($product->GiaBan - $this->giaHienTai($product)) / $product->GiaBan * 100)
            : 0;
    }

    private function dinhDangTien(float $amount): string
    {
        return number_format($amount, 0, ',', '.') . 'đ';
    }

    // Khung trình bày: tóm tắt -> phân tích -> nên chọn -> mẫu phù hợp + lý do -> kết luận -> mời hỏi tiếp.
    private function trinhBayTuVan(
        string $loai,
        string $request,
        array $analysis,
        string $recommendation,
        \Illuminate\Support\Collection $picks,
        callable $explain,
        string $emptyMessage,
        array $options
    ): string {
        $reply = ($options['intro'] ?? 'Dạ, cảm ơn bạn đã chia sẻ!')
            . ' Mình tóm tắt nhu cầu của bạn: <b>' . e($this->tomTatNhuCau($loai, $request)) . '</b>.'
            . '<br><br><b>Phân tích nhu cầu</b><br>• ' . implode('<br>• ', $analysis)
            . '<br><b>➜ Nên chọn:</b> ' . $recommendation;

        $upsell = $options['upsell'] ?? null;
        $closing = $this->loiMoiHoiTiep($options);
        $renderPick = function (object $product) use ($explain): array {
            [$fits, $warnings] = $explain($product);

            return [
                $this->taoTheSanPham($product)
                    . "<div style='font-size:13px;margin:-6px 0 12px 4px;'>"
                    . ($fits ? '✔ Hợp với bạn vì: ' . implode('; ', $fits) . '.' : '')
                    . ($warnings ? ($fits ? '<br>' : '') . '⚠ Cần lưu ý: ' . implode('; ', $warnings) . '.' : '')
                    . '</div>',
                $fits,
                $warnings,
            ];
        };

        if ($picks->isEmpty() && $upsell === null) {
            return $reply . '<br><br>' . $emptyMessage . $closing . $this->danhDauKetQuaTuVan($loai, $request, collect(), $options);
        }

        $reasons = [];
        $warningsById = [];
        if ($picks->isNotEmpty()) {
            $reply .= '<br><br><b>' . (!empty($options['chi_trong'])
                ? 'Xếp hạng trong các mẫu bạn đưa (hợp nhất ở trên cùng)'
                : (!empty($options['alternative_poor_fit'])
                ? 'Mẫu khác còn trong kho'
                : (!empty($options['poor_fit'])
                ? 'Mẫu gần nhất trong tầm giá của bạn'
                : ($picks->count() > 1 ? 'Các mẫu hợp với bạn nhất trong kho' : 'Mẫu hợp với bạn nhất trong kho')))) . '</b><br>';
            foreach ($picks as $product) {
                [$html, $fits, $warnings] = $renderPick($product);
                $reasons[(int) $product->ID_SanPham] = $fits;
                $warningsById[(int) $product->ID_SanPham] = $warnings;
                $reply .= $html;
            }
        }

        if ($upsell !== null) {
            [$html, $fits] = $renderPick($upsell);
            $reply .= '<b>Nếu nới ngân sách lên khoảng ' . $this->dinhDangTien($this->giaHienTai($upsell)) . '</b><br>' . $html;
            $current = $picks->first();
            $reply .= '<b>Kết luận:</b> '
                . ($current !== null
                    ? 'Trong tầm giá bạn nêu, kho chưa có cây thật sự hợp với bạn (' . e($current->TenSanPham) . ': '
                        . implode(', ', array_slice($warningsById[(int) $current->ID_SanPham] ?? [], 0, 2)) . '). '
                    : 'Trong tầm giá bạn nêu, kho hiện chưa có mẫu phù hợp. ')
                . 'Mình khuyên bạn <b>nên nới ngân sách để mua ' . e($upsell->TenSanPham) . '</b>'
                . ($fits ? ' vì ' . implode(', ', array_slice($fits, 0, 3)) : '') . '.'
                . ($current !== null ? ' Nếu bắt buộc giữ ngân sách, ' . e($current->TenSanPham) . ' chỉ là lựa chọn tạm.' : '');
            $picks = $picks->push($upsell);
        } elseif (!empty($options['alternative_poor_fit'])) {
            $best = $picks->first();
            $reply .= '<b>Kết luận:</b> Đây là mẫu khác còn lại trong kho nhưng chưa thật sự hợp với bạn ('
                . implode(', ', array_slice($warningsById[(int) $best->ID_SanPham] ?? [], 0, 2)) . '). '
                . 'Mình vẫn khuyên bạn <b>chọn mẫu đã tư vấn trước đó</b>, trừ khi bạn muốn đổi tiêu chí.';
        } else {
            $best = $picks->first();
            $bestReasons = array_slice($reasons[(int) $best->ID_SanPham] ?? [], 0, 3);
            $reply .= '<b>Kết luận: ' . ($options['ket_luan'] ?? 'Nên mua ' . e($best->TenSanPham) . '.') . '</b> '
                . (!empty($options['ket_luan'])
                    ? 'Hai món này đều khớp nhu cầu của bạn' . ($bestReasons ? ': ' . implode(', ', $bestReasons) : '') . ', mặc cùng nhau thành một bộ ra sân.'
                    : ($bestReasons ? 'Đây là mẫu khớp nhu cầu của bạn nhất: ' . implode(', ', $bestReasons) . '.' : 'Đây là mẫu khớp nhu cầu của bạn nhất trong kho hiện tại.'));
            // Chọn trong danh sách khách đưa mà mẫu tốt nhất vẫn còn điểm chưa hợp => nói rõ.
            $bestWarnings = $warningsById[(int) $best->ID_SanPham] ?? [];
            if (!empty($options['chi_trong']) && $bestWarnings) {
                $reply .= ' Lưu ý: mẫu này vẫn còn điểm chưa thật khớp (' . implode(', ', array_slice($bestWarnings, 0, 2))
                    . '). Nếu muốn, mình có thể tìm thêm trong cả kho mẫu đúng nhu cầu hơn.';
            }
        }

        return $reply . $closing . $this->danhDauKetQuaTuVan($loai, $request, $picks, $options);
    }

    // Mời hỏi tiếp; khách chưa kể hết (đơn/đôi, lực tay...) thì mời bổ sung để tinh chỉnh.
    private function loiMoiHoiTiep(array $options): string
    {
        $askMore = array_intersect($options['invite_more'] ?? [], self::Y_HOI_THEM)
            ? 'Để tư vấn sát hơn nữa, bạn cho mình biết thêm bạn đánh đơn hay đôi, chơi mấy buổi một tuần, lực tay thế nào và có hay đau khuỷu tay/cổ tay không nhé. '
            : 'Bạn thấy thế nào ạ? ';

        return '<br><br>' . $askMore . 'Nếu muốn so sánh kỹ các mẫu, xem mẫu khác, mẫu rẻ hơn, hoặc đổi tiêu chí nào, bạn cứ nhắn mình.';
    }

    private function danhDauKetQuaTuVan(string $loai, string $request, \Illuminate\Support\Collection $picks, array $options): string
    {
        $seen = array_values(array_unique([
            ...($options['seen'] ?? []),
            ...$picks->map(fn ($product) => (int) $product->ID_SanPham)->all(),
        ]));

        return sprintf('<!--tuvan-loai:%s-->', $loai)
            . sprintf('<!--tuvan-ketqua:%s-->', base64_encode($request))
            . sprintf('<!--tuvan-da-xem:%s-->', implode(',', $seen));
    }

    private function moTaNganSach(string $lower): string
    {
        $budget = $this->layNganSach($lower);

        return $this->coGioiHanNganSach($budget)
            ? 'Với ngân sách ' . e(preg_replace('/^ngân sách\s*/u', '', $budget['label'])) . ', mình chỉ chọn trong các mẫu nằm trong tầm giá này.'
            : 'Bạn chưa giới hạn ngân sách nên mình ưu tiên mẫu hợp nhu cầu nhất trước, giá sau.';
    }

    // ---------- Vợt ----------

    private function laySoU(string $weight): int
    {
        return preg_match('/([2-5])\s*U/iu', $weight, $matches) ? (int) $matches[1] : 0;
    }

    private function phanLoaiCanBang(string $balance): string
    {
        return match (true) {
            preg_match('/(đầu nặng|nặng đầu|head heavy)/iu', $balance) === 1 => 'nang',
            preg_match('/(đầu nhẹ|nhẹ đầu|head light)/iu', $balance) === 1 => 'nhe',
            preg_match('/(cân bằng|even)/iu', $balance) === 1 => 'deu',
            default => '',
        };
    }

    private function phanLoaiDoCung(string $stiffness): string
    {
        return match (true) {
            preg_match('/rất\s*cứng/iu', $stiffness) === 1 => 'rat_cung',
            preg_match('/(cứng|stiff)/iu', $stiffness) === 1 => 'cung',
            preg_match('/(trung bình|medium)/iu', $stiffness) === 1 => 'trung_binh',
            preg_match('/(dẻo|flexible)/iu', $stiffness) === 1 => 'deo',
            default => '',
        };
    }

    // Từ nhu cầu => cấu hình vợt nên chọn, kèm lời giải thích vì sao.
    private function taoHoSoVot(string $lower): array
    {
        $level = $this->layMaTrinhDo($lower);
        $style = $this->layLoiChoi($lower);
        $playing = $this->layCachChoi($lower);
        $arm = $this->layTheLuc($lower);
        $isWeak = in_array($arm, ['yeu', 'dau_tay'], true);
        $analysis = [];

        [$weights, $stiffness] = match ($level) {
            'MoiChoi' => [[5, 4], ['deo', 'trung_binh']],
            'NangCao' => [[4, 3], ['trung_binh', 'cung']],
            'ChuyenNghiep' => [[4, 3], ['cung', 'rat_cung']],
            default => [[4, 5], ['trung_binh', 'deo']],
        };
        $analysis[] = match ($level) {
            'MoiChoi' => 'Bạn mới chơi nên cần cây vợt nhẹ tay, dễ điều khiển: ưu tiên 4U–5U và thân dẻo/trung bình để vợt tự trợ lực, đỡ mỏi và hạn chế đau tay khi kỹ thuật chưa ổn định.',
            'NangCao' => 'Bạn đã có kỹ thuật nền tảng nên dùng được vợt 3U–4U, thân trung bình đến cứng để kiểm soát cầu chính xác hơn.',
            'ChuyenNghiep' => 'Ở trình độ chuyên nghiệp, thân cứng giúp cú đánh nhanh và chính xác, đổi lại đòi hỏi lực vung và kỹ thuật tốt.',
            default => 'Bạn chưa chắc trình độ nên mình chọn cấu hình an toàn, dễ làm quen: khoảng 4U, thân trung bình.',
        };

        if ($style === 'tan_cong' && $level === 'MoiChoi') {
            // Người mới: đầu nặng + thân cứng khó điều khiển, dễ đau tay; nên bắt đầu từ cân bằng / hơi nặng đầu.
            $balances = ['deu', 'nang'];
            $analysis[] = 'Bạn thích tấn công nhưng mới chơi, nên bắt đầu với vợt cân bằng hoặc hơi nặng đầu, thân dẻo: vẫn có lực đập mà không bị nặng tay; vợt đầu nặng + thân cứng hợp với người đã có lực và kỹ thuật vung.';
        } elseif ($style === 'tan_cong') {
            $balances = ['nang'];
            $analysis[] = 'Bạn thích tấn công nên vợt đầu nặng giúp cú đập uy lực hơn nhờ quán tính dồn về đầu vợt.';
        } elseif ($style === 'phong_thu') {
            $balances = ['nhe', 'deu'];
            $analysis[] = 'Bạn thiên phòng thủ – phản tạt nên cần vợt đầu nhẹ hoặc cân bằng để xoay trở nhanh khi đỡ cầu.';
        } else {
            $balances = ['deu'];
            $analysis[] = $style === 'toan_dien'
                ? 'Lối công thủ toàn diện hợp vợt cân bằng: đủ lực khi tấn công mà vẫn linh hoạt khi phòng thủ.'
                : 'Chưa rõ lối chơi nên vợt cân bằng là lựa chọn dễ thích nghi nhất.';
        }

        if ($playing['format'] === 'doi') {
            $analysis[] = 'Đánh đôi có nhịp cầu nhanh, nhiều pha chặn lưới và phản tạt, nên vợt không quá nặng đầu sẽ giúp bạn kịp tay hơn.';
            $balances = $style === 'tan_cong' ? ['nang', 'deu'] : ['nhe', 'deu'];
        } elseif ($playing['format'] === 'don') {
            $analysis[] = 'Đánh đơn phải phủ sân và đánh cầu sâu cuối sân nhiều, vợt cân bằng hoặc hơi nặng đầu giúp bạn đỡ tốn sức.';
            if ($style !== 'phong_thu') {
                $balances = array_values(array_unique([...$balances, 'deu', 'nang']));
            }
        } elseif ($playing['format'] === 'ca_hai') {
            $analysis[] = 'Bạn chơi cả đơn lẫn đôi nên vợt cân bằng là cấu hình đa dụng nhất.';
            $balances = array_values(array_unique([...$balances, 'deu']));
        }
        if ($playing['purpose'] === 'thi_dau') {
            $analysis[] = 'Bạn tập để thi đấu nên đáng đầu tư cây có thông số rõ ràng, đúng lối đánh của mình.';
        } elseif ($playing['purpose'] === 'phong_trao') {
            $analysis[] = 'Chơi phong trào thì ưu tiên cây dễ đánh, bền, không cần thông số quá khắt khe.';
        }
        if (($playing['frequency'] ?? 0) >= 4) {
            $analysis[] = 'Bạn chơi nhiều buổi mỗi tuần nên chọn vợt khung chắc, và nhớ thay cước, quấn cán định kỳ.';
        }

        if ($arm === 'dau_tay') {
            $weights = [5, 4];
            $stiffness = ['deo', 'trung_binh'];
            $analysis[] = 'Bạn hay đau tay nên cần vợt nhẹ, thân dẻo để giảm rung và áp lực lên khuỷu/cổ tay; tránh căng cước quá cao.';
        } elseif ($arm === 'yeu') {
            $weights = [5, 4];
            $stiffness = ['deo', 'trung_binh'];
            $analysis[] = 'Lực tay/cổ tay chưa mạnh nên tránh vợt thân cứng + đầu nặng; vợt nhẹ, thân dẻo sẽ trợ lực và không gây mỏi.';
        } elseif ($arm === 'khoe') {
            $stiffness = array_values(array_unique([...$stiffness, 'cung']));
            if ($style === 'tan_cong' && $level !== 'MoiChoi') {
                $weights = [3, 4];
            }
            $analysis[] = 'Lực tay tốt nên bạn khai thác được thân cứng hơn để cú đánh nhanh và chính xác.';
        }
        if ($isWeak && $style === 'tan_cong') {
            $balances = ['deu', 'nang'];
            $analysis[] = 'Vì lực tay chưa mạnh, nếu vẫn thích tấn công thì nên chọn vợt cân bằng hoặc hơi nặng đầu nhưng thân dẻo, thay vì đầu nặng + thân cứng.';
        }
        if ($this->muonVotNhe($lower)) {
            $weights = [5, 4];
            $analysis[] = 'Bạn muốn vợt nhẹ, dễ điều khiển nên mình chọn 4U–5U: vung nhanh, ít mỏi tay, dễ sửa động tác.';
        }

        // Cỡ cán (ChuViTayCam): G5 nhỏ hơn G4.
        $grip = $this->layCoCan($lower);
        if ($grip !== null) {
            $analysis[] = $grip === 'G5'
                ? 'Bàn tay nhỏ nên chọn cán G5 (nhỏ hơn G4) để cầm chắc mà cổ tay vẫn linh hoạt.'
                : 'Bàn tay to nên chọn cán G4 để cầm đầy tay, không phải bóp chặt khi đánh.';
        }

        // Mức căng cước nên dùng (đối chiếu cột MucCangToiDa).
        $tension = match (true) {
            $isWeak || $level === 'MoiChoi' => [20, 24],
            $level === 'ChuyenNghiep' || $arm === 'khoe' => [24, 28],
            default => [22, 26],
        };
        $analysis[] = 'Mức căng cước nên dùng khoảng ' . $tension[0] . '–' . $tension[1] . ' lbs'
            . ($isWeak || $level === 'MoiChoi' ? ' (căng vừa phải giúp cầu đi nhẹ, đỡ đau tay)' : '')
            . '; mình chỉ chọn vợt có mức căng tối đa chịu được mức này.';
        $analysis[] = $this->moTaNganSach($lower);

        $balanceNames = ['nang' => 'đầu nặng', 'nhe' => 'đầu nhẹ', 'deu' => 'cân bằng'];
        $stiffnessNames = ['deo' => 'dẻo', 'trung_binh' => 'trung bình', 'cung' => 'cứng', 'rat_cung' => 'rất cứng'];
        $sortedWeights = array_values(array_unique($weights));
        sort($sortedWeights);

        return [
            'level' => in_array($level, ['MoiChoi', 'NangCao', 'ChuyenNghiep'], true) ? $level : null,
            'weights' => array_values(array_unique($weights)),
            'balances' => array_values(array_unique($balances)),
            'stiffness' => array_values(array_unique($stiffness)),
            'grip' => $grip,
            'tension' => $tension,
            'analysis' => $analysis,
            'recommendation' => 'vợt ' . implode('–', array_map(fn ($weight) => $weight . 'U', $sortedWeights))
                . ', điểm cân bằng ' . implode(' hoặc ', array_map(fn ($balance) => $balanceNames[$balance], array_values(array_unique($balances))))
                . ', thân ' . implode(' đến ', array_map(fn ($stiff) => $stiffnessNames[$stiff], array_values(array_unique($stiffness))))
                . ($grip !== null ? ', cán ' . $grip : '')
                . ', căng cước khoảng ' . $tension[0] . '–' . $tension[1] . ' lbs.',
        ];
    }

    private function diemTheoHoSoVot(object $product, array $profile): int
    {
        $score = 0;
        if ($profile['level'] !== null) {
            $score += ($product->TrinhDoPhuHop ?? null) === $profile['level'] ? 20 : -20;
        }
        $weight = $this->laySoU((string) ($product->TrongLuong ?? ''));
        $score += $weight === 0 ? 0 : (in_array($weight, $profile['weights'], true) ? 5 : -4);
        $balance = $this->phanLoaiCanBang((string) ($product->DiemCanBang ?? ''));
        $score += $balance === '' ? 0 : (in_array($balance, $profile['balances'], true) ? 6 : -5);
        $stiffness = $this->phanLoaiDoCung((string) ($product->DoCung ?? ''));
        $score += $stiffness === '' ? 0 : (in_array($stiffness, $profile['stiffness'], true) ? 6 : -5);
        if (!empty($profile['grip']) && !empty($product->ChuViTayCam)) {
            $score += strtoupper(trim($product->ChuViTayCam)) === $profile['grip'] ? 3 : -3;
        }
        $maxTension = $this->layMucCangToiDa((string) ($product->MucCangToiDa ?? ''));
        if (!empty($profile['tension']) && $maxTension > 0 && $maxTension < $profile['tension'][0]) {
            $score -= 6;
        }

        return $score;
    }

    private function giaiThichVotPhuHop(object $product, array $profile, string $lower): array
    {
        $fits = [];
        $warnings = [];
        if ($profile['level'] !== null) {
            if (($product->TrinhDoPhuHop ?? null) === $profile['level']) {
                $fits[] = 'đúng ' . $this->tenTrinhDo($profile['level']);
            } elseif (!empty($product->TrinhDoPhuHop)) {
                $warnings[] = 'hãng xếp mẫu này cho ' . $this->tenTrinhDo($product->TrinhDoPhuHop);
            }
        }

        $weight = $this->laySoU((string) ($product->TrongLuong ?? ''));
        if ($weight !== 0) {
            if (in_array($weight, $profile['weights'], true)) {
                $fits[] = 'nặng ' . e($product->TrongLuong) . ' đúng mức nên chọn';
            } else {
                $warnings[] = 'trọng lượng ' . e($product->TrongLuong) . ($weight < min($profile['weights']) ? ' nặng hơn' : ' nhẹ hơn') . ' mức khuyên dùng';
            }
        }

        $balanceName = mb_strtolower(e(preg_replace('/\s*\(.*\)$/u', '', (string) ($product->DiemCanBang ?? ''))));
        $balance = $this->phanLoaiCanBang((string) ($product->DiemCanBang ?? ''));
        if ($balance !== '') {
            if (in_array($balance, $profile['balances'], true)) {
                $fits[] = $balanceName . ' hợp cách bạn đánh';
            } else {
                $warnings[] = $balanceName . ' chưa đúng lối chơi bạn mô tả';
            }
        }

        $stiffness = $this->phanLoaiDoCung((string) ($product->DoCung ?? ''));
        if ($stiffness !== '') {
            $order = ['deo' => 0, 'trung_binh' => 1, 'cung' => 2, 'rat_cung' => 3];
            $stiffnessName = mb_strtolower(e($product->DoCung));
            if (in_array($stiffness, $profile['stiffness'], true)) {
                $fits[] = 'thân ' . $stiffnessName . ' hợp lực tay và trình độ của bạn';
            } elseif ($order[$stiffness] > max(array_map(fn ($stiff) => $order[$stiff], $profile['stiffness']))) {
                $warnings[] = 'thân ' . $stiffnessName . ' cứng hơn mức khuyên dùng, cần lực vung tốt';
            } else {
                $warnings[] = 'thân ' . $stiffnessName . ' mềm hơn mức khuyên dùng';
            }
        }

        if (!empty($profile['grip']) && !empty($product->ChuViTayCam)) {
            if (strtoupper(trim($product->ChuViTayCam)) === $profile['grip']) {
                $fits[] = 'cán ' . e($product->ChuViTayCam) . ' vừa tay bạn';
            } else {
                $warnings[] = 'cán ' . e($product->ChuViTayCam) . ' khác cỡ ' . $profile['grip'] . ' bạn cần (có thể quấn thêm cán nếu cán nhỏ)';
            }
        }
        $maxTension = $this->layMucCangToiDa((string) ($product->MucCangToiDa ?? ''));
        if (!empty($profile['tension']) && $maxTension > 0) {
            if ($maxTension >= $profile['tension'][1]) {
                $fits[] = 'mức căng tối đa ' . e($product->MucCangToiDa) . ' đủ cho mức căng khuyên dùng';
            } elseif ($maxTension >= $profile['tension'][0]) {
                $fits[] = 'mức căng tối đa ' . e($product->MucCangToiDa) . ', nên căng không quá ' . $maxTension . ' lbs';
            } else {
                $warnings[] = 'mức căng tối đa chỉ ' . e($product->MucCangToiDa) . ', thấp hơn mức căng bạn cần';
            }
        }

        [$budgetFit, $budgetWarning] = $this->danhGiaNganSach($product, $lower);
        $fits = [...$fits, ...$budgetFit];
        $warnings = [...$warnings, ...$budgetWarning];
        if ($this->phanTramGiamGia($product) > 0) {
            $fits[] = 'đang giảm ' . $this->phanTramGiamGia($product) . '%';
        }

        return [$fits, $warnings];
    }

    // Giá có nằm trong ngân sách khách nêu không: [[lý do hợp], [cảnh báo]].
    private function danhGiaNganSach(object $product, string $lower): array
    {
        $budget = $this->layNganSach($lower);
        if (!$this->coGioiHanNganSach($budget)) {
            return [[], []];
        }
        $price = $this->giaHienTai($product);
        if (!empty($budget['max']) && $price > $budget['max']) {
            return [[], ['giá ' . $this->dinhDangTien($price) . ' vượt ngân sách ' . e(preg_replace('/^ngân sách\s*/u', '', $budget['label']))]];
        }

        return [['giá ' . $this->dinhDangTien($price) . ' nằm trong ngân sách'], []];
    }

    private function ketLuanTuVanVot(string $request, array $options = []): string
    {
        $lower = mb_strtolower($request);
        $profile = $this->taoHoSoVot($lower);
        $baseQuery = fn () => $this->truyVanSanPhamDangBan()->whereNotNull('thongsovot.ID_SanPham');
        $rank = fn ($products) => $products
            ->sort(function ($left, $right) use ($profile) {
                $comparison = $this->diemTheoHoSoVot($right, $profile) <=> $this->diemTheoHoSoVot($left, $profile);

                return $comparison !== 0 ? $comparison : $this->giaHienTai($left) <=> $this->giaHienTai($right);
            })
            ->values();

        $picks = $rank($this->layUngVienTuVan($baseQuery(), $lower, $options))->take(2)->values();

        // Trong tầm giá không có cây thật sự hợp => tìm mẫu hợp nhất nếu nới ngân sách để khuyên khách.
        $isPoorFit = fn ($product) => $this->diemTheoHoSoVot($product, $profile) < 0;
        // Khách hỏi "mẫu khác" mà mẫu còn lại không hợp => nói thật, khuyên giữ mẫu đã tư vấn.
        if (!empty($options['exclude']) && $picks->isNotEmpty() && $isPoorFit($picks->first())) {
            $options['alternative_poor_fit'] = true;
        } elseif (empty($options['chi_trong']) && $this->coGioiHanNganSach($this->layNganSach($lower)) && ($picks->isEmpty() || $isPoorFit($picks->first()))) {
            $upsell = $rank($this->layUngVienTuVan($baseQuery(), $lower, $options, false))
                ->first(fn ($product) => !$isPoorFit($product) && !$picks->contains('ID_SanPham', $product->ID_SanPham));
            if ($upsell !== null) {
                $options['upsell'] = $upsell;
                $options['poor_fit'] = $picks->isNotEmpty();
                $picks = $picks->take(1);
            }
        }

        $emptyMessage = !empty($options['exclude']) || !empty($options['cheaperThan']) || !empty($options['lighterThan'])
            ? 'Hiện trong kho chưa còn mẫu vợt nào khác đáp ứng yêu cầu này. Mẫu đã tư vấn vẫn là lựa chọn hợp nhất; bạn có thể nới ngân sách hoặc đổi một tiêu chí để mình tìm thêm.'
            : 'Hiện trong kho chưa có vợt nào khớp các tiêu chí này. Bạn có thể nới ngân sách hoặc bỏ bớt một tiêu chí để mình tìm lại nhé.';

        return $this->trinhBayTuVan(
            'vot',
            $request,
            $profile['analysis'],
            $profile['recommendation'],
            $picks,
            fn ($product) => $this->giaiThichVotPhuHop($product, $profile, $lower),
            $emptyMessage,
            $options
        );
    }

    private function coCanShortlistVot(string $request, array $state): bool
    {
        return $this->coGioiHanNganSach($this->layNganSach($request))
            && (empty($state['trinh_do']) || empty($state['loi_choi']));
    }

    private function traDanhSachVotCanLamRo(string $request, array $missingMain, array $missing): string
    {
        $lower = mb_strtolower($request);
        $profile = $this->taoHoSoVot($lower);
        $candidates = $this->layUngVienTuVan(
            $this->truyVanSanPhamDangBan()->whereNotNull('thongsovot.ID_SanPham'),
            $lower,
            []
        )->sort(function ($left, $right) use ($profile) {
            $score = $this->diemTheoHoSoVot($right, $profile) <=> $this->diemTheoHoSoVot($left, $profile);

            return $score !== 0 ? $score : $this->giaHienTai($left) <=> $this->giaHienTai($right);
        })->take(3)->values();

        $reply = 'Dạ, mình lọc trước các mẫu vợt còn hàng trong ngân sách bạn nêu. Đây mới là danh sách tham khảo, mình chưa chốt mẫu nên mua:';
        foreach ($candidates as $product) {
            $reply .= '<br><br>' . $this->taoTheSanPham($product);
        }
        if ($candidates->isEmpty()) {
            $reply .= '<br><br>Hiện chưa có vợt còn hàng trong mức giá này. Bạn muốn tăng ngân sách hay đổi khoảng giá ạ?';
        } else {
            $missingSlots = array_values(array_intersect(['trinh_do', 'loi_choi'], $missingMain));
            if ($missingSlots) {
                $reply .= '<br><br>Để mình so sánh shortlist và chọn mẫu hợp nhất, bạn cho mình biết '
                    . implode(' và ', array_map(
                        fn ($slot) => $slot === 'trinh_do'
                            ? 'trình độ (mới chơi, nâng cao hay chuyên nghiệp)'
                            : 'lối chơi (tấn công, phòng thủ hay toàn diện)',
                        $missingSlots
                    )) . ' nhé.';
            } elseif (in_array('chi_tiet', $missing, true)) {
                $reply .= '<br><br>Để chọn mẫu chính xác hơn, bạn cho mình biết thêm lực tay hoặc thường đánh đơn/đôi nhé.';
            }
        }

        return $reply . $this->danhDauKetQuaTuVan('vot', $request, $candidates, []);
    }

    // ---------- Giày ----------

    private function layMatSan(string $lower): ?string
    {
        return match (true) {
            preg_match('/(xi măng|ngoài trời|bê tông)/iu', $lower) === 1 => 'sân xi măng/ngoài trời',
            preg_match('/thảm/iu', $lower) === 1 => 'sân thảm',
            preg_match('/(sàn gỗ|sân gỗ)/iu', $lower) === 1 => 'sàn gỗ',
            default => null,
        };
    }

    private function coSizeSanPham(string $availableSizes, string $requestedSize): bool
    {
        foreach (preg_split('/[,;\/|\s]+/', mb_strtoupper($availableSizes), -1, PREG_SPLIT_NO_EMPTY) as $size) {
            if ($size === mb_strtoupper($requestedSize)) {
                return true;
            }
        }

        return false;
    }

    private function ketLuanTuVanGiay(string $request, array $options = []): string
    {
        $lower = mb_strtolower($request);
        $size = $this->laySizeGiay($lower);
        $needs = $this->layNhuCauTheoMoTa('giay', $lower);
        $court = $this->layMatSan($lower);

        $analysis = [$size !== null
            ? 'Bạn mang size ' . $size . ' nên mình ưu tiên các mẫu cửa hàng đang có size này.'
            : 'Bạn chưa rõ size, nên đo chiều dài bàn chân hoặc thử trực tiếp trước khi chốt.'];
        if (preg_match('/(bè|rộng)/iu', $lower)) {
            $analysis[] = 'Bàn chân bè nên chọn form rộng hoặc tăng nửa size, và thử cùng tất thể thao để không bị bó ngang bàn chân.';
        } elseif (preg_match('/(thon|hẹp)/iu', $lower)) {
            $analysis[] = 'Bàn chân thon nên chọn form ôm để chân không bị trượt trong giày khi đổi hướng.';
        }
        if ($court === 'sân xi măng/ngoài trời') {
            $analysis[] = 'Sân xi măng/ngoài trời làm đế mòn rất nhanh, nên ưu tiên đế bền và đừng dùng chung đôi giày đánh sân thảm.';
        } elseif ($court !== null) {
            $analysis[] = 'Chơi ' . $court . ' thì giày cầu lông chuyên dụng đế cao su là đúng loại, vừa bám sân vừa không làm hỏng mặt sân.';
        }
        $needAdvice = [
            'êm, giảm chấn' => 'Bạn cần độ êm nên đế giảm chấn rất quan trọng để bảo vệ gối và gót khi bật nhảy.',
            'nhẹ, linh hoạt' => 'Bạn ưu tiên nhẹ, linh hoạt để bứt tốc và đổi hướng nhanh.',
            'độ bền cao' => 'Bạn chơi nhiều/sân dễ mòn nên chọn giày có độ bền cao.',
            'thoáng khí' => 'Chân hay nóng thì cần thân giày lưới thoáng khí.',
            'ổn định, ôm chân' => 'Bạn cần độ ổn định, ôm chân để hạn chế lật cổ chân.',
            'hợp chơi phong trào' => 'Chơi phong trào thì một đôi giày chuyên dụng tầm trung là đủ, không cần dòng thi đấu cao cấp.',
            'hợp thi đấu' => 'Thi đấu thì nên đầu tư đôi có độ ổn định và giảm chấn tốt hơn.',
        ];
        foreach ($needs as $need) {
            $analysis[] = $needAdvice[$need['label']];
        }
        $analysis[] = $this->moTaNganSach($lower);
        $recommendation = 'giày cầu lông chuyên dụng đế cao su'
            . ($needs ? ', ' . implode(', ', array_column($needs, 'label')) : '')
            . ($size !== null ? ', đúng size ' . $size : '') . '.';

        $candidates = $this->layUngVienTuVan(
            $this->truyVanSanPhamDangBan()->where('danhmuc.TenDanhMuc', 'like', '%Giày%'),
            $lower,
            $options
        );
        $hasSize = fn ($product) => $size !== null && $this->coSizeSanPham((string) ($product->SizeCoSan ?? ''), $size);
        if ($size !== null && $candidates->contains($hasSize)) {
            $candidates = $candidates->filter($hasSize);
        }
        $picks = $candidates
            ->sort(function ($left, $right) use ($needs) {
                $comparison = count($this->nhuCauCoTrongMoTa($right, $needs)) <=> count($this->nhuCauCoTrongMoTa($left, $needs));

                return $comparison !== 0 ? $comparison : $this->giaHienTai($left) <=> $this->giaHienTai($right);
            })
            ->take(2)
            ->values();

        $explain = function ($product) use ($needs, $size, $hasSize, $lower) {
            $matched = $this->nhuCauCoTrongMoTa($product, $needs);
            $fits = $matched ? ['theo mô tả của cửa hàng có ' . implode(', ', $matched)] : [];
            $warnings = [];
            if ($size !== null) {
                if ($hasSize($product)) {
                    $fits[] = 'có size ' . $size;
                } else {
                    $warnings[] = 'chưa có size ' . $size . ' (đang có: ' . e($product->SizeCoSan ?: 'chưa cập nhật') . ')';
                }
            }
            $missing = array_diff(array_column($needs, 'label'), $matched);
            if ($missing) {
                $warnings[] = 'mô tả chưa nhắc tới ' . implode(', ', $missing) . ', bạn nên thử trực tiếp';
            }
            [$budgetFit, $budgetWarning] = $this->danhGiaNganSach($product, $lower);
            $fits = [...$fits, ...$budgetFit];
            $warnings = [...$warnings, ...$budgetWarning];
            if ($this->phanTramGiamGia($product) > 0) {
                $fits[] = 'đang giảm ' . $this->phanTramGiamGia($product) . '%';
            }

            return [$fits, $warnings];
        };

        return $this->trinhBayTuVan(
            'giay',
            $request,
            $analysis,
            $recommendation,
            $picks,
            $explain,
            'Hiện trong kho chưa có đôi giày nào khớp các tiêu chí này. Bạn có thể nới ngân sách hoặc đổi size để mình tìm lại nhé.',
            $options
        );
    }

    // ---------- Chọn trong các mẫu vừa gợi ý ----------

    // "chọn cho tôi trong các mẫu bên trên", "trong mấy cây vừa gợi ý nên mua cây nào"...
    private function laChonTrongMauDaGoiY(string $lower): bool
    {
        if (preg_match('/(so sánh|đối chiếu|khác nhau)/iu', $lower)) {
            return false;
        }
        $refersToList = preg_match('/((?:các|mấy|những|hai|ba|\d)\s*(?:mẫu|cây|đôi|cái|chiếc|sản phẩm|loại|vợt|giày|áo|quần|váy)(?:\s+(?:vợt|giày|áo|quần|váy))?\s*(?:ở|bên|phía)?\s*trên|(?:ở|bên|phía) trên|danh sách (?:trên|vừa rồi|đó)|vừa (?:gợi ý|tư vấn|đưa|giới thiệu|liệt kê|gửi)|trong (?:số )?đó)/iu', $lower) === 1;
        $asksPick = preg_match('/(chọn|lấy|mua|nên|(?:cái|mẫu|cây|đôi|chiếc) nào|tư vấn|phù hợp|hợp (?:với|nhất)|tốt nhất)/iu', $lower) === 1;

        return $refersToList && $asksPick;
    }

    private function chonTrongMauDaGoiY(string $message, array $history): ?string
    {
        // Các mẫu trong câu trả lời gần nhất của chatbot có hiện sản phẩm.
        $ids = [];
        foreach (array_reverse($history) as $entry) {
            if (($entry['sender'] ?? null) === 'Chatbot'
                && preg_match_all('/\/san-pham\/(\d+)/', (string) ($entry['content'] ?? ''), $matches)) {
                $ids = array_values(array_unique(array_map('intval', $matches[1])));
                break;
            }
        }
        if (count($ids) < 2) {
            return null;
        }

        // Danh sách có thể lẫn nhiều loại: ưu tiên loại khách nhắc tới, không thì loại chiếm nhiều nhất.
        $typeById = DB::table('sanpham')
            ->leftJoin('danhmuc', 'sanpham.ID_DanhMuc', '=', 'danhmuc.ID_DanhMuc')
            ->whereIn('sanpham.ID_SanPham', $ids)
            ->pluck('danhmuc.TenDanhMuc', 'sanpham.ID_SanPham')
            ->map(fn ($category) => match (true) {
                preg_match('/vợt/iu', (string) $category) === 1 => 'vot',
                preg_match('/giày/iu', (string) $category) === 1 => 'giay',
                preg_match('/(quần áo|váy)/iu', (string) $category) === 1 => 'quan_ao',
                default => 'phu_kien',
            });
        $counts = array_count_values($typeById->all());
        arsort($counts);
        $mentioned = $this->layLoaiSanPhamNhacToi(mb_strtolower($message));
        $loai = $mentioned !== null && isset($counts[$mentioned]) ? $mentioned : array_key_first($counts);
        $ids = array_values(array_filter($ids, fn ($id) => ($typeById[$id] ?? null) === $loai));
        if (count($ids) < 2) {
            return null;
        }

        // Giữ nhu cầu đã tư vấn trước đó (nếu cùng loại), thông tin mới nói sau sẽ ghi đè.
        $context = $this->layNguCanhTuVan($history);
        $base = $context !== null && $context['loai'] === $loai ? $context['request'] : '';
        $request = trim($base . ' ' . $this->chuanHoaTraLoiTuVan($message, null));

        return $this->ketLuanTuVan($loai, $request, [
            'intro' => 'Dạ, mình chỉ xét trong ' . count($ids) . ' mẫu vừa gợi ý ở trên, phân tích theo thông tin của bạn rồi chọn ra mẫu nên mua.',
            'chi_trong' => $ids,
            'seen' => $context['seen'] ?? [],
        ]);
    }

    // ---------- Hỏi còn hàng theo loại (vợt / giày, có thể kèm hãng, trình độ, size) ----------

    private function lietKeConHangTheoLoai(string $message, string $lower): ?string
    {
        $asksStock = preg_match('/(còn hàng|còn không|còn ko|còn k(?!\p{L})|có hàng|có sẵn|hết hàng|còn bán|đang bán|có bán|còn mẫu|còn cây|còn đôi)/u', $lower) === 1;
        $loai = $this->layLoaiSanPhamNhacToi($lower);
        // Hỏi đúng một mẫu cụ thể (tên dòng vợt) => để kịch bản thông tin sản phẩm trả lời.
        $namesModel = preg_match('/(astrox|nanoflare|arcsaber|duora|voltric|thruster|ryuga|axforce|power cushion|jetspeed|auraspeed|halbertec|tectonic|bg65|\b\d{2,3}z+\d*\b)/iu', $lower) === 1;
        if (!$asksStock || !in_array($loai, ['vot', 'giay'], true) || $namesModel || $this->laYeuCauSoSanh($lower)) {
            return null;
        }

        $request = $this->chuanHoaTraLoiTuVan($message, null);
        $query = $this->truyVanSanPhamDangBan(false);
        if ($loai === 'vot') {
            $query->whereNotNull('thongsovot.ID_SanPham');
            $level = $this->layMaTrinhDo($lower);
            if (in_array($level, ['MoiChoi', 'NangCao', 'ChuyenNghiep'], true)) {
                $query->where('thongsovot.TrinhDoPhuHop', $level);
            }
        } else {
            $query->where('danhmuc.TenDanhMuc', 'like', '%Giày%');
        }
        $brand = preg_match(self::THUONG_HIEU_PATTERN, $lower, $brandMatch) ? str_replace('-', '', $brandMatch[1]) : null;
        if ($brand !== null) {
            $query->where('thuonghieu.TenThuongHieu', 'like', '%' . $brand . '%');
        }
        $products = $query->get()->unique('ID_SanPham');
        $size = $loai === 'giay' ? $this->laySizeGiay($lower) : null;
        $conHang = $products->filter(fn ($p) => (int) $p->SoLuongTon > 0
            && ($size === null || $this->coSizeSanPham((string) ($p->SizeCoSan ?? ''), $size)))
            ->sortBy(fn ($p) => $this->giaHienTai($p))->values();

        $tenLoai = ($loai === 'vot' ? 'vợt' : 'giày') . ($brand !== null ? ' ' . ucfirst($brand) : '')
            . (isset($level) && $level && $level !== 'ChuaRo' ? ' cho ' . $this->tenTrinhDo($level) : '')
            . ($size !== null ? ' size ' . $size : '');

        if ($conHang->isEmpty()) {
            $hetHang = $products->count();
            $reply = 'Dạ, hiện ' . e($tenLoai) . ($hetHang > 0 ? ' đã <b>tạm hết hàng</b> (' . $hetHang . ' mẫu đang chờ nhập thêm).' : ' chưa có trong kho.')
                . '<br>Mình có thể tư vấn cho bạn mẫu ' . ($loai === 'vot' ? 'vợt' : 'giày') . ' khác đang còn hàng.';
            // Hãng đã hết => tư vấn tiếp không giới hạn hãng nữa (nếu không sẽ không còn mẫu nào để gợi ý).
            $request = trim(preg_replace(self::THUONG_HIEU_PATTERN, '', $request));
        } else {
            $reply = 'Dạ, <b>' . e($tenLoai) . '</b> hiện <b>còn hàng ' . $conHang->count() . ' mẫu</b>:<br><br>';
            foreach ($conHang->take(6) as $product) {
                $reply .= $this->taoTheSanPham($product)
                    . "<div style='font-size:12px;color:#16a34a;margin:-8px 0 10px 4px;'>✔ Còn " . (int) $product->SoLuongTon . ' sản phẩm</div>';
            }
            if ($conHang->count() > 6) {
                $reply .= 'Và ' . ($conHang->count() - 6) . ' mẫu khác, bạn xem thêm ở trang sản phẩm nhé.<br>';
            }
        }

        // Hỏi tiếp để tư vấn chọn mẫu (ý nào khách đã nói thì không hỏi lại).
        $state = $this->layTrangThaiTuVan($loai, $request);
        $slots = $loai === 'vot' ? ['trinh_do', 'loi_choi', 'ngan_sach'] : ['giay_size', 'giay_nhu_cau', 'ngan_sach'];
        $missing = array_values(array_filter($slots, fn ($slot) => !$state[$slot]));
        if (!$missing) {
            return $reply . '<br>Bạn muốn mình phân tích để chọn ra mẫu hợp nhất với bạn không? Chỉ cần nhắn "tư vấn giúp mình" nhé.'
                . sprintf('<!--tuvan:%s:chi_tiet--><!--tuvan-yeucau:%s-->', $loai, base64_encode($request));
        }
        $hoi = [
            'trinh_do' => 'trình độ (mới chơi, nâng cao hay chuyên nghiệp)',
            'loi_choi' => 'lối chơi (tấn công, phòng thủ hay toàn diện)',
            'ngan_sach' => 'ngân sách dự kiến',
            'giay_size' => 'size giày bạn mang',
            'giay_nhu_cau' => 'bạn chơi sân nào và ưu tiên êm, nhẹ hay bền',
        ];
        $reply .= '<br>Để mình tư vấn mẫu hợp với bạn nhất' . ($conHang->isEmpty() ? '' : ' trong số này') . ', bạn cho mình biết '
            . implode(', ', array_map(fn ($slot) => $hoi[$slot], $missing)) . ' nhé.';

        // Ghi nhớ nhu cầu (hãng, trình độ...) để câu trả lời sau được ghép đúng.
        return $reply . sprintf('<!--tuvan:%s:%s--><!--tuvan-yeucau:%s-->', $loai, $missing[0], base64_encode($request));
    }

    // ---------- Liệt kê vợt theo trình độ ----------

    private function lietKeVotTheoTrinhDo(string $message, string $lower): ?string
    {
        $level = $this->layMaTrinhDo($lower);
        $asksList = preg_match('/((?:các|những|mấy)\s*(?:loại|mẫu|cây|dòng|kiểu)?\s*vợt|liệt kê|danh sách|có những|gồm những|vợt nào (?:dành cho|cho|phù hợp))/iu', $lower) === 1;
        if (!$asksList || !$this->laYeuCauVot($lower) || !in_array($level, ['MoiChoi', 'NangCao', 'ChuyenNghiep'], true)
            || $this->laYeuCauSoSanh($lower)) {
            return null;
        }
        // Khách đã nói đủ trình độ + lối chơi + ngân sách => tư vấn chọn mẫu luôn như kịch bản cũ.
        $request = $this->chuanHoaTraLoiTuVan($message, null);
        $state = $this->layTrangThaiTuVan('vot', $request);
        $missing = array_values(array_filter(['loi_choi', 'ngan_sach'], fn ($slot) => !$state[$slot]));
        if (!$missing) {
            return null;
        }

        // Chỉ vợt đúng trình độ (lọc thêm thương hiệu / ngân sách nếu khách có nói), rẻ trước.
        $rackets = $this->layUngVienTuVan(
            $this->truyVanSanPhamDangBan()->where('thongsovot.TrinhDoPhuHop', $level),
            mb_strtolower($request),
            []
        )->sortBy(fn ($product) => $this->giaHienTai($product))->values();

        $levelName = $this->tenTrinhDo($level);
        if ($rackets->isEmpty()) {
            $reply = 'Dạ, hiện trong kho chưa còn mẫu vợt nào dành cho ' . $levelName . ' khớp yêu cầu của bạn.';
        } else {
            $reply = 'Dạ, đây là ' . ($rackets->count() > 1 ? 'các mẫu' : 'mẫu') . ' vợt dành cho <b>' . $levelName . '</b> đang có trong kho ('
                . $rackets->count() . ' mẫu):<br><br>';
            foreach ($rackets as $product) {
                $reply .= $this->taoTheSanPham($product);
            }
        }
        $asks = array_map(fn ($slot) => $slot === 'loi_choi'
            ? 'lối chơi (thích tấn công đập cầu, thiên phòng thủ – phản tạt hay công thủ toàn diện)'
            : 'ngân sách dự kiến', $missing);
        $reply .= '<br>Để mình chọn ra cây hợp nhất với bạn, bạn cho mình biết thêm ' . implode(' và ', $asks)
            . ' nhé. Nếu bạn hay đánh đơn hay đôi, lực tay thế nào, bạn kể kèm luôn để mình tư vấn sát hơn ạ.';

        // Marker câu hỏi để câu trả lời tiếp theo đi vào phiên tư vấn vợt.
        return $reply . sprintf('<!--tuvan:vot:%s-->', $missing[0]);
    }

    // ---------- Quần áo, váy ----------

    // ao / quan / vay / ao_khoac / bo (cả bộ quần áo), hoặc null.
    private function layLoaiQuanAo(string $lower): ?string
    {
        $text = preg_replace('/(?:bộ\s*)?quần\s*áo/u', ' bộđồ ', $lower);

        return $this->layGiaTriSauCung($text, [
            'bo' => '/(bộđồ|bộ đồ|cả bộ)/iu',
            'ao_khoac' => '/(áo khoác|hoodie|khoác)/iu',
            'vay' => '/((?<!\p{L})váy|(?<!\p{L})đầm(?!\p{L}))/iu',
            'quan' => '/(?<!\p{L})quần(?!\p{L})(?!\s*(?:trong|bảo hộ))/iu',
            'ao' => '/(?<!\p{L})áo(?!\p{L})(?!\s*khoác)/iu',
        ]);
    }

    private function tenLoaiQuanAo(string $type): string
    {
        return match ($type) {
            'ao' => 'áo cầu lông',
            'quan' => 'quần cầu lông',
            'vay' => 'váy cầu lông',
            'ao_khoac' => 'áo khoác/hoodie',
            default => 'bộ quần áo cầu lông',
        };
    }

    private function laySizeQuanAo(string $lower): ?string
    {
        return preg_match_all('/(?:size|cỡ|mặc|số)\s*(xxl|xl|xs|s|m|l)(?!\p{L})/iu', $lower, $matches)
            ? mb_strtoupper(end($matches[1]))
            : null;
    }

    private function layCanNang(string $lower): ?int
    {
        $weight = null;
        if (preg_match_all('/(\d{2,3})(?:[.,]\d)?\s*(?:kg|kí|ký|ki lô|cân)(?!\p{L})/iu', $lower, $matches)) {
            $weight = (int) end($matches[1]);
        } elseif (preg_match_all('/(?:nặng|cân nặng)\s*(?:khoảng|tầm|là)?\s*(\d{2,3})(?!\d)/iu', $lower, $matches)) {
            $weight = (int) end($matches[1]);
        }

        return $weight !== null && $weight >= 25 && $weight <= 150 ? $weight : null;
    }

    private function layChieuCao(string $lower): ?int
    {
        $height = null;
        if (preg_match_all('/(?<!\d)1\s*m\s*(\d{1,2})(?!\d)/iu', $lower, $matches)) {
            $digits = end($matches[1]);
            $height = 100 + (strlen($digits) === 1 ? (int) $digits * 10 : (int) $digits);
        } elseif (preg_match_all('/(?<!\d)(1[.,]\d{1,2})\s*m(?!\p{L})/iu', $lower, $matches)) {
            $height = (int) round((float) str_replace(',', '.', end($matches[1])) * 100);
        } elseif (preg_match_all('/(?:(\d{3})\s*cm|cao\s*(?:khoảng|tầm|là)?\s*(\d{3})(?!\d))/iu', $lower, $matches, PREG_SET_ORDER)) {
            $last = end($matches);
            $height = (int) ($last[2] ?? '' ?: $last[1]);
        }

        return $height !== null && $height >= 120 && $height <= 210 ? $height : null;
    }

    private function layGioiTinh(string $lower): ?string
    {
        return $this->layGiaTriSauCung($lower, [
            'nu' => '/((?<!\p{L})nữ(?!\p{L})|con gái|bạn gái|vợ (?:tôi|mình|em)|(?<!\p{L})váy|(?<!\p{L})đầm(?!\p{L}))/iu',
            'nam' => '/((?<!\p{L})nam(?!\p{L})|con trai|bạn trai|chồng (?:tôi|mình|em))/iu',
        ]);
    }

    // Size theo bảng (cân nặng, chiều cao: lấy size lớn hơn để không bị chật/ngắn).
    private function tinhSizeTheoBang(string $gender, ?int $weight, ?int $height): string
    {
        $chart = self::BANG_SIZE_QUAN_AO[$gender];
        $sizes = array_keys($chart);
        $indexOf = function (?int $value, int $column) use ($chart, $sizes): int {
            if ($value === null) {
                return 0;
            }
            foreach ($sizes as $index => $size) {
                if ($value <= $chart[$size][$column]) {
                    return $index;
                }
            }

            return count($sizes) - 1;
        };

        return $sizes[max($indexOf($weight, 0), $indexOf($height, 1))];
    }

    // ['size' => 'M', 'from_chart' => bool, 'gender' => nam/nu/null, 'alt' => size nếu là nữ khi chưa rõ giới tính]
    private function goiYSizeQuanAo(string $lower): ?array
    {
        $gender = $this->layGioiTinh($lower) ?? ($this->layLoaiQuanAo($lower) === 'vay' ? 'nu' : null);
        $explicit = $this->laySizeQuanAo($lower);
        if ($explicit !== null) {
            return ['size' => $explicit, 'from_chart' => false, 'gender' => $gender, 'alt' => null];
        }
        $weight = $this->layCanNang($lower);
        $height = $this->layChieuCao($lower);
        if ($weight === null && $height === null) {
            return null;
        }

        return [
            'size' => $this->tinhSizeTheoBang($gender ?? 'nam', $weight, $height),
            'from_chart' => true,
            'gender' => $gender,
            'alt' => $gender === null ? $this->tinhSizeTheoBang('nu', $weight, $height) : null,
            'weight' => $weight,
            'height' => $height,
        ];
    }

    // Câu giải thích size gợi ý theo cân nặng/chiều cao (null nếu khách tự nói size hoặc chưa có số đo).
    private function moTaSizeQuanAo(string $lower): ?string
    {
        $suggestion = $this->goiYSizeQuanAo($lower);
        if ($suggestion === null || !$suggestion['from_chart']) {
            return null;
        }
        $measures = array_filter([
            $suggestion['weight'] !== null ? 'cân nặng ' . $suggestion['weight'] . 'kg' : null,
            $suggestion['height'] !== null ? 'chiều cao ' . $suggestion['height'] . 'cm' : null,
        ]);
        $text = 'Với ' . implode(' và ', $measures) . ', theo bảng size đồ thể thao ' . ($suggestion['gender'] === 'nu' ? 'nữ' : 'nam')
            . ' bạn hợp <b>size ' . $suggestion['size'] . '</b>';
        if ($suggestion['alt'] !== null && $suggestion['alt'] !== $suggestion['size']) {
            $text .= ' (nếu bạn là nữ thì khoảng size ' . $suggestion['alt'] . ')';
        }
        $text .= '.';
        $smallest = $suggestion['gender'] === 'nu' ? 36 : 45;
        if ($suggestion['weight'] !== null && $suggestion['weight'] < $smallest) {
            $text .= ' Đây đã là size nhỏ nhất thường có nên mặc có thể hơi rộng; nếu thích mặc ôm, bạn chọn mẫu có size nhỏ hơn hoặc form nữ/trẻ em.';
        }
        if ($suggestion['height'] === null || $suggestion['weight'] === null) {
            $text .= ' Bạn cho mình thêm ' . ($suggestion['height'] === null ? 'chiều cao' : 'cân nặng') . ' thì size sẽ chuẩn hơn.';
        }

        return $text;
    }

    private function laQuanAoLoai(object $product, string $type): bool
    {
        $name = mb_strtolower((string) ($product->TenSanPham ?? ''));
        $isSkirt = str_contains(mb_strtolower((string) ($product->TenDanhMuc ?? '')), 'váy') || str_starts_with($name, 'váy');
        $isJacket = str_contains($name, 'khoác') || str_contains($name, 'hoodie');

        return match ($type) {
            'vay' => $isSkirt,
            'ao_khoac' => $isJacket,
            'quan' => str_starts_with($name, 'quần'),
            'ao' => str_starts_with($name, 'áo') && !$isJacket,
            default => !$isJacket,
        };
    }

    private function ketLuanTuVanQuanAo(string $request, array $options = []): string
    {
        $lower = mb_strtolower($request);
        $type = $this->layLoaiQuanAo($lower) ?? 'bo';
        $suggestion = $this->goiYSizeQuanAo($lower);
        $size = $suggestion['size'] ?? null;
        $gender = $this->layGioiTinh($lower) ?? ($type === 'vay' ? 'nu' : null);
        $needs = $this->layNhuCauTheoMoTa('quan_ao', $lower);

        $analysis = [];
        if ($size !== null && !$suggestion['from_chart']) {
            $analysis[] = 'Bạn mặc size ' . $size . ' nên mình ưu tiên các mẫu còn size này.';
        } elseif ($size !== null) {
            $analysis[] = $this->moTaSizeQuanAo($lower);
        } else {
            $analysis[] = 'Bạn chưa cho mình cân nặng, chiều cao nên mình chưa chốt được size; trước khi đặt bạn nhắn cân nặng/chiều cao để mình chọn size giúp nhé.';
        }
        $analysis[] = match ($type) {
            'ao' => 'Áo cầu lông nên chọn vải thấm hút, thoáng để không bết người khi ra nhiều mồ hôi, form vừa người để vung tay thoải mái.',
            'quan' => 'Quần cầu lông nên có cạp chun co giãn, ống rộng vừa phải để bước chân và bật nhảy thoải mái.',
            'vay' => 'Váy cầu lông nên có quần bảo hộ bên trong và chất vải co giãn để tự tin di chuyển, bật nhảy.',
            'ao_khoac' => 'Áo khoác/hoodie dùng để giữ ấm khi khởi động hoặc nghỉ giữa trận, không mặc khi thi đấu.',
            default => 'Một bộ ra sân gồm áo + quần (hoặc váy) cùng chất vải thấm hút, co giãn là đủ dùng.',
        };
        $needAdvice = [
            'thấm hút mồ hôi, thoáng mát' => 'Bạn cần vải thấm hút, thoáng mát vì cầu lông ra rất nhiều mồ hôi.',
            'co giãn, thoải mái vận động' => 'Bạn ưu tiên co giãn, thoải mái để không bị vướng khi đổi hướng, bật nhảy.',
            'giữ ấm, chống lạnh' => 'Bạn cần giữ ấm nên chọn loại có lót bông hoặc 2 lớp.',
            'lớp bảo hộ bên trong' => 'Bạn muốn kín đáo, tự tin nên cần lớp bảo hộ bên trong.',
            'hợp thi đấu' => 'Đi thi đấu thì nên chọn mẫu chính hãng, form gọn, chất vải nhẹ.',
            'giá phải chăng' => 'Bạn ưu tiên giá mềm nên mình xếp mẫu rẻ hơn lên trước khi chất lượng tương đương.',
        ];
        foreach ($needs as $need) {
            $analysis[] = $needAdvice[$need['label']];
        }
        if ($gender === 'nu' && $type !== 'vay') {
            $analysis[] = 'Mẫu ghi rõ "nam" là form nam, nữ mặc thường rộng hơn khoảng một size.';
        }
        $wantsCheap = in_array('giá phải chăng', array_column($needs, 'label'), true);
        if (!$wantsCheap || $this->layNganSach($lower) !== null) {
            $analysis[] = $this->moTaNganSach($lower);
        }
        $recommendation = $this->tenLoaiQuanAo($type)
            . ($size !== null ? ', size ' . $size : '')
            . ($needs ? ', ' . implode(', ', array_column($needs, 'label')) : '') . '.';

        $candidates = $this->layUngVienTuVan(
            $this->truyVanSanPhamDangBan()->where(fn ($query) => $query
                ->where('danhmuc.TenDanhMuc', 'like', '%Quần Áo%')
                ->orWhere('danhmuc.TenDanhMuc', 'like', '%Váy%')),
            $lower,
            $options
        );
        $hasSize = fn ($product) => $size !== null && $this->coSizeSanPham((string) ($product->SizeCoSan ?? ''), $size);
        $isMenOnly = fn ($product) => preg_match('/(?<!\p{L})nam(?!\p{L})/iu', (string) $product->TenSanPham) === 1;
        if (empty($options['chi_trong'])) {
            $candidates = $candidates->filter(fn ($product) => $this->laQuanAoLoai($product, $type)
                && !($gender === 'nam' && $this->laQuanAoLoai($product, 'vay')));
            // Cả bộ: lọc size theo từng món khi xếp hạng, không lọc chung (tránh mất cả nhóm áo hoặc quần).
            if ($type !== 'bo' && $size !== null && $candidates->contains($hasSize)) {
                $candidates = $candidates->filter($hasSize);
            }
        }
        // "Giá phải chăng" là cách xếp hạng (rẻ trước), không phải điều tìm trong mô tả.
        $descNeeds = array_values(array_filter($needs, fn ($need) => $need['label'] !== 'giá phải chăng'));
        $rank = fn ($products) => $products->sort(function ($left, $right) use ($hasSize, $descNeeds, $isMenOnly, $gender, $wantsCheap) {
            $score = fn ($product) => ($hasSize($product) ? 100 : 0)
                + ($wantsCheap ? 0 : 10 * count($this->nhuCauCoTrongMoTa($product, $descNeeds)))
                - ($gender === 'nu' && $isMenOnly($product) ? 5 : 0);
            $comparison = $score($right) <=> $score($left);

            return $comparison !== 0 ? $comparison : $this->giaHienTai($left) <=> $this->giaHienTai($right);
        })->values();

        // Cả bộ: chọn 1 áo + 1 quần/váy hợp nhất. Còn lại: 2 mẫu hợp nhất.
        if ($type === 'bo' && empty($options['chi_trong'])) {
            $top = $rank($candidates->filter(fn ($product) => $this->laQuanAoLoai($product, 'ao')))->first();
            $bottomType = $gender === 'nu' ? 'vay' : 'quan';
            $bottom = $rank($candidates->filter(fn ($product) => $this->laQuanAoLoai($product, $bottomType)
                || ($gender === null && $this->laQuanAoLoai($product, 'quan'))))->first();
            $picks = collect([$top, $bottom])->filter()->values();
            if ($picks->count() === 2) {
                $options['ket_luan'] = 'Nên mua bộ ' . e($top->TenSanPham) . ' + ' . e($bottom->TenSanPham) . '.';
            }
        } else {
            $picks = $rank($candidates)->take(2)->values();
        }

        $explain = function ($product) use ($descNeeds, $size, $hasSize, $lower, $gender, $isMenOnly) {
            $needs = $descNeeds;
            $matched = $this->nhuCauCoTrongMoTa($product, $needs);
            $fits = $matched ? ['theo mô tả của cửa hàng có ' . implode(', ', $matched)] : [];
            $warnings = [];
            if ($size !== null) {
                if ($hasSize($product)) {
                    $fits[] = 'còn size ' . $size;
                } else {
                    $warnings[] = empty($product->SizeCoSan)
                        ? 'shop chưa cập nhật size, bạn nhắn shop kiểm tra size ' . $size . ' trước khi đặt'
                        : 'chưa có size ' . $size . ' (đang có: ' . e($product->SizeCoSan) . ')';
                }
            }
            $missing = array_diff(array_column($needs, 'label'), $matched);
            if ($missing) {
                $warnings[] = 'mô tả chưa nhắc tới ' . implode(', ', $missing);
            }
            if ($gender === 'nu' && $isMenOnly($product)) {
                $warnings[] = 'đây là form nam';
            }
            [$budgetFit, $budgetWarning] = $this->danhGiaNganSach($product, $lower);
            $fits = [...$fits, ...$budgetFit];
            $warnings = [...$warnings, ...$budgetWarning];
            if ($this->phanTramGiamGia($product) > 0) {
                $fits[] = 'đang giảm ' . $this->phanTramGiamGia($product) . '%';
            }

            return [$fits, $warnings];
        };

        return $this->trinhBayTuVan(
            'quan_ao',
            $request,
            $analysis,
            $recommendation,
            $picks,
            $explain,
            'Hiện trong kho chưa có ' . $this->tenLoaiQuanAo($type) . ' nào khớp các tiêu chí này. Bạn có thể đổi loại, nới ngân sách hoặc nhắn shop để được hỗ trợ size nhé.',
            $options
        );
    }

    // ---------- Phụ kiện ----------

    private function laPhuKienLoai(object $product, string $type): bool
    {
        $name = mb_strtolower((string) ($product->TenSanPham ?? ''));
        $terms = match ($type) {
            'cuoc' => ['cước', 'string'],
            'quan_can' => ['quấn cán', 'grip'],
            'bao_vot' => ['túi', 'bao vợt', 'balo'],
            'qua_cau' => ['ống cầu', 'quả cầu', 'cầu nhựa', 'cầu lông vũ'],
            default => [],
        };
        foreach ($terms as $term) {
            if (str_contains($name, $term)) {
                return true;
            }
        }

        return false;
    }

    private function phanTichPhuKien(string $type, string $lower): array
    {
        $analysis = [];
        $arm = $this->layTheLuc($lower);
        if ($type === 'cuoc') {
            if (in_array($arm, ['yeu', 'dau_tay'], true) || preg_match('/(mới chơi|người mới)/iu', $lower)) {
                $analysis[] = 'Lực tay chưa mạnh/mới chơi nên căng vừa phải (khoảng 20–24 lbs) và chọn cước hỗ trợ lực, hấp thụ sốc để cầu đi nhẹ, đỡ đau tay.';
            }
            if (preg_match('/(bền|đứt)/iu', $lower)) {
                $analysis[] = 'Bạn hay đứt dây nên ưu tiên cước bền, sợi dày hơn một chút.';
            }
            if (preg_match('/(nảy|tiếng nổ|âm thanh|đanh|cảm giác)/iu', $lower)) {
                $analysis[] = 'Bạn thích cảm giác nảy, tiếng nổ đanh thì chọn cước mảnh và căng cao hơn khi lực tay cho phép.';
            }
            if (preg_match('/(\d{2})\s*lbs?/iu', $lower, $tension)) {
                $analysis[] = 'Mức căng ' . $tension[1] . ' lbs: nhớ kiểm tra không vượt mức căng tối đa ghi trên vợt của bạn.';
            }
        } elseif ($type === 'quan_can') {
            if (preg_match('/(mồ hôi|trơn|ướt)/iu', $lower)) {
                $analysis[] = 'Tay bạn hay ra mồ hôi nên cần quấn cán thấm hút tốt, bám tay, và nên thay ngay khi bị trơn.';
            }
            if (preg_match('/(dày|cầm chắc)/iu', $lower)) {
                $analysis[] = 'Thích cán dày, chắc tay thì có thể quấn thêm một lớp lên cán gốc.';
            } elseif (preg_match('/mỏng/iu', $lower)) {
                $analysis[] = 'Thích cán mỏng để cảm giác rõ thì thay hẳn lớp cũ thay vì quấn chồng.';
            }
        } elseif ($type === 'bao_vot') {
            $analysis[] = preg_match('/(giày|quần áo|nhiều đồ|\d+\s*(?:cây\s*)?vợt)/iu', $lower)
                ? 'Bạn mang nhiều đồ nên chọn túi nhiều ngăn, có ngăn riêng cho giày để không ám mùi sang vợt.'
                : 'Mang ít đồ thì một túi/bao vợt gọn nhẹ là đủ để bảo vệ khung và dây.';
        } elseif ($type === 'qua_cau') {
            $analysis[] = preg_match('/(trong nhà|lông vũ|thi đấu|chuẩn)/iu', $lower)
                ? 'Đánh trong nhà/thi đấu nên dùng cầu lông vũ cho đường bay chuẩn; chọn tốc độ cầu hợp nhiệt độ nơi bạn chơi.'
                : 'Đánh ngoài trời/tập luyện thì cầu nhựa bền, ít hỏng, tiết kiệm hơn cầu lông vũ.';
        } else {
            $analysis[] = 'Bộ phụ kiện cơ bản nên có: quấn cán (cầm chắc, thấm mồ hôi), cước dự phòng khi đứt dây, ống cầu để tập và túi đựng vợt.';
            if (preg_match('/(mồ hôi|trơn)/iu', $lower)) {
                $analysis[] = 'Tay bạn hay ra mồ hôi nên quấn cán là món nên mua trước.';
            }
        }
        if (!$analysis) {
            $analysis[] = 'Mình chọn theo nhu cầu chung, ưu tiên món bền và dễ dùng.';
        }
        $analysis[] = $this->moTaNganSach($lower);

        return $analysis;
    }

    private function ketLuanTuVanPhuKien(string $request, array $options = []): string
    {
        $lower = mb_strtolower($request);
        $type = $this->layLoaiPhuKien($lower);
        $candidates = $this->layUngVienTuVan(
            $this->truyVanSanPhamDangBan()->where('danhmuc.TenDanhMuc', 'like', '%Phụ Kiện%'),
            $lower,
            $options
        );
        $kitTypes = ['quan_can', 'cuoc', 'qua_cau', 'bao_vot'];

        if ($type === 'tat_ca') {
            // Bộ cơ bản: mỗi loại chọn một món hợp nhất.
            $picks = collect($kitTypes)
                ->map(fn ($itemType) => $candidates->filter(fn ($product) => $this->laPhuKienLoai($product, $itemType))
                    ->sortBy(fn ($product) => $this->giaHienTai($product))
                    ->sortByDesc(fn ($product) => count($this->nhuCauCoTrongMoTa($product, $this->layNhuCauTheoMoTa($itemType, $lower))))
                    ->first())
                ->filter()
                ->values();
            $recommendation = 'đủ 4 món cơ bản, ưu tiên quấn cán và cước trước vì dùng hằng ngày.';
        } else {
            $needs = $this->layNhuCauTheoMoTa($type, $lower);
            $picks = $candidates->filter(fn ($product) => $this->laPhuKienLoai($product, $type))
                ->sort(function ($left, $right) use ($needs) {
                    $comparison = count($this->nhuCauCoTrongMoTa($right, $needs)) <=> count($this->nhuCauCoTrongMoTa($left, $needs));

                    return $comparison !== 0 ? $comparison : $this->giaHienTai($left) <=> $this->giaHienTai($right);
                })
                ->take(2)
                ->values();
            $recommendation = $this->tenLoaiPhuKien($type) . ($needs ? ' có ' . implode(', ', array_column($needs, 'label')) : ' bền, dễ dùng') . '.';
        }

        $explain = function ($product) use ($type, $kitTypes, $lower) {
            $itemType = $type !== 'tat_ca' ? $type : collect($kitTypes)->first(fn ($candidate) => $this->laPhuKienLoai($product, $candidate));
            $needs = $this->layNhuCauTheoMoTa((string) $itemType, $lower);
            $matched = $this->nhuCauCoTrongMoTa($product, $needs);
            $fits = $type === 'tat_ca' ? ['là món ' . $this->tenLoaiPhuKien((string) $itemType) . ' trong bộ cơ bản'] : [];
            if ($matched) {
                $fits[] = 'theo mô tả của cửa hàng có ' . implode(', ', $matched);
            }
            $missing = array_diff(array_column($needs, 'label'), $matched);
            $fits[] = 'giá ' . $this->dinhDangTien($this->giaHienTai($product));

            return [$fits, $missing ? ['mô tả chưa nhắc tới ' . implode(', ', $missing)] : []];
        };

        return $this->trinhBayTuVan(
            'phu_kien',
            $request,
            $this->phanTichPhuKien($type, $lower),
            $recommendation,
            $picks,
            $explain,
            'Hiện cửa hàng chưa có ' . $this->tenLoaiPhuKien($type) . ' phù hợp còn hàng. Bạn muốn mình tư vấn loại phụ kiện khác không ạ?',
            $options
        );
    }

    // ==========================================
    // TRẢ LỜI THEO Ý ĐỊNH CỤ THỂ
    // Khách hỏi một việc rõ ràng (khuyến mãi, mã giảm giá, thông tin một mẫu, rẻ/đắt nhất, theo thương hiệu)
    // thì trả lời đúng việc đó, không đưa vào câu hỏi làm rõ nhu cầu chung.
    // ==========================================

    private function laHoiMaGiamGia(string $lower): bool
    {
        return preg_match('/(mã.{0,12}(?:giảm|khuyến mãi|ưu đãi|sale|freeship)|voucher|coupon|code giảm|dùng mã|nhập mã|áp mã|mã nào)/iu', $lower) === 1;
    }

    private function laHoiKhuyenMai(string $lower): bool
    {
        return !$this->laHoiMaGiamGia($lower)
            && preg_match('/(khuyến mãi|khuyen mai|giảm giá|giam gia|đang giảm|đang sale|\bsale\b|ưu đãi|xả hàng)/iu', $lower) === 1;
    }

    // Danh mục theo loại sản phẩm khách nhắc tới: [tên danh mục trong DB, tên gọi], hoặc null.
    private function layDanhMucTheoCau(string $lower): ?array
    {
        return match ($this->layLoaiSanPhamNhacToi($lower)) {
            'vot' => ['Vợt', 'vợt'],
            'giay' => ['Giày', 'giày'],
            'phu_kien' => ['Phụ Kiện', 'phụ kiện'],
            'quan_ao' => preg_match('/(váy|đầm)/iu', $lower) ? ['Váy', 'váy'] : ['Quần Áo', 'quần áo'],
            default => null,
        };
    }

    private function locTheoDanhMucVaThuongHieu(Builder $query, string $lower, ?array $category): void
    {
        if ($category !== null) {
            $query->where('danhmuc.TenDanhMuc', 'like', '%' . $category[0] . '%');
        }
        if (preg_match(self::THUONG_HIEU_PATTERN, $lower, $brand)) {
            $query->where('thuonghieu.TenThuongHieu', 'like', '%' . str_replace('-', '', $brand[1]) . '%');
        }
    }

    private function xuLyTheoYDinh(string $message, string $lower): ?string
    {
        $hasNeeds = $this->layMaTrinhDo($lower) !== null || $this->layLoiChoi($lower) !== null;
        $category = $this->layDanhMucTheoCau($lower);

        if ($this->laHoiMaGiamGia($lower)) {
            return $this->traLoiMaGiamGia($lower);
        }
        // Có nêu trình độ / lối chơi thì để phần tư vấn theo nhu cầu xử lý (đã lọc sẵn hàng khuyến mãi).
        if ($this->laHoiKhuyenMai($lower) && !$hasNeeds && !preg_match('/(này|vừa tư vấn|vừa gợi ý|ở trên)/iu', $lower)) {
            return $this->lietKeKhuyenMai($lower, $category);
        }
        if (preg_match('/(rẻ nhất|giá thấp nhất|giá mềm nhất|đắt nhất|giá cao nhất|cao cấp nhất|xịn nhất)/iu', $lower) && !$hasNeeds) {
            return $this->lietKeTheoGia($lower, $category, preg_match('/(rẻ nhất|thấp nhất|mềm nhất)/iu', $lower) === 1);
        }
        if (preg_match('/(giá|bao nhiêu|thông số|còn hàng|còn không|hết hàng|chi tiết|thế nào|ra sao|có tốt không|hợp với ai|phù hợp với ai)/iu', $lower)) {
            $product = $this->timSanPhamCuThe($message);
            if ($product !== null) {
                return $this->thongTinSanPham($product);
            }
        }
        if (preg_match('/(thương hiệu nào|hãng nào|những hãng|những thương hiệu)/iu', $lower)) {
            return $this->lietKeThuongHieu($category);
        }
        if (preg_match(self::THUONG_HIEU_PATTERN, $lower) && !$hasNeeds
            && preg_match('/(có những|những mẫu|mẫu nào|loại nào|có bán|danh sách|liệt kê|các mẫu|có .{0,20}không)/iu', $lower)) {
            return $this->lietKeTheoThuongHieu($lower, $category);
        }

        return null;
    }

    // Tìm đúng một mẫu khách nhắc tên (phải khớp ít nhất một từ riêng của mẫu, không chỉ thương hiệu/loại).
    private function timSanPhamCuThe(string $message): ?object
    {
        $terms = $this->layTuKhoaTenSanPham($message);
        $genericWords = ['yonex', 'victor', 'lining', 'li', 'ning', 'mizuno', 'kumpoo', 'kawasaki', 'apacs', 'vnb',
            'giày', 'giay', 'áo', 'quần', 'váy', 'túi', 'ống', 'cước', 'size', 'triệu', 'nghìn', 'dưới', 'trên', 'khoảng', 'tầm'];
        $specificTerms = array_values(array_diff($terms, $genericWords));
        if (!$specificTerms) {
            return null;
        }

        $product = $this->timSanPhamTheoTen($message, false);
        if ($product === null) {
            return null;
        }
        $name = mb_strtolower($product->TenSanPham);
        foreach ($specificTerms as $term) {
            if (str_contains($name, $term)) {
                return $product;
            }
        }

        return null;
    }

    private function thongTinSanPham(object $product): string
    {
        $isRacket = !empty($product->DoCung);
        $details = [];
        if ($isRacket) {
            $details = [
                'Trình độ phù hợp' => ucfirst($this->tenTrinhDo($product->TrinhDoPhuHop)) ?: '—',
                'Trọng lượng' => e($product->TrongLuong ?: '—'),
                'Điểm cân bằng' => e($product->DiemCanBang ?: '—'),
                'Độ cứng' => e($product->DoCung ?: '—'),
                'Cán' => e($product->ChuViTayCam ?: '—'),
                'Mức căng tối đa' => e($product->MucCangToiDa ?: '—'),
            ];
        } elseif (!empty($product->SizeCoSan)) {
            $details['Size có sẵn'] = e($product->SizeCoSan);
        }
        $details['Tình trạng'] = (int) $product->SoLuongTon > 0 ? 'Còn ' . (int) $product->SoLuongTon . ' sản phẩm' : 'Tạm hết hàng';

        $reply = 'Dạ, đây là thông tin <b>' . e($product->TenSanPham) . '</b>:<br><br>' . $this->taoTheSanPham($product);
        foreach ($details as $label => $value) {
            $reply .= '• <b>' . $label . ':</b> ' . $value . '<br>';
        }
        if ($this->phanTramGiamGia($product) > 0) {
            $reply .= '• <b>Khuyến mãi:</b> giảm ' . $this->phanTramGiamGia($product) . '% (tiết kiệm '
                . $this->dinhDangTien((float) $product->GiaBan - $this->giaHienTai($product)) . ')<br>';
        }
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $product->MoTa)));
        if ($description !== '') {
            $reply .= '• <b>Mô tả:</b> ' . e(mb_strimwidth($description, 0, 220, '…')) . '<br>';
        }
        if ($isRacket) {
            $reply .= '<br>' . $this->moTaNguoiChoiPhuHop($product)
                . '<br>Bạn cho mình biết trình độ và lối chơi, mình sẽ xem mẫu này có thật sự hợp với bạn không nhé.';
        }
        if ((int) $product->SoLuongTon <= 0) {
            $similar = $this->truyVanSanPhamDangBan()
                ->where('sanpham.ID_DanhMuc', DB::table('sanpham')->where('ID_SanPham', $product->ID_SanPham)->value('ID_DanhMuc'))
                ->where('sanpham.ID_SanPham', '!=', $product->ID_SanPham)
                ->get()->unique('ID_SanPham')
                ->sortBy(fn ($item) => abs($this->giaHienTai($item) - $this->giaHienTai($product)))
                ->take(2);
            if ($similar->isNotEmpty()) {
                $reply .= '<br><br><b>Mẫu này đang hết hàng, bạn có thể tham khảo mẫu cùng loại, giá gần nhất:</b><br>'
                    . $similar->map(fn ($item) => $this->taoTheSanPham($item))->implode('');
            }
        }
        $loai = $isRacket ? 'vot' : (str_contains(mb_strtolower((string) $product->TenDanhMuc), 'giày') ? 'giay' : 'phu_kien');

        return $reply . $this->danhDauKetQuaTuVan($loai, $loai === 'vot' ? 'vợt' : ($loai === 'giay' ? 'giày' : 'phụ kiện'), collect([$product]), []);
    }

    private function lietKeKhuyenMai(string $lower, ?array $category): string
    {
        $query = $this->truyVanSanPhamDangBan()
            ->whereNotNull('sanpham.GiaKhuyenMai')
            ->where('sanpham.GiaKhuyenMai', '>', 0)
            ->whereColumn('sanpham.GiaKhuyenMai', '<', 'sanpham.GiaBan');
        $this->locTheoDanhMucVaThuongHieu($query, $lower, $category);
        $this->apDungNganSach($query, $this->layNganSach($lower));
        $products = $query->get()->unique('ID_SanPham')->sortByDesc(fn ($product) => $this->phanTramGiamGia($product))->take(5)->values();
        $label = $category[1] ?? 'sản phẩm';

        if ($products->isEmpty()) {
            $others = DB::table('sanpham')->join('danhmuc', 'sanpham.ID_DanhMuc', '=', 'danhmuc.ID_DanhMuc')
                ->where('sanpham.TrangThai', 'DangBan')->where('sanpham.SoLuongTon', '>', 0)
                ->where('sanpham.GiaKhuyenMai', '>', 0)->whereColumn('sanpham.GiaKhuyenMai', '<', 'sanpham.GiaBan')
                ->groupBy('danhmuc.TenDanhMuc')->selectRaw('danhmuc.TenDanhMuc, COUNT(*) as SoLuong')->get();

            return 'Dạ, hiện chưa có ' . e($label) . ' nào đang giảm giá' . ($this->layNganSach($lower) ? ' trong mức giá bạn nêu' : '') . '.'
                . ($others->isNotEmpty()
                    ? ' Các nhóm đang có khuyến mãi: ' . $others->map(fn ($row) => e($row->TenDanhMuc) . ' (' . $row->SoLuong . ' mẫu)')->implode(', ') . '. Bạn muốn xem nhóm nào ạ?'
                    : '')
                . ' Bạn cũng có thể nhắn "mã giảm giá" để xem các mã đang áp dụng khi thanh toán.';
        }

        $deepest = $products->first();
        $cheapest = $products->sortBy(fn ($product) => $this->giaHienTai($product))->first();
        $reply = 'Dạ, hiện có ' . $products->count() . ' ' . e($label) . ' đang khuyến mãi, xếp theo mức giảm từ cao đến thấp:<br><br>'
            . $products->map(fn ($product) => $this->taoTheSanPham($product))->implode('')
            . '• <b>Giảm sâu nhất:</b> ' . e($deepest->TenSanPham) . ' – giảm ' . $this->phanTramGiamGia($deepest) . '%, tiết kiệm '
            . $this->dinhDangTien((float) $deepest->GiaBan - $this->giaHienTai($deepest)) . '.<br>'
            . '• <b>Rẻ nhất sau giảm:</b> ' . e($cheapest->TenSanPham) . ' – chỉ còn ' . $this->dinhDangTien($this->giaHienTai($cheapest)) . '.'
            . '<br><br>Ngoài ra bạn có thể dùng thêm mã giảm giá khi thanh toán (nhắn "mã giảm giá" để xem). ';
        $reply .= ($category[0] ?? null) === 'Vợt' || $category === null
            ? 'Nếu bạn cho mình biết trình độ và lối chơi, mình sẽ chọn trong các mẫu đang giảm giá cây hợp với bạn nhất nhé.'
            : 'Bạn cho mình biết nhu cầu sử dụng, mình sẽ chọn món hợp với bạn nhất trong số này nhé.';
        $loai = match ($category[0] ?? 'Vợt') {
            'Giày' => 'giay',
            'Phụ Kiện' => 'phu_kien',
            default => 'vot',
        };

        return $reply . $this->danhDauKetQuaTuVan($loai, ($loai === 'vot' ? 'vợt' : $label) . ' khuyến mãi', $products, []);
    }

    private function lietKeTheoGia(string $lower, ?array $category, bool $cheapestFirst): string
    {
        $query = $this->truyVanSanPhamDangBan();
        $this->locTheoDanhMucVaThuongHieu($query, $lower, $category);
        $products = $query->get()->unique('ID_SanPham')
            ->sortBy(fn ($product) => $this->giaHienTai($product), SORT_REGULAR, !$cheapestFirst)
            ->take(3)->values();
        $label = $category[1] ?? 'sản phẩm';
        if ($products->isEmpty()) {
            return 'Dạ, hiện chưa có ' . e($label) . ' nào còn hàng phù hợp yêu cầu này ạ.';
        }

        return 'Dạ, đây là ' . $products->count() . ' ' . e($label) . ($cheapestFirst ? ' có giá thấp nhất' : ' có giá cao nhất')
            . ' đang còn hàng:<br><br>' . $products->map(fn ($product) => $this->taoTheSanPham($product))->implode('')
            . ($cheapestFirst
                ? 'Lưu ý: rẻ nhất chưa chắc đã hợp với bạn. '
                : 'Mẫu giá cao thường dành cho người chơi có kỹ thuật tốt. ')
            . 'Bạn cho mình biết nhu cầu (trình độ, lối chơi, ngân sách) để mình tư vấn mẫu phù hợp nhất nhé.';
    }

    private function lietKeTheoThuongHieu(string $lower, ?array $category): string
    {
        $query = $this->truyVanSanPhamDangBan();
        $this->locTheoDanhMucVaThuongHieu($query, $lower, $category);
        $this->apDungNganSach($query, $this->layNganSach($lower));
        $products = $query->get()->unique('ID_SanPham')->sortBy(fn ($product) => $this->giaHienTai($product))->take(6)->values();
        preg_match(self::THUONG_HIEU_PATTERN, $lower, $brand);
        $label = ($category[1] ?? 'sản phẩm') . ' ' . ucfirst($brand[1] ?? '');
        if ($products->isEmpty()) {
            return 'Dạ, hiện cửa hàng chưa có ' . e(trim($label)) . ' nào còn hàng ạ. Bạn muốn xem thương hiệu khác không?';
        }

        return 'Dạ, cửa hàng đang có ' . $products->count() . ' ' . e(trim($label)) . ' còn hàng (xếp theo giá):<br><br>'
            . $products->map(fn ($product) => $this->taoTheSanPham($product))->implode('')
            . 'Bạn cho mình biết nhu cầu của bạn, mình sẽ chọn mẫu hợp nhất trong số này nhé.';
    }

    private function lietKeThuongHieu(?array $category): string
    {
        $query = DB::table('sanpham')
            ->join('thuonghieu', 'sanpham.ID_ThuongHieu', '=', 'thuonghieu.ID_ThuongHieu')
            ->join('danhmuc', 'sanpham.ID_DanhMuc', '=', 'danhmuc.ID_DanhMuc')
            ->where('sanpham.TrangThai', 'DangBan')->where('sanpham.SoLuongTon', '>', 0);
        if ($category !== null) {
            $query->where('danhmuc.TenDanhMuc', 'like', '%' . $category[0] . '%');
        }
        $brands = $query->groupBy('thuonghieu.TenThuongHieu')
            ->selectRaw('thuonghieu.TenThuongHieu, COUNT(*) as SoLuong')->orderByDesc('SoLuong')->get();
        if ($brands->isEmpty()) {
            return 'Dạ, hiện chưa có sản phẩm nào còn hàng trong nhóm này ạ.';
        }

        return 'Dạ, cửa hàng đang có các thương hiệu' . ($category !== null ? ' ' . e($category[1]) : '') . ': '
            . $brands->map(fn ($row) => '<b>' . e($row->TenThuongHieu) . '</b> (' . $row->SoLuong . ' mẫu)')->implode(', ')
            . '. Bạn muốn xem các mẫu của thương hiệu nào ạ?';
    }

    private function traLoiMaGiamGia(string $lower): string
    {
        $today = now()->toDateString();
        $codes = DB::table('khuyenmai')
            ->where('TrangThai', 'HoatDong')
            ->whereDate('NgayBatDau', '<=', $today)
            ->whereDate('NgayKetThuc', '>=', $today)
            ->where(fn ($query) => $query->whereNull('SoLuong')->orWhere('SoLuong', '>', 0))
            ->orderBy('GiaTriDonToiThieu')
            ->get();
        if ($codes->isEmpty()) {
            return 'Dạ, hiện cửa hàng chưa có mã giảm giá nào đang hoạt động. Bạn có thể nhắn "sản phẩm khuyến mãi" để xem các mẫu đang giảm giá trực tiếp nhé.';
        }

        // Số tiền giảm của một mã cho đơn có giá trị $orderValue (ưu tiên mức % ghi trong mô tả).
        $discountOf = function ($code, float $orderValue): float {
            if ($orderValue < $code->GiaTriDonToiThieu) {
                return 0;
            }
            if (preg_match('/(\d+)\s*%/', (string) $code->MoTa, $percent) || $code->LoaiGiam === 'PhanTram') {
                return $orderValue * ((int) ($percent[1] ?? $code->GiaTriGiam)) / 100;
            }

            return (float) $code->GiaTriGiam;
        };

        $reply = 'Dạ, các mã giảm giá đang áp dụng khi thanh toán:<br>';
        foreach ($codes as $code) {
            $reply .= '• <b>' . e($code->MaCode) . '</b>: ' . e($code->MoTa ?: 'ưu đãi')
                . ' – áp dụng đơn từ ' . $this->dinhDangTien((float) $code->GiaTriDonToiThieu)
                . ', hạn đến ' . date('d/m/Y', strtotime($code->NgayKetThuc))
                . ($code->SoLuong !== null ? ', còn ' . (int) $code->SoLuong . ' lượt' : '') . '<br>';
        }

        // Khách nêu giá trị đơn => chọn mã có lợi nhất.
        if (preg_match('/(?:đơn|hóa đơn|hoá đơn|mua|thanh toán)\D{0,15}(\d+(?:[.,]\d+)?)\s*(triệu|tr|nghìn|ngàn|k)(?!\p{L})/iu', $lower, $matches)) {
            $orderValue = $this->chuyenGiaSangVnd($matches[1], $matches[2]);
            $best = $codes->sortByDesc(fn ($code) => $discountOf($code, $orderValue))->first();
            $saving = $discountOf($best, $orderValue);
            $reply .= $saving > 0
                ? '<br><b>Với đơn ' . $this->dinhDangTien($orderValue) . ', nên dùng mã ' . e($best->MaCode) . '</b> – giảm khoảng ' . $this->dinhDangTien($saving) . '.'
                : '<br>Đơn ' . $this->dinhDangTien($orderValue) . ' chưa đủ điều kiện mã nào, bạn cần mua thêm để đạt giá trị tối thiểu nhé.';
        } else {
            $reply .= '<br>Bạn cho mình biết giá trị đơn dự kiến, mình sẽ chọn mã có lợi nhất cho bạn nhé.';
        }

        return $reply;
    }

    // ==========================================
    // SO SÁNH SẢN PHẨM
    // Hai mẫu khách nêu tên, các mẫu vừa tư vấn, hoặc ngẫu nhiên hai mẫu cùng nhóm;
    // đặt cạnh nhau, phân tích theo nhu cầu của khách (nếu đã biết) rồi chốt mẫu nên mua.
    // ==========================================

    private const TU_DUNG_TEN_SAN_PHAM = [
        'vợt', 'vot', 'cầu', 'lông', 'so', 'sánh', 'với', 'và', 'hay', 'hoặc', 'vs', 'nên', 'mua', 'chọn', 'lấy',
        'nào', 'cái', 'cây', 'đôi', 'chiếc', 'mẫu', 'sản', 'phẩm', 'này', 'kia', 'cho', 'mình', 'tôi', 'em', 'bạn',
        'giúp', 'hãy', 'giữa', 'hai', 'tốt', 'hơn', 'đáng', 'chính', 'hãng', 'thì', 'là', 'của', 'có', 'không',
        'khác', 'nhau', 'gì', 'thế', 'giá', 'bao', 'nhiêu', 'thông', 'số', 'còn', 'hàng', 'chi', 'tiết', 'ra', 'sao',
        'như', 'xem', 'về', 'shop', 'bán', 'đâu',
    ];

    private function laYeuCauSoSanh(string $message): bool
    {
        return preg_match('/(so sánh|so với|đối chiếu|\bvs\b|chọn giữa)/iu', $message) === 1
            // "A và B, cây nào nên mua / tốt hơn?"
            || preg_match('/\s(và|với|hay|hoặc)\s.+(nào|cái nào)\s*(nên mua|nên chọn|nên lấy|tốt hơn|hợp hơn|đáng mua)/iu', $message) === 1
            // "A và B, tôi nên mua vợt nào?" / "...nên chọn mua cái nào?"
            || preg_match('/\s(và|với|hay|hoặc)\s.+(?:nên|thì)\s*(?:(?:mua|chọn|lấy)\s*){1,2}(?:cây|cái|mẫu|đôi|vợt|loại|chiếc)?\s*nào/iu', $message) === 1;
    }

    private function soSanhSanPham(string $message, string $lower, array $history): ?string
    {
        $context = $this->layNguCanhTuVan($history);
        $products = collect();
        $names = $this->tachHaiTenSanPham($message);

        if ($names !== null) {
            $first = $this->timSanPhamTheoTen($names[0]);
            $second = $this->timSanPhamTheoTen($names[1]);
            if ($first && $second && (int) $first->ID_SanPham !== (int) $second->ID_SanPham) {
                $products = collect([$first, $second]);
            } elseif (count($this->layTuKhoaTenSanPham($names[0])) >= 2 && count($this->layTuKhoaTenSanPham($names[1])) >= 2) {
                $missing = !$first ? $names[0] : $names[1];
                return 'Dạ, mình chưa tìm thấy "' . e(trim($missing)) . '" trong các sản phẩm đang bán. Bạn kiểm tra lại tên mẫu giúp mình nhé.';
            }
        }
        if ($products->isEmpty() && preg_match('/(ngẫu nhiên|bất kỳ|bất kì|random)/iu', $lower)) {
            $products = $this->chonNgauNhienHaiSanPham($lower);
        }
        if ($products->isEmpty() && $context !== null) {
            // "So sánh hai mẫu vừa tư vấn": lấy các mẫu đang bàn, thiếu thì lấy thêm mẫu đã xem trước đó.
            $ids = array_values(array_unique([...$context['shown'], ...array_reverse($context['seen'])]));
            if (count($ids) >= 2) {
                $products = $this->truyVanSanPhamDangBan()->whereIn('sanpham.ID_SanPham', array_slice($ids, 0, 3))->get()
                    ->unique('ID_SanPham')->values();
            }
        }
        if ($products->count() < 2) {
            return null;
        }

        // Khách tự nêu tên hai mẫu muốn mua => so sánh và chốt theo giá, không xếp theo trình độ.
        return $this->trinhBaySoSanh($products, $context, $lower, $names !== null);
    }

    private function tachHaiTenSanPham(string $message): ?array
    {
        $text = preg_replace('/^\s*(?:hãy\s*)?(?:so sánh|đối chiếu)(?:\s*giúp\s*(?:mình|tôi|em))?\s*(?:giữa\s*)?/iu', '', $message);
        $text = preg_replace('/[,;]?\s*(?:(?:cây|đôi|chiếc|cái|mẫu)\s*)?(?:vợt|giày|áo|quần|váy|sản phẩm)?\s*(?:nào|cái nào)\s*(?:nên\s*(?:mua|chọn|lấy)|phù\s*hợp|tốt|hợp|đáng\s*mua)(?:\s*hơn)?.*$/iu', '', $text);
        $text = preg_replace('/[,;]?\s*(?:(?:tôi|mình|em|anh|chị)\s*)?(?:nên|thì)\s*(?:(?:mua|chọn|lấy)\s*){1,2}(?:cây|cái|mẫu|đôi|vợt|loại|chiếc)?\s*nào.*$/iu', '', $text);
        $text = preg_replace('/\s*(?:khác nhau|khác gì).*$/iu', '', $text);
        $text = trim(preg_replace('/[?.!]+\s*$/u', '', $text));
        $names = preg_split('/\s+(?:và|với|hay|hoặc|vs\.?)\s+/iu', $text, 2);
        if (count($names) === 1
            && preg_match_all('/\b(yonex|victor|lining|mizuno|kumpoo|kawasaki|apacs|vnb)\b/iu', $text, $brands, PREG_OFFSET_CAPTURE) >= 2) {
            $names = [trim(substr($text, 0, $brands[0][1][1])), trim(substr($text, $brands[0][1][1]))];
        }

        return count($names) === 2 && $this->layTuKhoaTenSanPham($names[0]) && $this->layTuKhoaTenSanPham($names[1]) ? $names : null;
    }

    private function layTuKhoaTenSanPham(string $name): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $tokens,
            fn ($token) => (mb_strlen($token) >= 2 || ctype_digit($token)) && !in_array($token, self::TU_DUNG_TEN_SAN_PHAM, true)
        )));
    }

    // Tìm sản phẩm khớp tên khách gõ, ưu tiên sản phẩm khớp nhiều từ khóa nhất.
    private function timSanPhamTheoTen(string $name, bool $chiConHang = true): ?object
    {
        $terms = $this->layTuKhoaTenSanPham($name);
        if (!$terms) {
            return null;
        }

        return $this->truyVanSanPhamDangBan($chiConHang)
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('sanpham.TenSanPham', 'like', '%' . $term . '%');
                }
            })
            ->get()
            ->unique('ID_SanPham')
            ->map(function ($product) use ($terms) {
                $searchable = mb_strtolower($product->TenSanPham . ' ' . ($product->TenThuongHieu ?? ''));
                $product->matchScore = collect($terms)->filter(fn ($term) => str_contains($searchable, $term))->count();
                return $product;
            })
            ->filter(fn ($product) => $product->matchScore >= min(2, count($terms)))
            ->sort(fn ($left, $right) => [$right->matchScore, mb_strlen($left->TenSanPham)] <=> [$left->matchScore, mb_strlen($right->TenSanPham)])
            ->first();
    }

    private function chonNgauNhienHaiSanPham(string $lower): \Illuminate\Support\Collection
    {
        $query = $this->truyVanSanPhamDangBan();
        $category = match (true) {
            $this->laYeuCauPhuKien($lower) => 'Phụ Kiện',
            preg_match('/(giày|giay)/iu', $lower) === 1 => 'Giày',
            preg_match('/(quần|áo)/iu', $lower) === 1 => 'Quần Áo',
            preg_match('/(váy|đầm)/iu', $lower) === 1 => 'Váy',
            $this->laYeuCauVot($lower) => 'Vợt',
            default => DB::table('sanpham')->join('danhmuc', 'sanpham.ID_DanhMuc', '=', 'danhmuc.ID_DanhMuc')
                ->where('sanpham.TrangThai', 'DangBan')->where('sanpham.SoLuongTon', '>', 0)
                ->groupBy('danhmuc.TenDanhMuc')->havingRaw('COUNT(*) >= 2')->inRandomOrder()->value('danhmuc.TenDanhMuc'),
        };
        if ($category) {
            $query->where('danhmuc.TenDanhMuc', 'like', '%' . $category . '%');
        }

        return $query->get()->unique('ID_SanPham')->shuffle()->take(2)->values();
    }

    // Khách tự chọn hai mẫu và hỏi nên mua cây nào => so sánh giá hiện tại, giá gốc, mức giảm rồi chốt mẫu có giá tốt hơn.
    private function soSanhTheoGia(\Illuminate\Support\Collection $products, ?array $context): string
    {
        $sorted = $products->sortBy(fn ($product) => $this->giaHienTai($product))->values();
        $cheap = $sorted->first();
        $other = $sorted->last();
        $gap = $this->giaHienTai($other) - $this->giaHienTai($cheap);

        $lines = $sorted->map(function ($product) {
            $line = '• <b>' . e($product->TenSanPham) . '</b>: ' . $this->dinhDangTien($this->giaHienTai($product));
            if ($this->phanTramGiamGia($product) > 0) {
                $line .= ' (giá gốc ' . $this->dinhDangTien((float) $product->GiaBan) . ', đang giảm '
                    . $this->phanTramGiamGia($product) . '% – tiết kiệm '
                    . $this->dinhDangTien((float) $product->GiaBan - $this->giaHienTai($product)) . ')';
            } else {
                $line .= ' (chưa có khuyến mãi)';
            }
            return $line;
        })->implode('<br>');

        if ($gap <= 0) {
            $deeper = $products->sortByDesc(fn ($product) => $this->phanTramGiamGia($product))->first();
            $conclusion = '<b>Kết luận:</b> Hai mẫu đang cùng mức giá '
                . $this->dinhDangTien($this->giaHienTai($cheap)) . '. '
                . ($this->phanTramGiamGia($deeper) > 0
                    ? 'Nên mua <b>' . e($deeper->TenSanPham) . '</b> vì đang được giảm sâu hơn (' . $this->phanTramGiamGia($deeper) . '%).'
                    : 'Về giá thì như nhau, bạn có thể chọn theo thương hiệu hoặc cảm giác cầm thử.');
        } else {
            $conclusion = '<b>Kết luận: Xét về giá, nên mua ' . e($cheap->TenSanPham) . '.</b> '
                . 'Mẫu này rẻ hơn ' . e($other->TenSanPham) . ' ' . $this->dinhDangTien($gap)
                . ' (khoảng ' . round($gap / $this->giaHienTai($other) * 100) . '%).';
            if ($this->phanTramGiamGia($other) > $this->phanTramGiamGia($cheap)) {
                $conclusion .= ' ' . e($other->TenSanPham) . ' tuy đang giảm ' . $this->phanTramGiamGia($other)
                    . '% nhưng giá sau giảm vẫn cao hơn.';
            }
        }

        // Nếu trước đó khách đã nêu ngân sách thì cho biết mẫu nào nằm trong ngân sách.
        $budget = $this->layNganSach((string) ($context['request'] ?? ''));
        if (!empty($budget['max'])) {
            $within = $sorted->filter(fn ($product) => $this->giaHienTai($product) <= $budget['max']);
            $label = e(preg_replace('/^ngân sách\s*/u', '', $budget['label']));
            $conclusion .= match ($within->count()) {
                0 => ' Lưu ý: cả hai mẫu đều vượt ngân sách ' . $label . ' bạn đã nêu.',
                $sorted->count() => ' Cả hai đều nằm trong ngân sách ' . $label . ' của bạn.',
                default => ' Trong ngân sách ' . $label . ' bạn đã nêu, chỉ có ' . e($within->first()->TenSanPham) . ' là vừa túi tiền.',
            };
        }

        $specs = '';
        if ($sorted->every(fn ($product) => !empty($product->DoCung))) {
            [$specs, $tradeOff] = $this->soSanhThongSoVot($cheap, $other);
            $conclusion .= $tradeOff;
        }

        return '<b>So sánh giá</b><br>' . $lines . '<br><br>' . $specs . $conclusion;
    }

    // So sánh từng thông số của hai vợt và ý nghĩa với người chơi: [HTML so sánh, lưu ý cho phần kết luận].
    private function soSanhThongSoVot(object $cheap, object $other): array
    {
        $names = [e($cheap->TenSanPham), e($other->TenSanPham)];
        $clean = fn ($value) => e(preg_replace('/\s*\(.*\)$/u', '', (string) $value));
        $points = [];

        $levels = [$cheap->TrinhDoPhuHop, $other->TrinhDoPhuHop];
        if ($levels[0] !== $levels[1]) {
            $points[] = '<b>Trình độ:</b> ' . $names[0] . ' dành cho ' . $this->tenTrinhDo($levels[0])
                . ', còn ' . $names[1] . ' dành cho ' . $this->tenTrinhDo($levels[1]) . '.';
        }

        $weights = [$this->laySoU((string) $cheap->TrongLuong), $this->laySoU((string) $other->TrongLuong)];
        if ($weights[0] !== $weights[1] && $weights[0] && $weights[1]) {
            [$heavy, $light] = $weights[0] < $weights[1] ? [0, 1] : [1, 0];
            $points[] = '<b>Trọng lượng:</b> ' . $names[$heavy] . ' nặng hơn (' . e([$cheap, $other][$heavy]->TrongLuong) . ') nên đầm tay, cú đánh có lực; '
                . $names[$light] . ' nhẹ hơn (' . e([$cheap, $other][$light]->TrongLuong) . ') nên vung nhanh, ít mỏi tay.';
        }

        $balanceMeaning = ['nang' => 'thiên tấn công, hỗ trợ đập cầu', 'nhe' => 'xoay trở nhanh, hợp phòng thủ – phản tạt', 'deu' => 'cân bằng công thủ'];
        $balances = [$this->phanLoaiCanBang((string) $cheap->DiemCanBang), $this->phanLoaiCanBang((string) $other->DiemCanBang)];
        if ($balances[0] !== $balances[1]) {
            $points[] = '<b>Điểm cân bằng:</b> ' . $names[0] . ' ' . mb_strtolower($clean($cheap->DiemCanBang)) . ' (' . ($balanceMeaning[$balances[0]] ?? '—') . '); '
                . $names[1] . ' ' . mb_strtolower($clean($other->DiemCanBang)) . ' (' . ($balanceMeaning[$balances[1]] ?? '—') . ').';
        }

        $stiffnessMeaning = ['deo' => 'dễ trợ lực, đỡ đau tay', 'trung_binh' => 'dễ làm quen', 'cung' => 'đánh chính xác, cần lực vung tốt', 'rat_cung' => 'rất khó thuần, cần lực và kỹ thuật cao'];
        $stiffness = [$this->phanLoaiDoCung((string) $cheap->DoCung), $this->phanLoaiDoCung((string) $other->DoCung)];
        if ($stiffness[0] !== $stiffness[1]) {
            $points[] = '<b>Độ cứng:</b> ' . $names[0] . ' thân ' . mb_strtolower(e($cheap->DoCung)) . ' (' . ($stiffnessMeaning[$stiffness[0]] ?? '—') . '); '
                . $names[1] . ' thân ' . mb_strtolower(e($other->DoCung)) . ' (' . ($stiffnessMeaning[$stiffness[1]] ?? '—') . ').';
        }

        if ((string) $cheap->ChuViTayCam !== (string) $other->ChuViTayCam) {
            $points[] = '<b>Cán:</b> ' . $names[0] . ' cán ' . e($cheap->ChuViTayCam) . ', ' . $names[1] . ' cán ' . e($other->ChuViTayCam)
                . ' (G5 nhỏ hơn G4, hợp bàn tay nhỏ).';
        }
        if ((string) $cheap->MucCangToiDa !== (string) $other->MucCangToiDa) {
            $points[] = '<b>Mức căng tối đa:</b> ' . $names[0] . ' ' . e($cheap->MucCangToiDa) . ', ' . $names[1] . ' ' . e($other->MucCangToiDa)
                . ' (mức cao hơn cho phép căng cước chặt hơn, hợp người lực tay tốt).';
        }

        $html = '<b>So sánh thông số</b><br>'
            . ($points ? '• ' . implode('<br>• ', $points) : '• Hai mẫu có thông số gần như giống nhau.')
            . '<br><br>';

        // Mẫu rẻ hơn khác hẳn kiểu người chơi => nhắc khách đừng chỉ nhìn giá.
        $tradeOff = '';
        if ($levels[0] !== $levels[1] || $balances[0] !== $balances[1]) {
            $tradeOff = ' Tuy nhiên hai mẫu hợp hai kiểu người chơi khác nhau: ' . $this->moTaNguoiChoiPhuHop($cheap) . ' '
                . $this->moTaNguoiChoiPhuHop($other)
                . ' Nếu bạn thuộc kiểu người chơi của ' . $names[1] . ' thì nên cân nhắc mẫu này dù giá cao hơn. Bạn cho mình biết trình độ và lối chơi để mình chốt giúp nhé.';
        }

        return [$html, $tradeOff];
    }

    private function trinhBaySoSanh(\Illuminate\Support\Collection $products, ?array $context, string $lower, bool $theoGia = false): string
    {
        $areRackets = $products->every(fn ($product) => !empty($product->DoCung));
        $loai = $areRackets ? 'vot' : ($products->every(fn ($product) => str_contains(mb_strtolower((string) $product->TenDanhMuc), 'giày')) ? 'giay' : 'phu_kien');
        // Nhu cầu: lấy từ lần tư vấn trước (nếu cùng loại), cộng với điều khách vừa nói trong câu so sánh.
        $needs = trim((($context['loai'] ?? null) === $loai ? $context['request'] : '') . ' ' . $lower);

        $rows = ['Giá' => fn ($product) => $this->dinhDangTien($this->giaHienTai($product))
            . ($this->phanTramGiamGia($product) > 0 ? ' (giảm ' . $this->phanTramGiamGia($product) . '%)' : '')];
        if ($areRackets) {
            $rows += [
                'Trình độ' => fn ($product) => ucfirst($this->tenTrinhDo($product->TrinhDoPhuHop)),
                'Trọng lượng' => fn ($product) => e($product->TrongLuong),
                'Điểm cân bằng' => fn ($product) => e(preg_replace('/\s*\(.*\)$/u', '', (string) $product->DiemCanBang)),
                'Độ cứng' => fn ($product) => e($product->DoCung),
                'Cán' => fn ($product) => e($product->ChuViTayCam ?: '—'),
                'Mức căng tối đa' => fn ($product) => e($product->MucCangToiDa ?: '—'),
            ];
        } else {
            $rows += [
                'Thương hiệu' => fn ($product) => e($product->TenThuongHieu ?: '—'),
                'Size có sẵn' => fn ($product) => e($product->SizeCoSan ?: '—'),
            ];
        }
        $table = "<table class='table table-sm table-bordered mb-2' style='font-size:12px;'><tr><th></th>"
            . $products->map(fn ($product) => '<th>' . e($product->TenSanPham) . '</th>')->implode('') . '</tr>';
        foreach ($rows as $label => $value) {
            $table .= '<tr><td><b>' . $label . '</b></td>' . $products->map(fn ($product) => '<td>' . $value($product) . '</td>')->implode('') . '</tr>';
        }
        $table .= '</table>';

        $reply = 'Dạ, mình đặt các mẫu cạnh nhau để bạn dễ so sánh:<br><br>'
            . $products->map(fn ($product) => $this->taoTheSanPham($product))->implode('') . $table;

        if ($theoGia) {
            $reply .= $this->soSanhTheoGia($products, $context);
        } elseif ($areRackets) {
            $reply .= '<b>Mỗi mẫu hợp với ai</b><br>' . $products->map(fn ($product) => '• ' . $this->moTaNguoiChoiPhuHop($product))->implode('<br>');
            $hasNeeds = $this->layMaTrinhDo($needs) !== null || $this->layLoiChoi($needs) !== null
                || $this->layTheLuc($needs) !== null || $this->coGioiHanNganSach($this->layNganSach($needs));
            if ($hasNeeds) {
                $profile = $this->taoHoSoVot($needs);
                $budget = $this->layNganSach($needs);
                $score = fn ($product) => $this->diemTheoHoSoVot($product, $profile)
                    + (!empty($budget['max']) && $this->giaHienTai($product) > $budget['max'] ? -15 : 0);
                $winner = $products->sortByDesc($score)->first();
                [$fits] = $this->giaiThichVotPhuHop($winner, $profile, $needs);
                $loserNotes = $products->reject(fn ($product) => $product === $winner)
                    ->map(function ($product) use ($profile, $needs) {
                        [, $warnings] = $this->giaiThichVotPhuHop($product, $profile, $needs);
                        return $warnings ? e($product->TenSanPham) . ': ' . implode(', ', array_slice($warnings, 0, 2)) : null;
                    })->filter()->implode('; ');
                $reply .= '<br><br><b>Kết luận: Với nhu cầu của bạn (' . e($this->tomTatNhuCau('vot', $needs)) . '), nên mua ' . e($winner->TenSanPham) . '.</b>'
                    . ($fits ? ' Lý do: ' . implode(', ', array_slice($fits, 0, 3)) . '.' : '')
                    . ($loserNotes !== '' ? ' Mẫu còn lại chưa hợp bằng vì ' . $loserNotes . '.' : '');
            } else {
                $cheapest = $products->sortBy(fn ($product) => $this->giaHienTai($product))->first();
                $reply .= '<br><br><b>Kết luận:</b> Mỗi mẫu hợp một kiểu người chơi như trên. Nếu cần chốt ngay mà chưa có thêm tiêu chí, '
                    . '<b>' . e($cheapest->TenSanPham) . '</b> có giá tốt hơn. Bạn cho mình biết trình độ, lối chơi và ngân sách để mình chốt chính xác cây hợp với bạn nhé.';
            }
        } else {
            $type = $loai === 'giay' ? 'giay' : $this->layLoaiPhuKien(mb_strtolower((string) $products->first()->TenSanPham));
            $allNeeds = array_values(array_filter(self::NHU_CAU_THEO_MO_TA, fn ($need) => in_array($type, $need['for'], true)));
            $wanted = $this->layNhuCauTheoMoTa($type, $needs);
            $reply .= '<b>Điểm nổi bật (theo mô tả của cửa hàng)</b><br>' . $products->map(function ($product) use ($allNeeds) {
                $highlights = $this->nhuCauCoTrongMoTa($product, $allNeeds);
                return '• ' . e($product->TenSanPham) . ': ' . ($highlights ? implode(', ', $highlights) : 'mô tả chưa nêu điểm nổi bật cụ thể');
            })->implode('<br>');
            $winner = $products->sort(function ($left, $right) use ($wanted) {
                $comparison = count($this->nhuCauCoTrongMoTa($right, $wanted)) <=> count($this->nhuCauCoTrongMoTa($left, $wanted));
                return $comparison !== 0 ? $comparison : $this->giaHienTai($left) <=> $this->giaHienTai($right);
            })->first();
            $matched = $this->nhuCauCoTrongMoTa($winner, $wanted);
            $reply .= '<br><br><b>Kết luận: Nên mua ' . e($winner->TenSanPham) . '.</b> '
                . ($matched ? 'Mẫu này đáp ứng đúng nhu cầu ' . implode(', ', $matched) . ' của bạn.' : 'Mẫu này có giá tốt hơn trong khi công dụng tương đương.');
            if ($products->pluck('TenDanhMuc')->unique()->count() > 1) {
                $reply .= ' Lưu ý: các sản phẩm thuộc nhóm khác nhau, bạn nên ưu tiên món đang thật sự cần dùng.';
            }
        }

        $reply .= '<br><br>Bạn muốn mình tư vấn kỹ hơn theo nhu cầu của bạn, hay xem thêm mẫu khác ạ?';
        $request = $context !== null && $context['loai'] === $loai ? $context['request'] : ($loai === 'vot' ? 'vợt ' . $lower : $lower);

        return $reply . $this->danhDauKetQuaTuVan($loai, $request, $products, ['seen' => $context['seen'] ?? []]);
    }

    private function moTaNguoiChoiPhuHop(object $racket): string
    {
        $level = $this->tenTrinhDo($racket->TrinhDoPhuHop) ?: 'nhiều trình độ';
        $style = match ($this->phanLoaiCanBang((string) $racket->DiemCanBang)) {
            'nang' => 'thích tấn công, đập cầu',
            'nhe' => 'thiên phòng thủ, phản tạt nhanh hoặc đánh đôi',
            'deu' => 'chơi công thủ toàn diện',
            default => 'cần một cây dễ làm quen',
        };
        $stiffness = mb_strtolower((string) $racket->DoCung);
        $power = str_contains($stiffness, 'cứng') ? ', lực vung tốt' : ($stiffness !== '' ? ', muốn vợt dễ trợ lực, đỡ mỏi tay' : '');

        return '<b>' . e($racket->TenSanPham) . '</b> hợp ' . $level . ', ' . $style . $power . '.';
    }

    // Thẻ hiển thị một sản phẩm: ảnh, giá gốc/giá sale, thông số vợt (nếu có), nút xem chi tiết.
    private function taoTheSanPham(object $product): string
    {
        if ($this->phanTramGiamGia($product) > 0) {
            $priceHtml = "<span style='text-decoration: line-through; color: #999; font-size: 12px; margin-right: 6px;'>" . $this->dinhDangTien((float) $product->GiaBan) . '</span>'
                . "<span style='color: #dc3545; font-weight: bold; font-size: 15px;'>" . $this->dinhDangTien($this->giaHienTai($product)) . '</span>';
        } else {
            $priceHtml = "<span style='color: #dc3545; font-weight: bold; font-size: 15px;'>" . $this->dinhDangTien((float) $product->GiaBan) . '</span>';
        }

        $specs = '';
        if (!empty($product->TrongLuong)) {
            $specs = "<br><span style='font-size:12px; color:#555;'><b>Thông số:</b> " . e($product->TrongLuong) . ' | ' . e($product->DiemCanBang)
                . ' | Độ cứng: ' . e($product->DoCung) . ' | ' . e(ucfirst($this->tenTrinhDo($product->TrinhDoPhuHop ?? null))) . '</span>';
        } elseif (preg_match('/(quần áo|váy)/iu', (string) ($product->TenDanhMuc ?? ''))) {
            $specs = "<br><span style='font-size:12px; color:#555;'><b>Size có sẵn:</b> " . e($product->SizeCoSan ?: 'chưa cập nhật') . '</span>';
        }

        $image = !empty($product->HinhAnh) ? $product->HinhAnh : ($product->HinhAnhPhu ?? null);
        $imageUrl = empty($image) ? '/images/no-image.jpg' : (preg_match('/^http/i', $image) ? $image : '/images/sanpham/' . ltrim($image, '/'));

        return "<div class='d-flex align-items-center mb-3 p-2 border rounded bg-white shadow-sm'>"
            . "<img src='" . e($imageUrl) . "' alt='" . e($product->TenSanPham) . "' style='width: 70px; height: 70px; object-fit: cover; border-radius: 6px; margin-right: 12px; border: 1px solid #eee;'>"
            . "<div style='flex:1;'>"
            . "<b style='font-size:14px;'>" . e($product->TenSanPham) . '</b><br>'
            . $priceHtml . $specs
            . "<br><a href='/san-pham/" . (int) $product->ID_SanPham . "' target='_blank' class='btn btn-sm btn-outline-primary mt-2' style='font-size:11px; padding: 3px 10px;'>Xem chi tiết</a>"
            . '</div></div>';
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
        } elseif (preg_match('/chuyên nghiệp/iu', $message)) {
            $query->where('thongsovot.TrinhDoPhuHop', 'ChuyenNghiep');
        } elseif (preg_match('/nâng cao/iu', $message)) {
            $query->where('thongsovot.TrinhDoPhuHop', 'NangCao');
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

        $dataTho = $sanPhams->map(fn ($sp) => $this->taoTheSanPham($sp))->implode('');

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

    // Tìm câu trả lời trong bảng câu hỏi thường gặp. Từ khóa ngăn bằng ";" hoặc ",".
    // Chọn câu khớp nhiều từ khóa nhất (tính theo tổng độ dài từ khóa khớp) thay vì câu đầu tiên.
    private function timKiemFAQ(string $message)
    {
        $message = mb_strtolower($message);
        $best = null;
        $bestScore = 0;
        foreach (DB::table('cauhoichatbot')->where('TrangThai', 'HoatDong')->get() as $faq) {
            $score = 0;
            foreach (preg_split('/[;,]/u', mb_strtolower((string) $faq->TuKhoa)) as $keyword) {
                $keyword = trim($keyword);
                // Từ khóa quá chung (vd "hỗ trợ") dễ khớp nhầm => bỏ qua từ khóa dưới 3 ký tự hoặc chỉ 1 từ phổ biến.
                if (mb_strlen($keyword) < 2 || in_array($keyword, [
                    'hỗ trợ', 'chatbot', 'mua hàng', 'đơn hàng', 'trạng thái đơn', 'thanh toán', 'giỏ hàng', 'người mới',
                    'phụ kiện', 'giày', 'size', 'danh mục', 'chọn vợt', 'giao dịch', 'số điện thoại', 'địa chỉ',
                ], true)) {
                    continue;
                }
                if (preg_match('/(?<!\p{L})' . preg_quote($keyword, '/') . '(?!\p{L})/u', $message)) {
                    $score += mb_strlen($keyword);
                }
            }
            if ($score > $bestScore) {
                $best = $faq;
                $bestScore = $score;
            }
        }

        return $best?->CauTraLoi;
    }

    // ==========================================
    // CÂU TRẢ LỜI CÓ SẴN: chào hỏi, chính sách cửa hàng, kiến thức cầu lông (không phụ thuộc Gemini)
    // Thông tin cửa hàng / chính sách sửa trực tiếp trong các hằng số bên dưới.
    // ==========================================
    private const HOTLINE = '035 214 3569';
    private const DIA_CHI = 'Tổ 4, Đền Rồng, Phường Từ Sơn, Thành Phố Bắc Ninh';
    private const EMAIL = 'Cohoi2512@gmail.com';
    private const GIO_HO_TRO = '08:00 - 22:00 hằng ngày';
    private const THOI_GIAN_GIAO = 'Nội thành Bắc Ninh, Hà Nội khoảng 1–2 ngày; các tỉnh khác khoảng 3–5 ngày làm việc';

    private function goiYKhaNang(): string
    {
        return 'Mình có thể giúp bạn:<br>'
            . '• <b>Tư vấn chọn vợt</b> theo trình độ, lối chơi, ngân sách<br>'
            . '• Tư vấn <b>giày, quần áo, váy</b> (kể cả chọn size) và <b>phụ kiện</b> (cước, quấn cán, túi, ống cầu)<br>'
            . '• <b>So sánh</b> hai sản phẩm, tìm hàng <b>đang giảm giá</b>, <b>mã giảm giá</b><br>'
            . '• Giải đáp <b>chính sách</b> đổi trả, bảo hành, giao hàng, thanh toán và <b>tra cứu đơn hàng</b>';
    }

    // Khi không hiểu câu hỏi (và AI không trả lời được): hướng dẫn khách những gì chatbot làm được.
    private function traLoiKhiKhongHieu(): string
    {
        return 'Dạ, câu này mình chưa trả lời chính xác được ạ. ' . $this->goiYKhaNang()
            . '<br>Bạn thử hỏi lại theo một trong các nội dung trên, hoặc gọi hotline <b>' . self::HOTLINE . '</b> để được hỗ trợ trực tiếp nhé.';
    }

    private function traLoiCauHoiCoSan(string $lower): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $lower));

        // --- Chào hỏi, cảm ơn, tạm biệt (chỉ khi tin nhắn ngắn, chủ yếu là lời chào) ---
        if (mb_strlen($text) <= 40) {
            if (preg_match('/^(xin chào|chào|hello|helo|hi|hey|alo|shop ơi|ad ơi|bạn ơi|có ai không)(?!\p{L})[\s\p{L}!.,?]{0,20}$/u', $text)
                && !preg_match('/(vợt|giày|áo|quần|váy|cước|phụ kiện|đơn|giá|mua)/u', $text)) {
                return 'Dạ, chào bạn! Mình là trợ lý tư vấn của <b>Badminton ProShop</b> 🏸<br>' . $this->goiYKhaNang()
                    . '<br>Bạn đang cần tìm sản phẩm gì ạ?';
            }
            if (preg_match('/(cảm ơn|cám ơn|thank|thanks|tks|cảm ơn shop|ok shop)/u', $text)) {
                return 'Dạ, không có gì ạ! Cảm ơn bạn đã ghé Badminton ProShop. Khi cần tư vấn thêm, bạn cứ nhắn mình nhé 😊';
            }
            if (preg_match('/(tạm biệt|bye|bai bai|hẹn gặp lại)/u', $text)) {
                return 'Dạ, chào bạn, hẹn gặp lại! Chúc bạn có những trận cầu thật vui 🏸';
            }
            if (preg_match('/^(bạn là ai|bạn làm được gì|bạn giúp được gì|bot là gì|bạn có thể làm gì)/u', $text)) {
                return 'Dạ, mình là trợ lý tư vấn tự động của Badminton ProShop. ' . $this->goiYKhaNang();
            }
        }

        // --- Chính sách cửa hàng ---
        if (preg_match('/(đổi trả|đổi hàng|trả hàng|trả lại|đổi lại|hoàn trả|hoàn hàng|đổi size|đổi sang size|đổi sản phẩm|hoàn tiền)/u', $text)
            && !preg_match('/(hủy|huỷ)/u', $text)) {
            return '<b>Chính sách trả hàng của Badminton ProShop:</b><br>'
                . '• Bạn được yêu cầu trả hàng trong <b>' . OrderService::SO_NGAY_DUOC_TRA_HANG . ' ngày</b> kể từ khi nhận hàng thành công.<br>'
                . '• Áp dụng khi sản phẩm bị lỗi/hư hỏng, giao sai mẫu – size – màu, không giống mô tả hoặc thiếu phụ kiện. Sản phẩm cần còn nguyên tem, hộp và phụ kiện đi kèm.<br>'
                . '• Cách gửi yêu cầu: vào <b>Đơn mua</b> → chọn đơn đã giao → bấm <b>"Trả hàng"</b>, chọn lý do. Shop sẽ duyệt và gửi email hướng dẫn gửi hàng về.<br>'
                . '• Shop <b>hoàn tiền</b> sau khi nhận và kiểm tra hàng trả.<br>'
                . 'Đơn chưa được duyệt thì bạn có thể tự <b>hủy đơn</b> ngay trong mục Đơn mua. Cần hỗ trợ thêm, bạn gọi hotline ' . self::HOTLINE . ' nhé.';
        }
        if (preg_match('/(bảo hành)/u', $text)) {
            return '<b>Chính sách bảo hành:</b><br>'
                . '• Sản phẩm tại shop là <b>hàng chính hãng</b>, được bảo hành theo chính sách của từng hãng (Yonex, Victor, Lining, Mizuno). Thời gian cụ thể tùy mẫu, ghi trên tem/phiếu bảo hành đi kèm.<br>'
                . '• Bảo hành các lỗi do nhà sản xuất. Không bảo hành khi vợt gãy, nứt do va đập, đánh vào cột/lưới, căng cước quá mức căng tối đa khuyến nghị, hoặc hư hỏng do sử dụng sai cách.<br>'
                . '• Khi cần bảo hành, bạn mang sản phẩm kèm phiếu bảo hành tới cửa hàng hoặc liên hệ hotline ' . self::HOTLINE . ' để được hướng dẫn.';
        }
        if (preg_match('/(phí ship|phí vận chuyển|phí giao|tiền ship|ship bao nhiêu|freeship|free ship|miễn phí vận chuyển|miễn phí ship|miễn phí giao)/u', $text)) {
            return '<b>Phí vận chuyển:</b><br>'
                . '• Giá sản phẩm trên web <b>chưa gồm phí vận chuyển</b>. Phí ship tùy khu vực và được shop báo khi xác nhận đơn.<br>'
                . '• Bạn có thể dùng mã <b>FREESHIPEXTRA</b> (giảm 30k cho đơn từ 150k) khi thanh toán. Hỏi mình "có mã giảm giá nào không" để xem các mã đang áp dụng nhé.';
        }
        // --- Đơn hàng: hủy, sửa thông tin, cách đặt, theo dõi (không có mã đơn => không phải tra cứu) ---
        $hasNumber = preg_match('/\d{2,}/', $text) === 1;
        if (preg_match('/(hủy đơn|huỷ đơn|hủy đặt|huỷ đặt|hủy mua|huỷ mua|không muốn mua nữa|không mua nữa|hủy hàng|huỷ hàng|bị hủy|bị huỷ|đã hủy|đã huỷ|hủy rồi|huỷ rồi)/u', $text) && !$hasNumber) {
            return '<b>Cách hủy đơn hàng:</b><br>'
                . '• Đơn đang <b>Chờ xác nhận</b>: bạn tự hủy được. Vào mục <b>Đơn mua</b> (góc trên, cạnh tên tài khoản) → chọn đơn → bấm <b>"Hủy đơn"</b> → chọn lý do và xác nhận. Lượt mã giảm giá đã dùng sẽ được trả lại.<br>'
                . '• Đơn đã thanh toán <b>VNPay</b>: shop cần hoàn tiền nên bạn liên hệ hotline <b>' . self::HOTLINE . '</b> để được hủy giúp.<br>'
                . '• Đơn đã <b>Đang giao</b>: không tự hủy được nữa, bạn gọi hotline để shop hỗ trợ. Nếu đã nhận hàng, bạn có thể yêu cầu <b>trả hàng</b> trong ' . OrderService::SO_NGAY_DUOC_TRA_HANG . ' ngày.';
        }
        if (preg_match('/(đổi địa chỉ|sửa địa chỉ|thay đổi địa chỉ|đổi số điện thoại|sửa số điện thoại|thay đổi thông tin|sửa thông tin (?:đơn|nhận hàng|giao hàng)|sửa đơn|đổi người nhận|đặt nhầm|đặt sai)/u', $text)) {
            return '<b>Muốn sửa thông tin đơn (địa chỉ, số điện thoại, sản phẩm):</b><br>'
                . '• Nếu đơn còn <b>Chờ xác nhận</b>: cách nhanh nhất là <b>hủy đơn</b> trong mục Đơn mua (chọn lý do "Muốn thay đổi địa chỉ / số điện thoại") rồi đặt lại đơn mới.<br>'
                . '• Nếu đơn đã được duyệt hoặc đã thanh toán VNPay: bạn gọi hotline <b>' . self::HOTLINE . '</b> để shop cập nhật giúp trước khi giao.';
        }
        if (preg_match('/(cách đặt hàng|đặt hàng (?:như thế nào|thế nào|ra sao|kiểu gì|ở đâu)|làm sao (?:để )?(?:đặt|mua) hàng|mua hàng (?:như thế nào|thế nào|ra sao)|hướng dẫn (?:đặt|mua) hàng|đặt mua (?:như thế nào|thế nào)|có cần đăng nhập)/u', $text)) {
            return '<b>Cách đặt hàng:</b><br>'
                . '1. <b>Đăng nhập</b> tài khoản (chưa có thì bấm Đăng ký).<br>'
                . '2. Chọn sản phẩm → bấm <b>"Thêm vào giỏ hàng"</b>.<br>'
                . '3. Mở <b>Giỏ hàng</b>, kiểm tra số lượng → bấm <b>Thanh toán</b>.<br>'
                . '4. Nhập thông tin nhận hàng, mã giảm giá (nếu có), chọn <b>COD</b> hoặc <b>VNPay</b> → <b>Đặt hàng</b>.<br>'
                . 'Đơn sẽ ở trạng thái <b>Chờ xác nhận</b>, shop duyệt xong sẽ chuyển sang <b>Đang giao</b> và gửi email báo bạn.';
        }
        if (!$hasNumber && preg_match('/(theo dõi đơn|xem đơn (?:hàng )?(?:ở đâu|thế nào|như thế nào)|kiểm tra (?:tình trạng |trạng thái )?đơn (?:hàng )?(?:ở đâu|thế nào|như thế nào)|đơn (?:hàng )?(?:của tôi )?(?:đang ở đâu|tới đâu rồi|đến đâu rồi)|xem trạng thái đơn)/u', $text)) {
            return '<b>Theo dõi đơn hàng:</b> sau khi đăng nhập, bạn bấm mục <b>Đơn mua</b> để xem tất cả đơn theo từng trạng thái: Chờ xác nhận → Đang giao → Hoàn thành (hoặc Đã hủy), kèm tiến trình chi tiết của từng đơn.<br>'
                . 'Bạn cũng có thể nhắn mình <b>mã đơn</b> (ví dụ "tra cứu đơn 53") để mình kiểm tra nhanh.';
        }

        $askWhen = preg_match('/(bao giờ|khi nào|bao lâu|mấy ngày|mấy hôm|lúc nào|thời gian|trong khoảng)/u', $text) === 1;
        $aboutDelivery = preg_match('/(giao|nhận được|nhận hàng|có hàng|hàng (?:tới|đến|về)|ship tới|ship đến|vận chuyển)/u', $text) === 1;
        if (($askWhen && $aboutDelivery && !$hasNumber && !preg_match('/(bảo hành|trả hàng|đổi trả|hoàn tiền)/u', $text))
            || preg_match('/(giao (?:hàng )?(?:mất|trong|bao lâu|mấy ngày|khi nào|có nhanh)|bao lâu (?:thì )?(?:nhận|có hàng|giao|tới|đến)|mấy ngày (?:thì )?(?:nhận|có hàng|giao|tới|đến)|khi nào (?:thì )?nhận được|thời gian giao|toàn quốc|giao (?:hàng )?(?:tỉnh|ra tỉnh|đi tỉnh|tận nơi|tận nhà|xa)|(?:có )?ship (?:không|tỉnh|tận nơi|tận nhà|đi tỉnh|toàn quốc)|có giao (?:hàng )?(?:không|tận nơi|tận nhà))/u', $text)) {
            return '<b>Giao hàng:</b> shop giao hàng <b>toàn quốc, tận nơi</b>. Thời gian: ' . self::THOI_GIAN_GIAO . ', tính từ khi shop xác nhận đơn.<br>'
                . 'Bạn theo dõi trạng thái đơn (Chờ xác nhận → Đang giao → Hoàn thành) trong mục <b>Đơn mua</b>; khi shop duyệt đơn bạn sẽ nhận được email thông báo.';
        }
        if (preg_match('/(phương thức thanh toán|hình thức thanh toán|cách thanh toán|thanh toán (?:bằng|qua|như thế nào|thế nào|kiểu gì|ra sao|được không)|trả tiền (?:bằng|thế nào|như thế nào|khi nào)|có (?:nhận )?(?:cod|vnpay|chuyển khoản)|thanh toán khi nhận)/u', $text)) {
            return '<b>Shop hỗ trợ 2 hình thức thanh toán:</b><br>'
                . '• <b>COD</b> – thanh toán bằng tiền mặt khi nhận hàng.<br>'
                . '• <b>VNPay</b> – thanh toán online qua thẻ ATM/ngân hàng, ví điện tử, QR.<br>'
                . 'Bạn chọn hình thức thanh toán ở bước đặt hàng. Đơn VNPay được xử lý sau khi thanh toán thành công.';
        }
        if (preg_match('/(shop ở đâu|cửa hàng ở đâu|địa chỉ (?:shop|cửa hàng|của shop)|shop nằm ở|mua trực tiếp|đến cửa hàng|chỉ đường|hotline|số điện thoại (?:shop|cửa hàng|của shop)|sđt shop|liên hệ (?:shop|cửa hàng|với shop)|email (?:shop|cửa hàng)|giờ mở cửa|mở cửa (?:lúc|mấy giờ|đến)|làm việc mấy giờ)/u', $text)) {
            return '<b>Thông tin cửa hàng Badminton ProShop:</b><br>'
                . '• Địa chỉ: ' . self::DIA_CHI . '<br>'
                . '• Hotline: <b>' . self::HOTLINE . '</b><br>'
                . '• Email: ' . self::EMAIL . '<br>'
                . '• Thời gian hỗ trợ: ' . self::GIO_HO_TRO . '<br>'
                . 'Xem bản đồ chỉ đường tại trang <a href="/lien-he">Liên hệ</a> nhé.';
        }
        if (preg_match('/(chính hãng|hàng thật|hàng giả|hàng fake|hàng auth|hàng nhái)/u', $text) && !preg_match('/(tư vấn|nên mua|chọn)/u', $text)) {
            return 'Dạ, toàn bộ sản phẩm tại Badminton ProShop là <b>hàng chính hãng</b> từ Yonex, Victor, Lining, Mizuno, có tem và được bảo hành theo chính sách của hãng. Bạn yên tâm mua sắm nhé!';
        }

        // --- Kiến thức cầu lông ---
        if (preg_match('/(căng (?:vợt|cước|dây|lưới)|bao nhiêu (?:kg|lbs|pound|cân)|mức căng|độ căng|nên căng)/u', $text)
            && !preg_match('/(mức căng tối đa)/u', $text)) {
            return '<b>Mức căng cước nên dùng</b> (1 kg ≈ 2,2 lbs):<br>'
                . '• Người mới chơi / lực tay yếu: <b>20–24 lbs</b> (khoảng 9–11 kg) – cầu đi nhẹ, đỡ đau tay.<br>'
                . '• Trình độ nâng cao: <b>22–26 lbs</b> (khoảng 10–12 kg).<br>'
                . '• Chuyên nghiệp, lực tay khỏe: <b>24–28 lbs</b> (khoảng 11–13 kg) – cầu chính xác, cảm giác đanh.<br>'
                . 'Căng càng cao thì cầu càng chính xác nhưng càng cần lực tay tốt. Luôn căng <b>không vượt quá mức căng tối đa</b> ghi trên thân vợt để tránh gãy khung.';
        }
        $isKnowledge = preg_match('/(là gì|là sao|là như nào|khác nhau|khác gì|khác biệt|nghĩa là|thế nào là|ý nghĩa|hiểu thế nào|có tác dụng gì|dùng để làm gì|nên chọn loại nào giữa)/u', $text) === 1;
        if ($isKnowledge && preg_match('/(điểm cân bằng|balance)/u', $text) && !preg_match('/(đầu nặng|đầu nhẹ)/u', $text)) {
            return '<b>Điểm cân bằng</b> là vị trí trọng tâm của cây vợt, cho biết trọng lượng dồn về đầu vợt hay về cán:<br>'
                . '• <b>Đầu nặng</b> (head heavy): trọng lượng dồn lên đầu, đập cầu uy lực – hợp lối chơi tấn công.<br>'
                . '• <b>Cân bằng</b> (even balance): cân đối giữa công và thủ – hợp lối chơi toàn diện, dễ làm quen.<br>'
                . '• <b>Đầu nhẹ</b> (head light): vợt xoay trở nhanh, phản tạt, đỡ cầu tốt – hợp lối chơi phòng thủ, đánh đôi.<br>'
                . 'Bạn cho mình biết trình độ và lối chơi, mình sẽ tư vấn cây vợt có điểm cân bằng phù hợp nhé.';
        }
        if ($isKnowledge) {
            $faq = $this->timKiemFAQ($text);
            if ($faq !== null) {
                return $faq;
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
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . env('GEMINI_MODEL', 'gemini-1.5-flash') . ':generateContent?key=' . env('GEMINI_API_KEY');

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
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . env('GEMINI_MODEL', 'gemini-1.5-flash') . ':generateContent?key=' . env('GEMINI_API_KEY');

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

            return $this->traLoiKhiKhongHieu();
        } catch (Exception $e) {
            return $this->traLoiKhiKhongHieu();
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