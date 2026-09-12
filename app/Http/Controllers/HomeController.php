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
        $sanPhamBanChay = SanPham::take(6)->get();

        $sanPhamSale = SanPham::whereNotNull('GiaKhuyenMai')
            ->where('GiaKhuyenMai', '<', DB::raw('GiaBan'))
            ->take(3)
            ->get();

        return response()->json([
            'sanPhamBanChay' => $sanPhamBanChay,
            'sanPhamSale' => $sanPhamSale
        ]);
    }
}