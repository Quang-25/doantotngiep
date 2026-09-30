@include('layouts.header')
<link rel="stylesheet" href="{{ asset('css/tintuc.css') }}">
 <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
@section('content')
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-9 bg-white p-4 rounded shadow-sm">
            <!-- Nút quay lại -->
            <a href="{{ route('tintuc.index') }}" class="text-decoration-none text-primary mb-3 d-inline-block">
                &larr; Quay lại trang tin tức
            </a>

            <h1 class="fw-bold mb-3" style="font-size: 28px;">{{ $tinTuc->TieuDe }}</h1>
            <p class="text-muted mb-4">
                🕒 Đăng lúc: {{ \Carbon\Carbon::parse($tinTuc->NgayDang)->format('d/m/Y H:i') }}
            </p>

            @if(!empty($tinTuc->HinhAnh))
                @php
                    $anhTinTuc = preg_match('/^http/i', $tinTuc->HinhAnh) ? $tinTuc->HinhAnh : asset('images/tintuc/' . $tinTuc->HinhAnh);
                @endphp
                <img src="{{ $anhTinTuc }}" class="img-fluid rounded mb-4" alt="{{ $tinTuc->TieuDe }}" style="width: 100%; max-height: 500px; object-fit: cover;">
            @endif

            <!-- Nội dung bài viết in ra dưới dạng HTML -->
            <div style="line-height: 1.8; font-size: 16px;">
                {!! $tinTuc->NoiDung !!}
            </div>
        </div>
    </div>
</div>
@include('layouts.Footer')