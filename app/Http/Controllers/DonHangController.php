<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OrderService;


class DonHangController extends Controller
{
    private const TRANG_THAI = [
        'ChoXacNhan' => 'Chờ xác nhận',
        'DangGiao' => 'Đang giao',
        'HoanThanh' => 'Hoàn thành',
        'DaHuy' => 'Đã hủy',
    ];

    public function __construct(private OrderService $orderService)
    {
    }

    // Trang "Đơn mua"
    public function trang(Request $request)
    {
        // Không chặn ở server: trang tự lấy thông tin khách từ trình duyệt (giống giỏ hàng),
        // chưa đăng nhập thì donhang.js chuyển sang trang đăng nhập.
        return view('donhang');
    }

    // Danh sách đơn của khách, lọc theo trạng thái; kèm số đơn của từng trạng thái để hiện trên tab.
    public function danhSach(Request $request): JsonResponse
    {
        $idKhachHang = $this->layIdKhachHang($request);
        if (!$idKhachHang) {
            return response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập để xem đơn hàng.'], 401);
        }

        $trangThai = $request->query('trang_thai');
        $query = DB::table('donhang')->where('ID_KhachHang', $idKhachHang);
        if ($trangThai && isset(self::TRANG_THAI[$trangThai])) {
            $query->where('TrangThaiDon', $trangThai);
        }
        $donHangs = $query->orderByDesc('NgayDat')->orderByDesc('ID_DonHang')->get();

        $sanPhamTheoDon = $this->laySanPhamTheoDon($donHangs->pluck('ID_DonHang')->all());
        $donDaThanhToan = DB::table('thanhtoan')
            ->whereIn('ID_DonHang', $donHangs->pluck('ID_DonHang')->all() ?: [0])
            ->where('TrangThaiGiaoDich', 'ThanhCong')
            ->pluck('ID_DonHang')->map(fn ($id) => (int) $id)->all();
        $soLuong = DB::table('donhang')
            ->where('ID_KhachHang', $idKhachHang)
            ->groupBy('TrangThaiDon')
            ->selectRaw('TrangThaiDon, COUNT(*) as SoLuong')
            ->pluck('SoLuong', 'TrangThaiDon');

        return response()->json([
            'success' => true,
            'soLuongTheoTrangThai' => $soLuong,
            'donHangs' => $donHangs->map(fn ($donHang) => $this->dinhDangDon(
                $donHang,
                $sanPhamTheoDon[$donHang->ID_DonHang] ?? [],
                false,
                in_array((int) $donHang->ID_DonHang, $donDaThanhToan, true)
            ))->values(),
        ]);
    }

    // Chi tiết một đơn + tiến trình đơn hàng.
    public function chiTiet(Request $request, int $id): JsonResponse
    {
        $donHang = $this->layDonCuaKhach($request, $id);
        if ($donHang instanceof JsonResponse) {
            return $donHang;
        }

        $thanhToan = DB::table('thanhtoan')->where('ID_DonHang', $id)->orderByDesc('ID_ThanhToan')->first();
        $maGiamGia = $donHang->ID_KhuyenMai
            ? DB::table('khuyenmai')->where('ID_KhuyenMai', $donHang->ID_KhuyenMai)->value('MaCode')
            : null;

        return response()->json([
            'success' => true,
            'donHang' => $this->dinhDangDon(
                $donHang,
                $this->laySanPhamTheoDon([$id])[$id] ?? [],
                true,
                ($thanhToan->TrangThaiGiaoDich ?? null) === 'ThanhCong'
            ) + [
                'TienTrinh' => $this->taoTienTrinh($donHang, $thanhToan),
                'MaGiamGia' => $maGiamGia,
                'TrangThaiThanhToan' => $this->moTaThanhToan($donHang, $thanhToan),
            ],
        ]);
    }

