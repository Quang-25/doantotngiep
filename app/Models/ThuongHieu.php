<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThuongHieu extends Model
{
    protected $table = 'ThuongHieu';

    protected $primaryKey = 'ID_ThuongHieu';

    public $timestamps = false;

    protected $fillable = [
        'TenThuongHieu',
        'MoTa'
    ];

    public function sanPham()
    {
        return $this->hasMany(SanPham::class, 'ID_ThuongHieu', 'ID_ThuongHieu');
    }
}
