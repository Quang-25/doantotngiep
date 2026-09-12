<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\DanhMuc;
use App\Models\ThuongHieu;

class SanPham extends Model
{
    protected $table = 'SanPham';

    protected $primaryKey = 'ID_SanPham';

    public $timestamps = false;

    protected $fillable = [
        'ID_DanhMuc',
        'ID_ThuongHieu',
        'TenSanPham',
        'MoTa',
        'GiaBan',
        'GiaKhuyenMai',
        'SoLuongTon',
        'HinhAnh',
        'TrangThai',
        'NgayTao',
    ];

    protected $casts = [
        'GiaBan' => 'integer',
        'GiaKhuyenMai' => 'integer',
        'SoLuongTon' => 'integer',
        'NgayTao' => 'datetime',
    ];

    public function danhMuc()
    {
        return $this->belongsTo(DanhMuc::class, 'ID_DanhMuc', 'ID_DanhMuc');
    }

    public function thuongHieu()
    {
        return $this->belongsTo(ThuongHieu::class, 'ID_ThuongHieu', 'ID_ThuongHieu');
    }
}