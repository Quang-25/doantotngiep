<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Email theo từng bước trả hàng.
// $buoc: yeu_cau (báo khách đã nhận yêu cầu) / shop (báo shop có yêu cầu mới) / chap_nhan / tu_choi / da_nhan / da_hoan_tien
class TraHangMail extends Mailable
{
    use Queueable, SerializesModels;

    private const TIEU_DE = [
        'yeu_cau' => 'đã nhận yêu cầu trả hàng',
        'shop' => 'có yêu cầu trả hàng mới',
        'chap_nhan' => 'yêu cầu trả hàng được chấp nhận',
        'tu_choi' => 'yêu cầu trả hàng bị từ chối',
        'da_nhan' => 'shop đã nhận lại hàng trả',
        'da_hoan_tien' => 'đã hoàn tiền',
    ];

    public object $donHang;
    public string $buoc;
    public ?string $ghiChu;

    public function __construct(object $donHang, string $buoc, ?string $ghiChu = null)
    {
        $this->donHang = $donHang;
        $this->buoc = $buoc;
        $this->ghiChu = $ghiChu;
    }

    public function build()
    {
        return $this->subject('Đơn hàng #' . $this->donHang->ID_DonHang . ' - ' . (self::TIEU_DE[$this->buoc] ?? 'cập nhật trả hàng') . ' - Badminton ProShop')
                    ->view('emails.trahang');
    }
}
