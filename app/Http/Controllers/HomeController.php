<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use App\Models\SanPham;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('Home');
    }

    public function  api(): JsonResponse
    {
        // Chỉ hiển thị sản phẩm đang bán (ngừng kinh doanh thì ẩn khỏi trang chủ)
        $sanPhamBanChay = SanPham::where('TrangThai', 'DangBan')->take(8)->get();

        $sanPhamSale = SanPham::where('TrangThai', 'DangBan')
            ->whereNotNull('GiaKhuyenMai')
            ->where('GiaKhuyenMai', '>', 0)
            ->where('GiaKhuyenMai', '<', DB::raw('GiaBan'))
            ->take(8)
            ->get();

        return response()->json([
            'sanPhamBanChay' => $sanPhamBanChay,
            'sanPhamSale' => $sanPhamSale
        ]);
    }
}