<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Gửi khi admin duyệt đơn (Chờ xác nhận -> Đang giao).
class DonHangDaDuyetMail extends Mailable
{
    use Queueable, SerializesModels;

    public object $donHang;
    public iterable $sanPhams;
    public bool $daThanhToan;

    public function __construct(object $donHang, iterable $sanPhams, bool $daThanhToan = false)
    {
        $this->donHang = $donHang;
        $this->sanPhams = $sanPhams;
        $this->daThanhToan = $daThanhToan;
    }

    public function build()
    {
        return $this->subject('Đơn hàng #' . $this->donHang->ID_DonHang . ' đã được duyệt và đang giao - Badminton ProShop')
                    ->view('emails.donhangdaduyet');
    }
}
