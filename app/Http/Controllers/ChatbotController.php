<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ChatbotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    protected ChatbotService $chatbotService;

    // Nhúng ChatbotService vào Controller
    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    public function tuVan(Request $request)
    {
        // Nhận dữ liệu từ Fetch API
        $message = trim($request->input('message', ''));
        $version = $request->input('version', 'standard');
        $maPhienChat = $request->input('maPhienChat') ?: Str::uuid()->toString();
        
        // FIX: BỘ LỌC ÉP KIỂU DỮ LIỆU TỪ JAVASCRIPT
        $idKhachHangRaw = $request->input('idKhachHang');
        
        // Chuyển các chuỗi ảo của JS thành giá trị null thực sự của PHP
        if (in_array(strtolower((string)$idKhachHangRaw), ['null', 'undefined', '', '0'])) {
            $idKhachHang = null;
        } else {
            // Nếu có số thật, ép về kiểu Số Nguyên (Integer) để an toàn cho Database
            $idKhachHang = (int) $idKhachHangRaw; 
        }

        // Ghi Log tin nhắn Khách Hàng (Chat History DB)
        DB::table('lichsuchat')->insert([
            'ID_KhachHang' => $idKhachHang,
            'MaPhienChat' => $maPhienChat,
            'NguoiGui' => 'KhachHang',
            'NoiDung' => $message,
            'ThoiGian' => now()
        ]);

        // Uỷ quyền xử lý logic cho Service (Lúc này $idKhachHang đã chuẩn 100%)
        $reply = $this->chatbotService->xuLyTinNhan($message, $version, $idKhachHang);

        // Ghi Log tin nhắn Chatbot
        DB::table('lichsuchat')->insert([
            'ID_KhachHang' => $idKhachHang,
            'MaPhienChat' => $maPhienChat,
            'NguoiGui' => 'Chatbot',
            'NoiDung' => $reply,
            'ThoiGian' => now()
        ]);

        // Trả kết quả về cho Khung Chat (RESTful)
        return response()->json([
            'success' => true,
            'reply' => $reply,
            'maPhienChat' => $maPhienChat
        ]);
    }
}