<?php

namespace App\Http\Controllers;

use App\Mail\LienHeMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Trang Liên hệ: không lưu database, phản hồi của khách được gửi thẳng về Gmail của shop.
class LienHeController extends Controller
{
    // Mỗi phiên trình duyệt chỉ gửi tối đa 3 lần trong 10 phút (chống spam, không cần bảng).
    private const GIOI_HAN_SO_LAN = 3;
    private const KHOANG_THOI_GIAN = 600;

    public function trang()
    {
        return view('lienhe');
    }

    public function gui(Request $request): JsonResponse
    {
        // Ô ẩn "website": người thật không thấy nên để trống, bot tự điền => bỏ qua âm thầm.
        if ($request->filled('website')) {
            return response()->json(['success' => true, 'message' => 'Cảm ơn bạn đã gửi phản hồi!']);
        }

        $data = $request->validate([
            'ho_ten' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'so_dien_thoai' => ['nullable', 'regex:/^[0-9+\s.\-]{8,15}$/'],
            'chu_de' => ['required', 'string', 'max:150'],
            'noi_dung' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'ho_ten.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email để shop phản hồi lại bạn.',
            'email.email' => 'Email không hợp lệ.',
            'so_dien_thoai.regex' => 'Số điện thoại không hợp lệ.',
            'chu_de.required' => 'Vui lòng chọn chủ đề.',
            'noi_dung.required' => 'Vui lòng nhập nội dung.',
            'noi_dung.min' => 'Nội dung cần ít nhất 10 ký tự.',
            'noi_dung.max' => 'Nội dung tối đa 3000 ký tự.',
        ]);

        $now = time();
        $lanGui = array_values(array_filter(
            (array) $request->session()->get('lien_he_lan_gui', []),
            fn ($time) => $now - (int) $time < self::KHOANG_THOI_GIAN
        ));
        if (count($lanGui) >= self::GIOI_HAN_SO_LAN) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã gửi nhiều phản hồi liên tiếp. Vui lòng thử lại sau ít phút hoặc gọi hotline 035 214 3569.',
            ], 429);
        }

        try {
            Mail::to($this->emailNhan())->send(new LienHeMail($data));
        } catch (\Throwable $e) {
            Log::error('Lỗi gửi email liên hệ: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Chưa gửi được phản hồi, vui lòng thử lại sau hoặc gọi hotline 035 214 3569.',
            ], 500);
        }

        $lanGui[] = $now;
        $request->session()->put('lien_he_lan_gui', $lanGui);

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn bạn! Shop đã nhận được phản hồi và sẽ liên hệ lại qua email ' . $data['email'] . ' sớm nhất.',
        ]);
    }

    // Gmail nhận phản hồi: CONTACT_EMAIL trong .env, mặc định là email gửi mail của shop.
    private function emailNhan(): string
    {
        return (string) (env('CONTACT_EMAIL') ?: config('mail.from.address') ?: 'Cohoi2512@gmail.com');
    }
}
