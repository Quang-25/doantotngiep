<?php

namespace App\Support;

/**
 * Quy tắc giá dùng chung toàn website:
 * giá khuyến mãi chỉ có hiệu lực khi > 0 và nhỏ hơn giá bán; ngược lại bán theo giá bán.
 */
class GiaSanPham
{
    /** Biểu thức SQL giá đang bán (dùng cho lọc / sắp xếp theo giá). */
    public const SQL_GIA_HIEN_TAI = 'CASE WHEN sanpham.GiaKhuyenMai > 0 AND sanpham.GiaKhuyenMai < sanpham.GiaBan THEN sanpham.GiaKhuyenMai ELSE sanpham.GiaBan END';

    public static function coKhuyenMai(object $sanPham): bool
    {
        $giaKm = (float) ($sanPham->GiaKhuyenMai ?? 0);

        return $giaKm > 0 && $giaKm < (float) $sanPham->GiaBan;
    }

    public static function hienTai(object $sanPham): float
    {
        return self::coKhuyenMai($sanPham) ? (float) $sanPham->GiaKhuyenMai : (float) $sanPham->GiaBan;
    }
}
