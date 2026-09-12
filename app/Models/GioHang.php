<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GioHang extends Model
{
    protected $table = 'GioHang';

    protected $primaryKey = 'ID_GioHang';

    public $timestamps = false;

    protected $fillable = [
        'ID_KhachHang',
        'MaPhien',
    ];
}