    // Khách tự hủy đơn khi shop chưa xác nhận.
    public function huy(Request $request, int $id): JsonResponse
    {
        $donHang = $this->layDonCuaKhach($request, $id);
        if ($donHang instanceof JsonResponse) {
            return $donHang;
        }
        if ($donHang->TrangThaiDon !== 'ChoXacNhan') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng đã được shop xử lý (' . (self::TRANG_THAI[$donHang->TrangThaiDon] ?? $donHang->TrangThaiDon)
                    . ') nên không thể tự hủy. Vui lòng liên hệ shop để được hỗ trợ.',
            ], 422);
        }

        // Đơn đã thanh toán VNPay cần shop hoàn tiền => không cho khách tự hủy.
        if ($this->orderService->daThanhToanVNPay($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn đã thanh toán qua VNPay nên cần shop hỗ trợ hủy và hoàn tiền. Vui lòng liên hệ shop.',
            ], 422);
        }

        $lyDo = trim((string) $request->input('ly_do', ''));
        $lyDo = mb_substr($lyDo !== '' ? $lyDo : 'Không nêu lý do', 0, 255);
        if (!$this->orderService->huyDon($id, 'Khách hủy đơn: ' . $lyDo)) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng không còn ở trạng thái có thể hủy.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Đã hủy đơn hàng #' . $id . ' thành công.']);
    }

    // ---------- Hàm hỗ trợ ----------

    // Mã xác thực cấp cho khách lúc đăng nhập, ký bằng APP_KEY (không lưu database).
    // Giúp trang Đơn mua chạy được cả khi phiên trên server đã mất, mà người khác không đoán được.
    public static function taoMaXacThuc(int $idKhachHang): string
    {
        return hash_hmac('sha256', 'don-hang|' . $idKhachHang, (string) config('app.key'));
    }

    // Lấy khách đang xem: ưu tiên phiên server, nếu mất phiên thì dùng ID + mã xác thực trình duyệt gửi lên.
    private function layIdKhachHang(Request $request): ?int
    {
        $idPhien = $request->session()->get('ID_KhachHang');
        if ($idPhien) {
            return (int) $idPhien;
        }

        $id = (int) $request->header('X-Khach-Hang-Id');
        $ma = (string) $request->header('X-Khach-Hang-Token');
        if ($id > 0 && $ma !== '' && hash_equals(self::taoMaXacThuc($id), $ma)) {
            return $id;
        }

        return null;
    }

    private function layDonCuaKhach(Request $request, int $id): object
    {
        $idKhachHang = $this->layIdKhachHang($request);
        if (!$idKhachHang) {
            return response()->json(['success' => false, 'message' => 'Vui lòng đăng nhập để xem đơn hàng.'], 401);
        }
        $donHang = DB::table('donhang')->where('ID_DonHang', $id)->where('ID_KhachHang', $idKhachHang)->first();

        return $donHang ?: response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng của bạn.'], 404);
    }

    // Sản phẩm (kèm ảnh) của các đơn: [ID_DonHang => [sản phẩm...]].
    private function laySanPhamTheoDon(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $anhDauTien = DB::table('hinhanhsanpham')
            ->select('ID_SanPham', DB::raw('MIN(DuongDan) as DuongDan'))
            ->where('ThuTu', 1)
            ->groupBy('ID_SanPham');

        return DB::table('chitietdonhang')
            ->join('sanpham', 'chitietdonhang.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->leftJoinSub($anhDauTien, 'anh', 'anh.ID_SanPham', '=', 'sanpham.ID_SanPham')
            ->whereIn('chitietdonhang.ID_DonHang', $ids)
            ->select('chitietdonhang.ID_DonHang', 'sanpham.ID_SanPham', 'sanpham.TenSanPham', 'sanpham.HinhAnh',
                'anh.DuongDan as HinhAnhPhu', 'chitietdonhang.SoLuong', 'chitietdonhang.GiaMua')
            ->get()
            ->groupBy('ID_DonHang')
            ->map(fn ($items) => $items->map(fn ($item) => [
                'ID_SanPham' => (int) $item->ID_SanPham,
                'TenSanPham' => $item->TenSanPham,
                'HinhAnh' => $this->duongDanAnh($item->HinhAnh ?: $item->HinhAnhPhu),
                'SoLuong' => (int) $item->SoLuong,
                'GiaMua' => (int) $item->GiaMua,
            ])->values()->all())
            ->all();
    }

    private function duongDanAnh(?string $image): string
    {
        if (empty($image)) {
            return '/images/no-image.jpg';
        }

        return preg_match('/^https?:\/\//i', $image) ? $image : '/images/sanpham/' . ltrim($image, '/');
    }

    private function dinhDangDon(object $donHang, array $sanPhams, bool $chiTiet = false, bool $daThanhToan = false): array
    {
        $data = [
            'ID_DonHang' => (int) $donHang->ID_DonHang,
            'NgayDat' => $donHang->NgayDat,
            'TrangThai' => $donHang->TrangThaiDon,
            'TenTrangThai' => self::TRANG_THAI[$donHang->TrangThaiDon] ?? $donHang->TrangThaiDon,
            'ThanhTien' => (int) $donHang->ThanhTien,
            'PhuongThucThanhToan' => $donHang->PhuongThucThanhToan,
            // Khách tự hủy khi shop chưa duyệt; đơn đã thanh toán VNPay cần shop hoàn tiền nên không tự hủy.
            'CoTheHuy' => $donHang->TrangThaiDon === 'ChoXacNhan' && !$daThanhToan,
            'SanPhams' => $sanPhams,
        ];
        if ($chiTiet) {
            $data += [
                'TongTien' => (int) $donHang->TongTien,
                'TienGiam' => (int) $donHang->TienGiam,
                'TenNguoiNhan' => $donHang->TenNguoiNhan,
                'SoDienThoaiNhan' => $donHang->SoDienThoaiNhan,
                'DiaChiGiaoHang' => $donHang->DiaChiGiaoHang,
                'GhiChu' => $donHang->GhiChu,
            ];
        }

        return $data;
    }

    private function moTaThanhToan(object $donHang, ?object $thanhToan): string
    {
        if ($donHang->PhuongThucThanhToan !== 'VNPay') {
            return $donHang->TrangThaiDon === 'HoanThanh' ? 'Đã thanh toán khi nhận hàng' : 'Thanh toán khi nhận hàng (COD)';
        }

        return match ($thanhToan->TrangThaiGiaoDich ?? null) {
            'ThanhCong' => 'Đã thanh toán qua VNPay',
            'ThatBai' => 'Thanh toán VNPay không thành công',
            default => 'Chờ thanh toán VNPay',
        };
    }

    // Các bước của đơn: đã qua / đang ở / chưa tới. Chỉ bước "Đặt hàng" có thời gian (NgayDat).
    private function taoTienTrinh(object $donHang, ?object $thanhToan): array
    {
        $buoc = [
            ['ma' => 'DatHang', 'ten' => 'Đơn hàng đã đặt', 'moTa' => 'Bạn đã đặt hàng thành công.', 'thoiGian' => $donHang->NgayDat],
            ['ma' => 'ChoXacNhan', 'ten' => 'Chờ xác nhận', 'moTa' => match (true) {
                $donHang->PhuongThucThanhToan !== 'VNPay' => 'Shop đang xác nhận đơn hàng, bạn sẽ thanh toán khi nhận hàng.',
                ($thanhToan->TrangThaiGiaoDich ?? null) === 'ThanhCong' => 'Bạn đã thanh toán VNPay, shop đang xác nhận đơn hàng.',
                default => 'Đơn đang chờ bạn hoàn tất thanh toán VNPay.',
            }],
            ['ma' => 'DangGiao', 'ten' => 'Đang giao hàng', 'moTa' => 'Đơn hàng đã được bàn giao cho đơn vị vận chuyển.'],
            ['ma' => 'HoanThanh', 'ten' => 'Giao hàng thành công', 'moTa' => 'Bạn đã nhận được hàng. Cảm ơn bạn đã mua sắm!'],
        ];

        if ($donHang->TrangThaiDon === 'DaHuy') {
            return [
                $buoc[0] + ['trangThai' => 'xong'],
                ['ma' => 'DaHuy', 'ten' => 'Đơn hàng đã hủy', 'moTa' => $this->lyDoHuy($donHang), 'trangThai' => 'huy'],
            ];
        }

        $viTri = ['ChoXacNhan' => 1, 'DangGiao' => 2, 'HoanThanh' => 3][$donHang->TrangThaiDon] ?? 1;

        return array_map(function ($step, $index) use ($viTri) {
            return $step + ['trangThai' => $index < $viTri ? 'xong' : ($index === $viTri ? 'hienTai' : 'chuaToi')];
        }, $buoc, array_keys($buoc));
    }

    private function lyDoHuy(object $donHang): string
    {
        if ($donHang->GhiChu && preg_match('/Khách hủy đơn: (.+)$/u', $donHang->GhiChu, $matches)) {
            return 'Bạn đã hủy đơn. Lý do: ' . $matches[1];
        }

        return 'Đơn hàng đã bị hủy.';
    }
}
