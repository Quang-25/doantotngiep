<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// Phản hồi khách gửi từ trang Liên hệ. Bấm "Trả lời" trong Gmail sẽ trả lời thẳng cho khách.
class LienHeMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $lienHe;

    public function __construct(array $lienHe)
    {
        $this->lienHe = $lienHe;
    }

    public function build()
    {
        return $this->subject('[Liên hệ] ' . $this->lienHe['chu_de'] . ' - ' . $this->lienHe['ho_ten'])
                    ->replyTo($this->lienHe['email'], $this->lienHe['ho_ten'])
                    ->view('emails.lienhe');
    }
}
