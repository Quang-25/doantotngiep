<?php

namespace App\Support;

/**
 * Danh sách size khách được chọn cho một sản phẩm.
 * - Lấy theo cột SizeCoSan (vd "39,40,41,42").
 * - Giày mà dữ liệu chưa nhập đủ (0 hoặc 1 size) thì dùng dải size chuẩn 38–44.
 */
class SizeSanPham
{
    public const DANH_MUC_GIAY = 2;
    public const SIZE_GIAY_CHUAN = ['38', '39', '40', '41', '42', '43', '44'];

    /** Tách chuỗi SizeCoSan thành mảng size (bỏ khoảng trắng, xuống dòng, trùng lặp). */
    public static function tach(?string $sizeCoSan): array
    {
        $sizes = preg_split('/[,;\/\s]+/u', mb_strtoupper(trim((string) $sizeCoSan)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($sizes));
    }

    /** Chuẩn hóa chuỗi admin nhập để lưu: "39, 40 ,41" -> "39,40,41". */
    public static function chuanHoa(?string $sizeCoSan): ?string
    {
        $sizes = self::tach($sizeCoSan);

        return $sizes ? implode(',', $sizes) : null;
    }

    public static function laGiay(object $sanPham): bool
    {
        return (int) ($sanPham->ID_DanhMuc ?? 0) === self::DANH_MUC_GIAY;
    }

    /** Các size hiển thị cho khách chọn; mảng rỗng = sản phẩm không cần chọn size. */
    public static function danhSach(object $sanPham): array
    {
        $sizes = self::tach($sanPham->SizeCoSan ?? null);

        if (self::laGiay($sanPham) && count($sizes) < 2) {
            $sizes = array_values(array_unique(array_merge(self::SIZE_GIAY_CHUAN, $sizes)));
            sort($sizes, SORT_NATURAL);
        }

        return $sizes;
    }

    /**
     * Đọc lại size từng sản phẩm từ ghi chú đơn hàng:
     * "Size đã chọn: Giày A - size 42; Áo B - size M" -> ["Giày A" => "42", "Áo B" => "M"].
     */
    public static function tuGhiChu(?string $ghiChu): array
    {
        if (!preg_match('/^Size đã chọn: (.+)$/mu', (string) $ghiChu, $m)) {
            return [];
        }

        $kq = [];
        foreach (explode('; ', $m[1]) as $phan) {
            if (preg_match('/^(.+) - size ([A-Z0-9]+)$/u', trim($phan), $x)) {
                $kq[$x[1]] = $x[2];
            }
        }

        return $kq;
    }

    /** Size chọn sẵn: size duy nhất đang lưu trong dữ liệu (nếu có). */
    public static function macDinh(object $sanPham): ?string
    {
        $sizes = self::tach($sanPham->SizeCoSan ?? null);

        return count($sizes) === 1 ? $sizes[0] : null;
    }
}
