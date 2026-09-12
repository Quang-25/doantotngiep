<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TaiKhoan extends Model
{
    protected $table = 'TaiKhoan';

    protected $primaryKey = 'ID_TaiKhoan';

    public $timestamps = false;

    protected $fillable = [
        'Email',
        'MatKhau',
        'VaiTro',
        'TrangThai',
    ];

    protected $hidden = [
        'MatKhau',
    ];

    public function khachHang(): HasOne
    {
        return $this->hasOne(
            KhachHang::class,
            'ID_TaiKhoan',
            'ID_TaiKhoan'
        );
        
    }
    
}