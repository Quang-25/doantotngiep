<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietGioHang extends Model
{
    protected $table = 'ChiTietGioHang';

    protected $primaryKey = 'ID_ChiTietGioHang';

    public $timestamps = false;

    protected $fillable = [
        'ID_GioHang',
        'ID_SanPham',
        'SoLuong',
        'DaChon',
    ];
}
