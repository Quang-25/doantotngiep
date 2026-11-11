<?php

namespace App\Mail;

use App\Services\DonHangPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class XacNhanDonHangMail extends Mailable
{
    use Queueable, SerializesModels;

    public object $donHang;

    /** Chi tiết đơn (sản phẩm, trạng thái thanh toán...) dùng cho nội dung email; rỗng thì email chỉ có thông tin chung. */
    public array $chiTiet;

    public function __construct(object $donHang)
    {
        $this->donHang = $donHang;
        $this->chiTiet = (new DonHangPdf())->layThongTin((int) $donHang->ID_DonHang) ?? [];
    }

    public function build()
    {
        $mail = $this->subject('Xác nhận đơn hàng #' . $this->donHang->ID_DonHang . ' - Badminton ProShop')
            ->view('emails.xacnhandonhang');

        // Đính kèm phiếu đơn hàng dạng PDF. Lỗi tạo PDF không được làm hỏng việc gửi email.
        try {
            $pdfService = new DonHangPdf();
            $pdf = $pdfService->taoPdf((int) $this->donHang->ID_DonHang);
            if ($pdf !== null) {
                $mail->attachData($pdf, $pdfService->tenFile((int) $this->donHang->ID_DonHang), ['mime' => 'application/pdf']);
            }
        } catch (\Throwable $e) {
            Log::error('Không tạo được PDF đơn hàng #' . $this->donHang->ID_DonHang . ': ' . $e->getMessage());
        }

        return $mail;
    }
}
