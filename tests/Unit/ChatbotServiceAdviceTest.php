<?php

namespace Tests\Unit;

use App\Services\ChatbotService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ChatbotServiceAdviceTest extends TestCase
{
    private ChatbotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = (new ReflectionClass(ChatbotService::class))->newInstanceWithoutConstructor();
    }

    // Gọi một hàm private của ChatbotService để kiểm thử.
    private function call(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod(ChatbotService::class, $method))->invoke($this->service, ...$args);
    }

    // Gửi lần lượt các tin nhắn, giữ lịch sử như controller.
    private function chat(array $messages): array
    {
        $history = [];
        $replies = [];
        foreach ($messages as $message) {
            $reply = $this->service->xuLyTinNhan($message, 'pro', null, $history);
            $history[] = ['sender' => 'KhachHang', 'content' => $message];
            $history[] = ['sender' => 'Chatbot', 'content' => $reply];
            $replies[] = $reply;
        }

        return $replies;
    }

    public function test_a_vague_racket_request_starts_clarifying_questions(): void
    {
        [$first, $second] = $this->chat([
            'Tôi muốn mua vợt cầu lông, tư vấn giúp tôi.',
            'Tôi là người mới chơi và có lối chơi tấn công',
        ]);

        $this->assertStringStartsWith('Dạ, để tư vấn vợt sát nhu cầu hơn', $first);
        $this->assertStringContainsString('<!--tuvan:vot:tat_ca-->', $first);
        $this->assertStringContainsString('người mới chơi, thích tấn công', $second);
        $this->assertStringContainsString('<!--tuvan:vot:ngan_sach-->', $second);
    }

    public function test_need_statements_for_shoes_and_accessories_start_clarification(): void
    {
        [$shoes] = $this->chat(['Tư vấn giày cầu lông cho tôi']);
        $this->assertStringContainsString('<!--tuvan:giay:giay_size-->', $shoes);

        [$string] = $this->chat(['Tôi cần mua cước đan vợt']);
        $this->assertStringContainsString('<!--tuvan:phu_kien:pk_nhu_cau-->', $string);
        $this->assertStringContainsString('cước bền', $string);

        [$kit] = $this->chat(['Mua vợt rồi thì cần thêm phụ kiện gì?']);
        $this->assertStringContainsString('<!--tuvan:phu_kien:pk_nhu_cau-->', $kit);
    }

    public function test_a_new_need_mid_interview_restarts_instead_of_mixing_criteria(): void
    {
        $replies = $this->chat([
            'Tư vấn vợt cho tôi',
            'Tôi là người mới chơi',
            'Tôi là người chơi chuyên nghiệp giúp tôi tìm vợt phù hợp',
        ]);

        $this->assertStringContainsString('trình độ chuyên nghiệp', $replies[2]);
        $this->assertStringNotContainsString('người mới chơi', $replies[2]);
    }

    public function test_it_reads_customer_needs_and_later_answers_override_earlier_ones(): void
    {
        $this->assertSame('MoiChoi', $this->call('layMaTrinhDo', 'Tôi mới chơi được 3 tháng'));
        $this->assertNull($this->call('layMaTrinhDo', 'Tôi chơi được 2 năm rồi'));
        $this->assertNull($this->call('layMaTrinhDo', 'Tôi ở trình độ trung cấp'));
        $this->assertSame('NangCao', $this->call('layMaTrinhDo', 'Tôi có trình độ nâng cao'));
        $this->assertSame('ChuyenNghiep', $this->call('layMaTrinhDo', 'mới chơi ... à không, tôi chơi chuyên nghiệp'));
        $this->assertSame('phong_thu', $this->call('layLoiChoi', 'thích phòng thủ'));
        $this->assertSame('dau_tay', $this->call('layTheLuc', 'tôi hay bị đau khuỷu tay'));
        $this->assertSame('doi', $this->call('layCachChoi', 'chủ yếu đánh đôi, 3 buổi một tuần')['format']);
        $this->assertSame(3, $this->call('layCachChoi', 'chủ yếu đánh đôi, 3 buổi một tuần')['frequency']);
        $this->assertTrue($this->call('muonVotNhe', 'có cây vợt nào nhẹ, dễ điều khiển'));
        $this->assertFalse($this->call('muonVotNhe', 'vợt đầu nhẹ'));

        $budget = $this->call('layNganSach', 'ngân sách khoảng 1 triệu đồng');
        $this->assertEqualsWithDelta(1150000, $budget['max'], 1);
        $range = $this->call('layNganSach', 'dưới 4 triệu, à thôi tầm 1-2 triệu');
        $this->assertSame([1000000.0, 2000000.0], [$range['min'], $range['max']]);
        $this->assertSame('42', $this->call('laySizeGiay', 'size 42, chân bè'));
    }

    public function test_racket_profile_explains_what_to_choose_for_the_customer(): void
    {
        $profile = $this->call('taoHoSoVot', 'người mới chơi thiên phòng thủ đánh đôi lực tay yếu');

        $this->assertSame('MoiChoi', $profile['level']);
        $this->assertContains(5, $profile['weights']);
        $this->assertContains('nhe', $profile['balances']);
        $this->assertSame(['deo', 'trung_binh'], $profile['stiffness']);
        $this->assertStringContainsString('đầu nhẹ', $profile['recommendation']);

        $beginnerRacket = (object) ['TrinhDoPhuHop' => 'MoiChoi', 'TrongLuong' => '5U', 'DiemCanBang' => 'Đầu nhẹ', 'DoCung' => 'Dẻo'];
        $proRacket = (object) ['TrinhDoPhuHop' => 'ChuyenNghiep', 'TrongLuong' => '3U', 'DiemCanBang' => 'Đầu nặng', 'DoCung' => 'Rất cứng'];
        $this->assertGreaterThan(
            $this->call('diemTheoHoSoVot', $proRacket, $profile),
            $this->call('diemTheoHoSoVot', $beginnerRacket, $profile)
        );
    }

    public function test_a_budget_only_request_shortlists_before_asking_for_playing_level_and_style(): void
    {
        $state = [
            'trinh_do' => false,
            'loi_choi' => false,
            'ngan_sach' => true,
            'chi_tiet' => false,
        ];
        $completeState = [
            'trinh_do' => true,
            'loi_choi' => true,
            'ngan_sach' => true,
            'chi_tiet' => false,
        ];

        $this->assertTrue($this->call('coCanShortlistVot', 'Tôi muốn mua vợt dưới 4 triệu', $state));
        $this->assertFalse($this->call('coCanShortlistVot', 'Tôi muốn mua vợt dưới 4 triệu', $completeState));
    }

    public function test_specific_racket_request_starts_the_advice_flow_instead_of_random_catalog_search(): void
    {
        [$reply] = $this->chat(['Tìm vợt 4U']);

        $this->assertStringContainsString('<!--tuvan:vot:', $reply);
        $this->assertStringNotContainsString('![', $reply);
    }

    public function test_requested_weight_and_grip_are_mandatory_candidate_filters(): void
    {
        $products = new Collection([
            (object) ['TrongLuong' => '4U (80-84g)', 'ChuViTayCam' => 'G5'],
            (object) ['TrongLuong' => '5U (75-79g)', 'ChuViTayCam' => 'G5'],
            (object) ['TrongLuong' => '4U (80-84g)', 'ChuViTayCam' => 'G4'],
        ]);

        $matches = $this->call('locTheoThongSoBatBuoc', $products, 'tôi cần vợt 4U cán G5');

        $this->assertCount(1, $matches);
        $this->assertSame('4U (80-84g)', $matches->first()->TrongLuong);
        $this->assertSame('G5', $matches->first()->ChuViTayCam);
    }

    public function test_it_detects_comparisons_and_splits_two_product_names(): void
    {
        $question = 'vợt cầu lông yonex astrox 100zz và vợt cầu lông lining Axforce 90 Max vợt nào nên mua';
        $this->assertTrue($this->call('laYeuCauSoSanh', mb_strtolower($question)));
        $names = $this->call('tachHaiTenSanPham', $question);
        $this->assertContains('astrox', $this->call('layTuKhoaTenSanPham', $names[0]));
        $this->assertContains('axforce', $this->call('layTuKhoaTenSanPham', $names[1]));

        $this->assertTrue($this->call('laYeuCauSoSanh', 'so sánh hai mẫu vừa tư vấn'));
        $this->assertNull($this->call('tachHaiTenSanPham', 'So sánh hai mẫu vừa tư vấn'));
        $this->assertFalse($this->call('laYeuCauSoSanh', 'tư vấn vợt cho người mới'));
    }

    public function test_specific_intents_are_recognised_before_generic_advice(): void
    {
        $this->assertTrue($this->call('laHoiKhuyenMai', 'tôi đang muốn tìm vợt khuyến mãi'));
        $this->assertTrue($this->call('laHoiKhuyenMai', 'có vợt nào đang giảm giá không'));
        $this->assertTrue($this->call('laHoiMaGiamGia', 'đơn 2 triệu thì dùng mã nào giảm giá'));
        $this->assertFalse($this->call('laHoiKhuyenMai', 'đơn 2 triệu thì dùng mã nào giảm giá'));
        $this->assertSame(['Giày', 'giày'], $this->call('layDanhMucTheoCau', 'giày nào đang sale'));
        $this->assertSame(['Vợt', 'vợt'], $this->call('layDanhMucTheoCau', 'có vợt nào đang giảm giá không'));
        $this->assertFalse($this->call('laHoiKhuyenMai', 'tư vấn vợt cho người mới'));
    }

    public function test_customer_picked_pair_is_recognised_and_compared_by_price(): void
    {
        $question = 'vợt cầu lông yonex astrox 100zz và vợt victorthruster ryuga II tôi nên mua vợt nào';
        $this->assertTrue($this->call('laYeuCauSoSanh', mb_strtolower($question)));
        $names = $this->call('tachHaiTenSanPham', $question);
        $this->assertContains('astrox', $this->call('layTuKhoaTenSanPham', $names[0]));
        $this->assertContains('ryuga', $this->call('layTuKhoaTenSanPham', $names[1]));
        $this->assertNotContains('nào', $this->call('layTuKhoaTenSanPham', $names[1]));

        $pickedPair = 'Vợt Cầu Lông Lining Axforce 90 Max và Vợt Cầu Lông Yonex Power Cushion 65Z3 tôi nên chọn mua cái nào';
        $this->assertTrue($this->call('laYeuCauSoSanh', mb_strtolower($pickedPair)));
        $pairNames = $this->call('tachHaiTenSanPham', $pickedPair);
        $this->assertContains('axforce', $this->call('layTuKhoaTenSanPham', $pairNames[0]));
        $this->assertSame(['yonex', 'power', 'cushion', '65z3'], $this->call('layTuKhoaTenSanPham', $pairNames[1]));

        $cheap = (object) ['TenSanPham' => 'Ryuga II', 'GiaBan' => 3900000, 'GiaKhuyenMai' => null];
        $pricey = (object) ['TenSanPham' => 'Astrox 100ZZ', 'GiaBan' => 4500000, 'GiaKhuyenMai' => 4200000];
        $reply = $this->call('soSanhTheoGia', collect([$pricey, $cheap]), ['request' => 'vợt trình độ chuyên nghiệp dưới 4 triệu']);

        $this->assertStringContainsString('Xét về giá, nên mua Ryuga II', $reply);
        $this->assertStringContainsString('300.000đ', $reply);
        $this->assertStringContainsString('chỉ có Ryuga II là vừa túi tiền', $reply);
    }

    public function test_answering_the_play_style_question_continues_the_interview(): void
    {
        [, $second] = $this->chat([
            'Tôi là người mới chơi đang muốn tìm mua một cây vợt',
            'tôi thích đập cầu tấn công',
        ]);

        $this->assertStringContainsString('người mới chơi, thích tấn công', $second);
        $this->assertStringContainsString('<!--tuvan:vot:ngan_sach-->', $second);
    }

    public function test_level_and_style_without_the_word_racket_still_start_racket_advice(): void
    {
        $this->assertSame('vot', $this->call('layLoaiTuVanBatDau', 'tôi chơi trình độ nâng cao, thích công thủ toàn diện, ngân sách dưới 4 triệu'));
        $this->assertSame('giay', $this->call('layLoaiTuVanBatDau', 'tư vấn giày cho người mới chơi'));
        $this->assertNull($this->call('layLoaiTuVanBatDau', 'chính sách bảo hành thế nào'));
    }

    public function test_profile_uses_grip_and_string_tension_columns(): void
    {
        $profile = $this->call('taoHoSoVot', 'người mới chơi thích tấn công tay hơi nhỏ');

        $this->assertSame('G5', $profile['grip']);
        $this->assertSame([20, 24], $profile['tension']);
        // Người mới thích tấn công: cân bằng hoặc hơi nặng đầu, không chỉ đầu nặng.
        $this->assertContains('deu', $profile['balances']);
        $this->assertSame(28, $this->call('layMucCangToiDa', '20-28 lbs'));

        $g5 = (object) ['TrinhDoPhuHop' => 'MoiChoi', 'TrongLuong' => '5U', 'DiemCanBang' => 'Cân bằng', 'DoCung' => 'Dẻo', 'ChuViTayCam' => 'G5', 'MucCangToiDa' => '20-26 lbs'];
        $g4 = (object) ['TrinhDoPhuHop' => 'MoiChoi', 'TrongLuong' => '5U', 'DiemCanBang' => 'Cân bằng', 'DoCung' => 'Dẻo', 'ChuViTayCam' => 'G4', 'MucCangToiDa' => '20-26 lbs'];
        $this->assertGreaterThan($this->call('diemTheoHoSoVot', $g4, $profile), $this->call('diemTheoHoSoVot', $g5, $profile));
    }

    public function test_picking_from_the_listed_products_is_detected(): void
    {
        $this->assertTrue($this->call('laChonTrongMauDaGoiY', 'tôi chơi ở trình độ chuyên nghiệp chọn cho tôi trong các mẫu bên trên'));
        $this->assertTrue($this->call('laChonTrongMauDaGoiY', 'trong mấy cây vừa gợi ý thì nên lấy cây nào'));
        $this->assertFalse($this->call('laChonTrongMauDaGoiY', 'so sánh các mẫu bên trên'));
        $this->assertFalse($this->call('laChonTrongMauDaGoiY', 'tư vấn vợt cho người mới chơi'));
    }

    public function test_asking_for_rackets_by_level_lists_them_then_keeps_the_level(): void
    {
        // Lịch sử: khách hỏi danh sách vợt chuyên nghiệp, chatbot liệt kê rồi hỏi lối chơi; khách trả lời tiêu chí.
        $session = $this->call('layPhienTuVan', [
            ['sender' => 'KhachHang', 'content' => 'cho tôi các loại vợt phù hợp với người chơi chuyên nghiệp'],
            ['sender' => 'Chatbot', 'content' => 'Dạ, đây là các mẫu vợt...<!--tuvan:vot:loi_choi-->'],
            ['sender' => 'KhachHang', 'content' => 'tôi đánh tấn công, ngân sách 5 triệu'],
        ]);

        // Câu trả lời chỉ có tiêu chí phải được ghi vào phiên cũ, không làm mất trình độ đã nói.
        $this->assertSame('vot', $session['loai']);
        $this->assertStringContainsString('chuyên nghiệp', $session['request']);
        $this->assertStringContainsString('tấn công', $session['request']);
        $kept = $this->call('layPhienTuVan', [
            ['sender' => 'KhachHang', 'content' => 'cho tôi các loại vợt phù hợp với người chơi chuyên nghiệp'],
            ['sender' => 'Chatbot', 'content' => 'Dạ, đây là các mẫu vợt...<!--tuvan:vot:loi_choi-->'],
        ]);
        $this->assertSame('vot', $kept['loai']);
        $this->assertSame('loi_choi', $kept['slot']);
        $updated = $this->call('ghiNhanTraLoiTuVan', $kept, 'tôi đánh tấn công, ngân sách 5 triệu');
        $this->assertStringContainsString('chuyên nghiệp', $updated['request']);
        $this->assertStringContainsString('tấn công', $updated['request']);
    }

    public function test_switching_item_restarts_advice_with_only_personal_info_kept(): void
    {
        // Câu hỏi lại nhu cầu khi đổi món có kèm nhu cầu đã gom (giữ số đo) => câu trả lời sau ghép vào đó.
        $carried = 'nặng 50kg nữ tôi muốn tìm thêm một cái váy';
        $session = $this->call('layPhienTuVan', [
            ['sender' => 'KhachHang', 'content' => 'còn váy thì sao'],
            ['sender' => 'Chatbot', 'content' => 'Dạ, mình chuyển sang tư vấn váy...<!--tuvan:quan_ao:qa_nhu_cau--><!--tuvan-yeucau:' . base64_encode($carried) . '-->'],
            ['sender' => 'KhachHang', 'content' => 'cần có quần bảo hộ'],
        ]);

        $this->assertSame('quan_ao', $session['loai']);
        $this->assertSame('qa_nhu_cau', $session['slot']);
        $this->assertStringContainsString('nặng 50kg', $session['request']);
        $this->assertStringContainsString('bảo hộ', $session['request']);
    }

    public function test_greetings_policies_and_basic_knowledge_have_built_in_answers(): void
    {
        $answer = fn (string $message) => (string) $this->call('traLoiCauHoiCoSan', mb_strtolower($message));

        $this->assertStringContainsString('chào bạn', $answer('Xin chào'));
        $this->assertStringContainsString('không có gì', $answer('ok cảm ơn bạn nhiều'));
        $this->assertStringContainsString('3 ngày', $answer('chính sách đổi trả thế nào'));
        $this->assertStringContainsString('3 ngày', $answer('mua rồi có trả lại được không shop'));
        $this->assertStringContainsString('bảo hành', $answer('vợt gãy có được bảo hành không'));
        $this->assertStringContainsString('chưa gồm phí vận chuyển', $answer('phí ship bao nhiêu'));
        $this->assertStringContainsString('ngày', $answer('đặt hôm nay bao lâu thì nhận được hàng'));
        $this->assertStringContainsString('VNPay', $answer('shop có những hình thức thanh toán nào'));
        $this->assertStringContainsString('035 214 3569', $answer('shop ở đâu'));
        $this->assertStringContainsString('lbs', $answer('nên căng vợt bao nhiêu kg'));
        $this->assertStringContainsString('Đầu nặng', $answer('điểm cân bằng là gì'));
        // Câu tư vấn mua hàng không bị câu trả lời có sẵn chặn mất
        $this->assertSame('', $answer('xin chào, tư vấn vợt cho tôi'));
        $this->assertSame('', $answer('Tôi muốn mua vợt tầm 3 triệu'));
    }

    public function test_clothing_questions_start_needs_based_advice(): void
    {
        $this->assertSame('quan_ao', $this->call('layLoaiTuVanBatDau', 'tôi đang 40kg nên mặc áo size gì'));
        $this->assertSame('quan_ao', $this->call('layLoaiTuVanBatDau', 'tư vấn váy cầu lông cho tôi'));
        $this->assertSame('bo', $this->call('layLoaiQuanAo', 'tư vấn quần áo cầu lông'));
        $this->assertSame('ao_khoac', $this->call('layLoaiQuanAo', 'cần áo hoodie giữ ấm'));
        $this->assertSame('vay', $this->call('layLoaiQuanAo', 'váy có quần trong không'));
    }

    public function test_clothing_size_is_read_and_estimated_from_body_measurements(): void
    {
        $this->assertSame(40, $this->call('layCanNang', 'tôi đang 40kg'));
        $this->assertSame(158, $this->call('layChieuCao', 'cao 1m58'));
        $this->assertSame(160, $this->call('layChieuCao', 'tôi 1m6'));
        $this->assertSame('M', $this->call('laySizeQuanAo', 'tôi hay mặc size m'));
        $this->assertNull($this->call('laySizeQuanAo', 'nên mặc size gì'));
        $this->assertSame('L', $this->call('tinhSizeTheoBang', 'nam', 68, 172));
        $this->assertSame('M', $this->call('tinhSizeTheoBang', 'nu', 50, 158));
        $this->assertSame('XS', $this->call('tinhSizeTheoBang', 'nu', 40, null));
        $this->assertSame('nu', $this->call('layGioiTinh', 'nữ, mặc đi tập'));
    }
}
