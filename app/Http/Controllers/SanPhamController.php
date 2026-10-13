<?php
namespace App\Http\Controllers;

use App\Models\SanPham;
use App\Models\DanhMuc;
use App\Models\ThuongHieu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 

class SanPhamController extends Controller
{
    // 1. HÀM INDEX
    public function index(Request $request)
    {
        $query = DB::table('sanpham')->where('TrangThai', 'DangBan');

        // Hỗ trợ truyền nhiều Danh mục (VD: danh_muc=3,5)
        if ($request->has('danh_muc')) {
            $dmArray = explode(',', $request->danh_muc);
            if (count($dmArray) > 1) {
                $query->whereIn('ID_DanhMuc', $dmArray);
            } else {
                $query->where('ID_DanhMuc', $request->danh_muc);
            }
        }

        if ($request->has('thuong_hieu')) {
            $query->where('ID_ThuongHieu', $request->thuong_hieu);
        }

        // Nếu load trang có sẵn search (Áo, Quần) thì lọc luôn Tên Sản Phẩm
        if ($request->has('search')) {
            $search = mb_strtolower(trim($request->search), 'UTF-8');
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(TenSanPham) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(MoTa) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->has('trinh_do')) {
            $query->join('thongsovot', 'sanpham.ID_SanPham', '=', 'thongsovot.ID_SanPham')
                  ->where('thongsovot.TrinhDoPhuHop', $request->trinh_do)
                  ->select('sanpham.*'); 
        }

        $sanPhams = $query->orderBy('sanpham.ID_SanPham', 'desc')->paginate(12);
        $sanPhams->appends($request->all());

        return view('sanpham', compact('sanPhams'));
    }

    // 2. HÀM API
    public function api(Request $request)
    {
        $query = SanPham::where('sanpham.TrangThai', 'DangBan'); 
        $idDanhMuc = $request->danh_muc ?? $request->danhmuc;

        if ($request->search) {
            $search = trim($request->search);
            $search = mb_strtolower($search, 'UTF-8');

            // NẾU ĐÃ CHỌN DANH MỤC: Chỉ tìm chữ "quần", "áo" trong Tên Sản Phẩm (Bỏ qua tên Danh Mục)
            if ($idDanhMuc) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(sanpham.TenSanPham) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('LOWER(sanpham.MoTa) LIKE ?', ["%{$search}%"]);
                });
            } else {
                // TÌM KIẾM TỰ DO: Chạy như cũ (Tìm cả trong Danh mục và Thương hiệu)
                $danhMuc = DanhMuc::whereRaw('LOWER(TenDanhMuc) LIKE ?', ["%{$search}%"])->first();
                $thuongHieu = ThuongHieu::whereRaw('LOWER(TenThuongHieu) LIKE ?', ["%{$search}%"])->first();

                if ($danhMuc) {
                    $query->where('sanpham.ID_DanhMuc', $danhMuc->ID_DanhMuc);
                } elseif ($thuongHieu) {
                    $query->where('sanpham.ID_ThuongHieu', $thuongHieu->ID_ThuongHieu);
                } else {
                    $query->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(sanpham.TenSanPham) LIKE ?', ["%{$search}%"])
                          ->orWhereRaw('LOWER(sanpham.MoTa) LIKE ?', ["%{$search}%"]);
                    });
                }
            }
        }
        
        // Xử lý Danh mục mảng (Ví dụ: 3,5)
        if ($idDanhMuc) {
            $dmArray = explode(',', $idDanhMuc);
            if (count($dmArray) > 1) {
                $query->whereIn('sanpham.ID_DanhMuc', $dmArray);
            } else {
                $query->where('sanpham.ID_DanhMuc', $idDanhMuc);
            }
        }
        
        $idThuongHieu = $request->thuong_hieu ?? $request->thuonghieu;
        if ($idThuongHieu) {
            $query->where('sanpham.ID_ThuongHieu', $idThuongHieu);
        }

        if ($request->trinh_do) {
            $query->join('thongsovot', 'sanpham.ID_SanPham', '=', 'thongsovot.ID_SanPham')
                  ->where('thongsovot.TrinhDoPhuHop', $request->trinh_do)
                  ->select('sanpham.*'); 
        }
        
        if ($request->gia == 1)
            $query->where('sanpham.GiaBan', '<', 1000000);
        elseif ($request->gia == 2)
            $query->whereBetween('sanpham.GiaBan', [1000000, 5000000]);
        elseif ($request->gia == 3)
            $query->where('sanpham.GiaBan', '>', 5000000);
            
        $sanpham = $query->get();
        return response()->json([
            'success' => true,
            'data' => $sanpham
        ]);
    }

    // 3. HÀM BỘ LỌC
    public function boLoc()
    {
        return response()->json([
            'success' => true,
            'danhmuc' => DanhMuc::all(),
            'thuonghieu' => ThuongHieu::all()
        ]);
    }

    // 4. HÀM CHI TIẾT SẢN PHẨM
    public function chiTietSanPham(int $id)
    {
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

        $thongSoVot = DB::table('ThongSoVot')->where('ID_SanPham', $id)->first();

        $hinhAnhGallery = DB::table('HinhAnhSanPham')
            ->where('ID_SanPham', $id)
            ->orderBy('ThuTu', 'asc')
            ->get();

        return view('chitietsanpham', compact('sanPham', 'thongSoVot', 'hinhAnhGallery'));
    }
}