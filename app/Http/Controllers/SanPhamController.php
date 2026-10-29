<?php
namespace App\Http\Controllers;

use App\Models\SanPham;
use App\Models\DanhMuc;
use App\Models\ThuongHieu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Schema;

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

        // Thông số vợt (trình độ, trọng lượng, cân bằng, độ cứng, cỡ cán)
        $locVot = [
            'trinh_do' => fn ($q, $v) => $q->where('thongsovot.TrinhDoPhuHop', $v),
            'trong_luong' => fn ($q, $v) => $q->where('thongsovot.TrongLuong', 'like', $v . '%'),
            'can_bang' => fn ($q, $v) => $q->where('thongsovot.DiemCanBang', 'like', $v . '%'),
            'do_cung' => fn ($q, $v) => $q->where('thongsovot.DoCung', $v),
            'co_can' => fn ($q, $v) => $q->where('thongsovot.ChuViTayCam', 'like', '%' . $v . '%'),
        ];
        $coLocVot = collect(array_keys($locVot))->contains(fn ($key) => $request->filled($key));
        if ($coLocVot) {
            $query->join('thongsovot', 'sanpham.ID_SanPham', '=', 'thongsovot.ID_SanPham')->select('sanpham.*');
            foreach ($locVot as $key => $apply) {
                if ($request->filled($key)) {
                    $apply($query, (string) $request->input($key));
                }
            }
        }

        // Giá đang bán (đã tính khuyến mãi)
        $giaHienTai = 'CASE WHEN sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai < sanpham.GiaBan THEN sanpham.GiaKhuyenMai ELSE sanpham.GiaBan END';
        [$giaTu, $giaDen] = $this->layKhoangGia($request);
        if ($giaTu !== null) {
            $query->whereRaw("($giaHienTai) >= ?", [$giaTu]);
        }
        if ($giaDen !== null) {
            $query->whereRaw("($giaHienTai) <= ?", [$giaDen]);
        }

        if ($request->boolean('giam_gia')) {
            $query->where('sanpham.GiaKhuyenMai', '>', 0)->whereColumn('sanpham.GiaKhuyenMai', '<', 'sanpham.GiaBan');
        }
        if ($request->boolean('con_hang')) {
            $query->where('sanpham.SoLuongTon', '>', 0);
        }

        // Size giày / quần áo: so khớp đúng từng size trong danh sách "S,M,L" (bỏ khoảng trắng, xuống dòng)
        if ($request->filled('size') && preg_match('/^[A-Za-z0-9]{1,4}$/', (string) $request->size)) {
            $query->whereRaw(
                "FIND_IN_SET(?, REPLACE(REPLACE(REPLACE(UPPER(sanpham.SizeCoSan), ' ', ''), CHAR(13), ''), CHAR(10), '')) > 0",
                [mb_strtoupper((string) $request->size)]
            );
        }

        match ($request->sap_xep) {
            'gia_tang' => $query->orderByRaw("($giaHienTai) asc"),
            'gia_giam' => $query->orderByRaw("($giaHienTai) desc"),
            'giam_nhieu' => $query->orderByRaw("CASE WHEN sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai < sanpham.GiaBan THEN (sanpham.GiaBan - sanpham.GiaKhuyenMai) / sanpham.GiaBan ELSE 0 END desc"),
            'ten' => $query->orderBy('sanpham.TenSanPham'),
            default => $query->orderByDesc('sanpham.ID_SanPham'),
        };

        $sanpham = $query->get();
        return response()->json([
            'success' => true,
            'data' => $sanpham
        ]);
    }

    // Khoảng giá: ô tự nhập (gia_tu, gia_den) ưu tiên hơn mức chọn sẵn "min-max"; vẫn hỗ trợ kiểu cũ gia=1/2/3.
    private function layKhoangGia(Request $request): array
    {
        $soTien = fn ($value) => is_numeric($value) && $value >= 0 ? (float) $value : null;
        $giaTu = $soTien($request->gia_tu);
        $giaDen = $soTien($request->gia_den);
        if ($giaTu !== null || $giaDen !== null) {
            return $giaTu !== null && $giaDen !== null && $giaTu > $giaDen ? [$giaDen, $giaTu] : [$giaTu, $giaDen];
        }

        $gia = (string) $request->gia;
        if (preg_match('/^(\d*)-(\d*)$/', $gia, $match)) {
            return [$match[1] !== '' ? (float) $match[1] : null, $match[2] !== '' ? (float) $match[2] : null];
        }

        return match ($gia) {
            '1' => [null, 999999],
            '2' => [1000000, 5000000],
            '3' => [5000001, null],
            default => [null, null],
        };
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
        $query = DB::table('SanPham')
            ->join('DanhMuc', 'SanPham.ID_DanhMuc', '=', 'DanhMuc.ID_DanhMuc')
            ->join('ThuongHieu', 'SanPham.ID_ThuongHieu', '=', 'ThuongHieu.ID_ThuongHieu')
            ->select('SanPham.*', 'DanhMuc.TenDanhMuc', 'ThuongHieu.TenThuongHieu')
            ->where('SanPham.ID_SanPham', $id)
            ->where('SanPham.TrangThai', 'DangBan');

        if (Schema::hasColumn('SanPham', 'can nang')) {
            $query->addSelect('SanPham.can nang as CanNang');
        }

        $sanPham = $query->first();

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