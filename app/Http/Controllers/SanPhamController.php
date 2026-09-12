<?php
namespace App\Http\Controllers;
use App\Models\SanPham;
use App\Models\DanhMuc;
use App\Models\ThuongHieu;
use Illuminate\Http\Request;
class SanPhamController extends Controller
{
    public function index()
    {
        return view('sanpham');
    }

    public function api(Request $request)
    {
        $query = SanPham::where('TrangThai', 'DangBan');

        if ($request->search) {
            $search = trim($request->search);
            $search = mb_strtolower($search, 'UTF-8');

            $danhMuc = DanhMuc::whereRaw(
                'LOWER(TenDanhMuc) LIKE ?',
                ["%{$search}%"]
            )->first();

            $thuongHieu = ThuongHieu::whereRaw(
                'LOWER(TenThuongHieu) LIKE ?',
                ["%{$search}%"]
            )->first();

            if ($danhMuc) {
                $query->where('ID_DanhMuc', $danhMuc->ID_DanhMuc);
            } elseif ($thuongHieu) {
                $query->where('ID_ThuongHieu', $thuongHieu->ID_ThuongHieu);
            } else {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw(
                        'LOWER(TenSanPham) LIKE ?',
                        ["%{$search}%"]
                    )
                    ->orWhereRaw(
                        'LOWER(MoTa) LIKE ?',
                        ["%{$search}%"]
                    );
                });
            }
        }
        if ($request->danhmuc)
            $query->where('ID_DanhMuc', $request->danhmuc);
        if ($request->thuonghieu)
            $query->where('ID_ThuongHieu', $request->thuonghieu);
        if ($request->gia == 1)
            $query->where('GiaBan', '<', 1000000);
        elseif ($request->gia == 2)
            $query->whereBetween('GiaBan', [1000000, 5000000]);
        elseif ($request->gia == 3)
            $query->where('GiaBan', '>', 5000000);
        $sanpham = $query->get();
        return response()->json([
            'success' => true,
            'data' => $sanpham
        ]);
    }

    public function boLoc()
    {
        return response()->json([
            'success' => true,
            'danhmuc' => DanhMuc::all(),
            'thuonghieu' => ThuongHieu::all()
        ]);
    }
}