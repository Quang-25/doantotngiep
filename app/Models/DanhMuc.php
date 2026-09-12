<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DanhMuc extends Model
{
      protected $table = 'DanhMuc';

    protected $primaryKey = 'ID_DanhMuc';

    public $timestamps = false;

    protected $fillable = [
        'TenDanhMuc',
        'MoTa'
    ];

    public function sanPham()
    {
        return $this->hasMany(SanPham::class, 'ID_DanhMuc', 'ID_DanhMuc');
    }
}
