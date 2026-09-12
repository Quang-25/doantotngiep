<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KhachHang extends Model
{
    protected $table = 'KhachHang';

    protected $primaryKey = 'ID_KhachHang';

    public $timestamps = false;

    protected $fillable = [
        'ID_TaiKhoan',
        'HoTen',
        'SoDienThoai',
        'DiaChi',
    ];

    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(
            TaiKhoan::class,
            'ID_TaiKhoan',
            'ID_TaiKhoan'
        );
    }
}