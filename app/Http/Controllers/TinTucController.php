<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TinTucController extends Controller
{
    // Hiển thị danh sách Khuyến mãi & Tin tức
    public function index()
    {
        $khuyenMais = DB::table('khuyenmai')
            ->where('TrangThai', 'HoatDong')
            ->whereDate('NgayBatDau', '<=', now())
            ->whereDate('NgayKetThuc', '>=', now())
            ->orderBy('NgayKetThuc', 'asc')
            ->get();

        $tinTucs = DB::table('tintuc')
            ->where('TrangThai', 'Hien')
            ->orderBy('NgayDang', 'desc')
            ->paginate(6);

        // Gọi ra file resources/views/tintuc.blade.php
        return view('tintuc', compact('khuyenMais', 'tinTucs'));
    }

    // Hiển thị Đọc chi tiết bài báo
    public function show(int $id)
    {
        $tinTuc = DB::table('tintuc')
            ->where('ID_TinTuc', $id)
            ->where('TrangThai', 'Hien')
            ->first();
        
        if (!$tinTuc) {
            abort(404, 'Bài viết không tồn tại hoặc đã bị ẩn');
        }

        // Gọi ra file resources/views/chitiettintuc.blade.php
        return view('chitiettintuc', compact('tinTuc'));
    }
}