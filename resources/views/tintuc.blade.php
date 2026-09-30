@include('layouts.header')

<!-- Nhúng CSS từ thư mục public/css/tintuc.css -->
 <link rel="stylesheet" href="{{ asset('css/tintuc.css') }}">
 <link rel="stylesheet" href="{{ asset('css/footer.css') }}">
<div class="container my-5">

    <!-- PHẦN 1: MÃ KHUYẾN MÃI -->
    <div class="mb-5">
        <h3 class="section-title section-title--promo">
            <span class="section-icon">🔥</span> Mã Khuyến Mãi Đang Diễn Ra
        </h3>

        <div class="row g-3 justify-content-center">
            @forelse($khuyenMais as $km)
                <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                    <div class="promo-card">
                        <div class="promo-top">
                            <span class="promo-code">{{ $km->MaCode }}</span>
                            <small class="promo-qty">SL: {{ $km->SoLuong }}</small>
                        </div>

                        <h6 class="promo-title">{{ $km->MoTa }}</h6>

                        <div class="promo-info">
                            <p class="mb-1">
                                Đơn tối thiểu:
                                <b>{{ number_format($km->GiaTriDonToiThieu, 0, ',', '.') }}đ</b>
                            </p>
                            <p class="mb-0">
                                HSD: {{ \Carbon\Carbon::parse($km->NgayBatDau)->format('d/m/Y') }} -
                                <b class="text-danger">{{ \Carbon\Carbon::parse($km->NgayKetThuc)->format('d/m/Y') }}</b>
                            </p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-muted text-center">Chưa có khuyến mãi nào.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- PHẦN 2: BÀI VIẾT TIN TỨC -->
    <div>
        <h3 class="section-title section-title--news">
            <span class="section-icon">📰</span> Tin Tức Mới Nhất
        </h3>

        <div class="row g-4 justify-content-center">
            @forelse($tinTucs as $tin)
                <div class="col-lg-4 col-md-6 col-12">
                    <!-- Liên kết sang trang đọc chi tiết -->
                    <a href="{{ route('tintuc.show', $tin->ID_TinTuc) }}" class="news-card">

                        @php
                            $anhTinTuc = preg_match('/^http/i', $tin->HinhAnh)
                                ? $tin->HinhAnh
                                : asset('images/tintuc/' . $tin->HinhAnh);
                        @endphp
                        <img src="{{ !empty($tin->HinhAnh) ? $anhTinTuc : asset('images/no-image.jpg') }}"
                             class="news-img" alt="{{ $tin->TieuDe }}">

                        <div class="news-content">
                            <div class="news-title">{{ $tin->TieuDe }}</div>

                            <div class="news-date-wrapper">
                                <div class="news-date-line"></div>
                                <div class="news-date-badge">
                                    {{ \Carbon\Carbon::parse($tin->NgayDang)->format('d-m-Y H:i') }}
                                </div>
                            </div>

                            <div class="news-excerpt">
                                {{ Str::limit(strip_tags($tin->NoiDung), 130) }}
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <p class="news-empty">Đang cập nhật tin tức...</p>
                </div>
            @endforelse
        </div>

        @if($tinTucs instanceof \Illuminate\Pagination\AbstractPaginator && $tinTucs->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $tinTucs->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

<!-- Footer đặt NGOÀI container để tràn hết chiều ngang -->
@include('layouts.Footer')