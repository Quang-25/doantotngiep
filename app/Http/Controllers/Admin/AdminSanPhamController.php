<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Support\SizeSanPham;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Bắt buộc phải có để gọi CSDL

class AdminSanPhamController extends Controller
{
    // ... (Giữ nguyên hàm dashboard của bạn nếu có)

    // ==========================================
    // 1. READ & SEARCH (Hiển thị & Tìm kiếm)
    // ==========================================
    public function quanLySanPham(Request $request)
    {
        $search = $request->input('search');

        // Sản phẩm hết hàng (tồn kho <= 0) mà còn ghi "Đang bán" thì chuyển sang "Ngừng kinh doanh" trong database.
        // Vẫn hiện ở trang này để admin biết và cập nhật số lượng hàng.
        DB::table('sanpham')->where('SoLuongTon', '<=', 0)->where('TrangThai', 'DangBan')->update(['TrangThai' => 'NgungKinhDoanh']);

        // JOIN 3 bảng: sanpham, danhmuc, thuonghieu
        $query = DB::table('sanpham')
            ->leftJoin('danhmuc', 'sanpham.ID_DanhMuc', '=', 'danhmuc.ID_DanhMuc')
            ->leftJoin('thuonghieu', 'sanpham.ID_ThuongHieu', '=', 'thuonghieu.ID_ThuongHieu')
            ->leftJoin('thongsovot', 'sanpham.ID_SanPham', '=', 'thongsovot.ID_SanPham')
            ->select('sanpham.*', 'danhmuc.TenDanhMuc', 'thuonghieu.TenThuongHieu',
                'thongsovot.TrongLuong', 'thongsovot.ChuViTayCam', 'thongsovot.ChatLieu', 'thongsovot.DiemCanBang',
                'thongsovot.DoCung', 'thongsovot.MucCangToiDa', 'thongsovot.TrinhDoPhuHop')
            // Ẩn sản phẩm đã xóa (ngừng kinh doanh); sản phẩm hết hàng vẫn hiện (nhãn Ngừng kinh doanh) để admin nhập thêm hàng.
            ->where(function ($q) {
                $q->where('sanpham.TrangThai', '!=', 'NgungKinhDoanh')->orWhere('sanpham.SoLuongTon', '<=', 0);
            });
             

        // Gom điều kiện tìm kiếm vào một nhóm để không làm mất bộ lọc "chưa ngừng kinh doanh"
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sanpham.TenSanPham', 'LIKE', '%' . $search . '%')
                  ->orWhere('sanpham.ID_SanPham', $search);
            });
        }

        $sanPhams = $query->orderBy('sanpham.ID_SanPham', 'desc')->paginate(10);
        $sanPhams->appends(['search' => $search]); // Giữ từ khóa khi sang trang 2, 3...

        // Lấy dữ liệu cho Thẻ Select (Combobox) trong form Thêm/Sửa
        $danhMuc = DB::table('danhmuc')->get(); 
        $thuongHieu = DB::table('thuonghieu')->get();

        // Hình ảnh phụ của các sản phẩm trên trang (để đổ vào form Sửa)
        $hinhAnhPhu = DB::table('hinhanhsanpham')
            ->whereIn('ID_SanPham', collect($sanPhams->items())->pluck('ID_SanPham'))
            ->orderBy('ThuTu')->orderBy('ID_HinhAnh')
            ->get()
            ->groupBy('ID_SanPham')
            ->map(fn ($anh) => $anh->pluck('DuongDan')->values()->all())
            ->all();

        return view('admin.quanlysanpham', compact('sanPhams', 'danhMuc', 'thuongHieu', 'search', 'hinhAnhPhu'));
    }

    // ==========================================
    // 2. CREATE (API Thêm)
    // ==========================================
    public function themSanPham(Request $request)
    {
        if ($loi = $this->kiemTraGia($request) ?? $this->kiemTraThongSoVot($request) ?? $this->kiemTraAnhPhu($request)) {
            return response()->json(['success' => false, 'message' => $loi], 422);
        }
        try {
            $idMoi = DB::table('sanpham')->insertGetId([
                'TenSanPham' => $request->TenSanPham,
                'ID_DanhMuc' => $request->ID_DanhMuc,
                'ID_ThuongHieu' => $request->ID_ThuongHieu,
                'GiaBan' => $request->GiaBan,
                'GiaKhuyenMai' => $request->GiaKhuyenMai ?: null,
                'SoLuongTon' => $request->SoLuongTon,
                'MoTa' => $request->MoTa,
                'SizeCoSan' => SizeSanPham::chuanHoa($request->SizeCoSan),
                'HinhAnh' => $request->HinhAnh, // Lưu trực tiếp Link URL
                'TrangThai' => (int) $request->SoLuongTon > 0 ? 'DangBan' : 'NgungKinhDoanh' // Hết hàng = ngừng kinh doanh
            ]);
            $this->luuThongSoVot($request, (int) $idMoi);
            $this->luuAnhPhu($request, (int) $idMoi);

            return response()->json(['success' => true, 'message' => 'Thêm sản phẩm thành công!'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 3. UPDATE (API Sửa)
    // ==========================================
    public function suaSanPham(Request $request, int $id)
    {
        if ($loi = $this->kiemTraGia($request) ?? $this->kiemTraThongSoVot($request) ?? $this->kiemTraAnhPhu($request)) {
            return response()->json(['success' => false, 'message' => $loi], 422);
        }
        try {
            DB::table('sanpham')->where('ID_SanPham', $id)->update([
                'TenSanPham' => $request->TenSanPham,
                'ID_DanhMuc' => $request->ID_DanhMuc,
                'ID_ThuongHieu' => $request->ID_ThuongHieu,
                'GiaBan' => $request->GiaBan,
                'GiaKhuyenMai' => $request->GiaKhuyenMai ?: null,
                'SoLuongTon' => $request->SoLuongTon,
                'MoTa' => $request->MoTa,
                'SizeCoSan' => SizeSanPham::chuanHoa($request->SizeCoSan),
                'HinhAnh' => $request->HinhAnh, // Cập nhật bằng Link URL mới
                // Tồn kho về 0 => ngừng kinh doanh; nhập thêm hàng (tồn > 0) => bán lại
                'TrangThai' => (int) $request->SoLuongTon > 0 ? 'DangBan' : 'NgungKinhDoanh'
            ]);
            $this->luuThongSoVot($request, $id);
            $this->luuAnhPhu($request, $id);

            return response()->json(['success' => true, 'message' => 'Cập nhật thành công!'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 4. DELETE (API Xóa - Ngừng kinh doanh)
    // ==========================================
    public function xoaSanPham(int $id)
    {
        try {
            // Đổi trạng thái thay vì xóa cứng để bảo toàn hóa đơn cũ
            DB::table('sanpham')->where('ID_SanPham', $id)->update(['TrangThai' => 'NgungKinhDoanh']);
            return response()->json(['success' => true, 'message' => 'Đã chuyển sản phẩm sang Ngừng kinh doanh và ẩn khỏi trang quản lý. Dữ liệu vẫn được giữ trong hệ thống.'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // THÔNG SỐ VỢT (bảng thongsovot) — chỉ lưu khi danh mục là Vợt cầu lông
    // ==========================================
    private const DANH_MUC_VOT = 1;
    private const TRUONG_THONG_SO_VOT = [
        'TrongLuong' => 20, 'ChuViTayCam' => 20, 'ChatLieu' => 100,
        'DiemCanBang' => 50, 'DoCung' => 50, 'MucCangToiDa' => 50,
    ];

    private function kiemTraThongSoVot(Request $request): ?string
    {
        if ((int) $request->ID_DanhMuc !== self::DANH_MUC_VOT) {
            return null;
        }
        if ($request->filled('ChuViTayCam') && !in_array($request->ChuViTayCam, ['to', 'nhỏ'], true)) {
            return 'Chu vi tay cầm chỉ được chọn "to" hoặc "nhỏ".';
        }
        if ($request->filled('TrinhDoPhuHop') && !in_array($request->TrinhDoPhuHop, ['MoiChoi', 'TrungCap', 'NangCao', 'ChuyenNghiep'], true)) {
            return 'Trình độ phù hợp không hợp lệ.';
        }
        foreach (self::TRUONG_THONG_SO_VOT as $truong => $toiDa) {
            if (mb_strlen(trim((string) $request->input($truong))) > $toiDa) {
                return "Thông số {$truong} dài quá {$toiDa} ký tự.";
            }
        }

        return null;
    }

    private function luuThongSoVot(Request $request, int $idSanPham): void
    {
        if ((int) $request->ID_DanhMuc !== self::DANH_MUC_VOT) {
            return;
        }

        $duLieu = [];
        foreach (array_keys(self::TRUONG_THONG_SO_VOT) as $truong) {
            $giaTri = trim((string) $request->input($truong));
            $duLieu[$truong] = $giaTri === '' ? null : $giaTri;
        }
        $duLieu['TrinhDoPhuHop'] = $request->input('TrinhDoPhuHop') ?: 'TrungCap';

        DB::table('thongsovot')->updateOrInsert(['ID_SanPham' => $idSanPham], $duLieu);
    }

    // ==========================================
    // HÌNH ẢNH PHỤ (bảng hinhanhsanpham) — mỗi dòng một link, thứ tự dòng = ThuTu
    // ==========================================
    private function tachAnhPhu(Request $request): array
    {
        $dong = preg_split('/\r\n|\r|\n/', (string) $request->input('HinhAnhPhu', ''));

        return array_values(array_unique(array_filter(array_map('trim', $dong), fn ($url) => $url !== '')));
    }

    private function kiemTraAnhPhu(Request $request): ?string
    {
        $dsAnh = $this->tachAnhPhu($request);
        if (count($dsAnh) > 10) {
            return 'Tối đa 10 hình ảnh phụ cho một sản phẩm.';
        }
        foreach ($dsAnh as $viTri => $url) {
            if (mb_strlen($url) > 255 || !preg_match('#^https?://\S+$#i', $url)) {
                return 'Link hình ảnh phụ dòng ' . ($viTri + 1) . ' không hợp lệ (phải bắt đầu bằng http:// hoặc https://, tối đa 255 ký tự).';
            }
        }

        return null;
    }

    // Thay toàn bộ ảnh phụ của sản phẩm bằng danh sách mới (để trống = xóa hết ảnh phụ).
    private function luuAnhPhu(Request $request, int $idSanPham): void
    {
        if (!$request->has('HinhAnhPhu')) {
            return;
        }

        DB::transaction(function () use ($request, $idSanPham) {
            DB::table('hinhanhsanpham')->where('ID_SanPham', $idSanPham)->delete();
            foreach ($this->tachAnhPhu($request) as $viTri => $url) {
                DB::table('hinhanhsanpham')->insert([
                    'ID_SanPham' => $idSanPham,
                    'DuongDan' => $url,
                    'ThuTu' => $viTri + 1,
                ]);
            }
        });
    }

    // Giá khuyến mãi (nếu có) phải nhỏ hơn giá bán, nếu không trang khách sẽ hiển thị sai.
    private function kiemTraGia(Request $request): ?string
    {
        $giaBan = (float) $request->GiaBan;
        if ($giaBan <= 0) {
            return 'Giá bán phải lớn hơn 0.';
        }
        if ($request->filled('GiaKhuyenMai')) {
            $giaKm = (float) $request->GiaKhuyenMai;
            if ($giaKm <= 0 || $giaKm >= $giaBan) {
                return 'Giá khuyến mãi phải lớn hơn 0 và nhỏ hơn giá bán (' . number_format($giaBan, 0, ',', '.') . 'đ). Để trống nếu không giảm giá.';
            }
        }

        return null;
    }
}
