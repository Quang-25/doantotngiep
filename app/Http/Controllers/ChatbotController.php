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
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'maPhienChat' => ['nullable', 'uuid'],
            'idKhachHang' => ['nullable', 'integer', 'min:1'],
        ]);
        $message = trim($validated['message']);
        $maPhienChat = $validated['maPhienChat'] ?? Str::uuid()->toString();
        $idKhachHang = $validated['idKhachHang'] ?? null;

        $conversationHistory = DB::table('lichsuchat')
            ->where('MaPhienChat', $maPhienChat)
            ->where('ID_KhachHang', $idKhachHang)
            // Sắp theo ID (tự tăng) chứ không theo ThoiGian: tin khách và tin chatbot thường cùng một giây.
            ->orderByDesc('ID_LichSu')
            ->limit(20)
            ->get(['NguoiGui', 'NoiDung'])
            ->reverse()
            ->map(fn ($chat) => [
                'sender' => $chat->NguoiGui,
                'content' => $chat->NoiDung,
            ])
            ->values()
            ->all();

        // Ghi Log tin nhắn Khách Hàng (Chat History DB)
        DB::table('lichsuchat')->insert([
            'ID_KhachHang' => $idKhachHang,
            'MaPhienChat' => $maPhienChat,
            'NguoiGui' => 'KhachHang',
            'NoiDung' => $message,
            'ThoiGian' => now()
        ]);

        $reply = $this->chatbotService->xuLyTinNhan($message, 'pro', $idKhachHang, $conversationHistory);

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