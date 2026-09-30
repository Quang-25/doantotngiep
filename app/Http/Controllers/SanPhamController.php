<?php
namespace App\Http\Controllers;

use App\Models\SanPham;
use App\Models\DanhMuc;
use App\Models\ThuongHieu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // BẮT BUỘC THÊM DÒNG NÀY ĐỂ TRUY VẤN DB::table

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

    // THÊM HÀM CHI TIẾT SẢN PHẨM VÀO ĐÂY
    public function chiTietSanPham($id)
    {
        // 1. Lấy thông tin cơ bản của sản phẩm (JOIN với bảng Danh Mục và Thương Hiệu)
        $sanPham = DB::table('SanPham')
            ->join('DanhMuc', 'SanPham.ID_DanhMuc', '=', 'DanhMuc.ID_DanhMuc')
            ->join('ThuongHieu', 'SanPham.ID_ThuongHieu', '=', 'ThuongHieu.ID_ThuongHieu')
            ->select('SanPham.*', 'DanhMuc.TenDanhMuc', 'ThuongHieu.TenThuongHieu')
            ->where('SanPham.ID_SanPham', $id)
            ->where('SanPham.TrangThai', 'DangBan')
            ->first();

        if (!$sanPham) {
            return redirect('/')->with('error', 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.');
        }

        // 2. Lấy thông số kỹ thuật (Chỉ dành cho Vợt) từ bảng thongsovot
        $thongSoVot = DB::table('ThongSoVot')->where('ID_SanPham', $id)->first();

        // 3. Lấy thư viện hình ảnh phụ từ bảng hinhanhsanpham
        $hinhAnhGallery = DB::table('HinhAnhSanPham')
            ->where('ID_SanPham', $id)
            ->orderBy('ThuTu', 'asc')
            ->get();

        return view('chitietsanpham', compact('sanPham', 'thongSoVot', 'hinhAnhGallery'));
    }
}