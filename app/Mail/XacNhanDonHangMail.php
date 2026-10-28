<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class XacNhanDonHangMail extends Mailable
{
    use Queueable, SerializesModels;

    public object $donHang;

    public function __construct(object $donHang)
    {
        $this->donHang = $donHang;
    }

    public function build()
    {
        return $this->subject('Xác nhận đơn hàng - Badminton ProShop')
                    ->view('emails.xacnhandonhang');
    }
}