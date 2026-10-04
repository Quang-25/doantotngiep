<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Kiểm tra xem đã đăng nhập chưa và VaiTro có phải là Admin (Đồng bộ với AuthController của bạn)
        if ($request->session()->has('ID_TaiKhoan') && $request->session()->get('VaiTro') === 'Admin') {
            return $next($request); // Hợp lệ -> Cho phép truy cập
        }

        // Nếu client gọi qua Fetch API/AJAX (chuẩn RESTful) -> Trả về JSON lỗi 403
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Truy cập bị từ chối. Bạn không phải là Quản trị viên!'
            ], 403);
        }

        // Nếu client gõ URL trực tiếp trên trình duyệt -> Chuyển hướng về trang đăng nhập
        return redirect('/Dangnhap')->with('error', 'Vui lòng đăng nhập tài khoản Quản trị viên!');
    }
}