<?php

namespace Tests\Unit;

use App\Services\OrderService;
use PHPUnit\Framework\TestCase;

class OrderStatusFlowTest extends TestCase
{
    public function test_orders_follow_pending_shipping_completed_flow(): void
    {
        $service = new OrderService();

        // Admin duyệt rồi mới giao, giao xong mới hoàn thành.
        $this->assertTrue($service->coTheChuyenTrangThai('ChoXacNhan', 'DangGiao'));
        $this->assertTrue($service->coTheChuyenTrangThai('DangGiao', 'HoanThanh'));

        // Không được bỏ qua bước duyệt hay đi lùi.
        $this->assertFalse($service->coTheChuyenTrangThai('ChoXacNhan', 'HoanThanh'));
        $this->assertFalse($service->coTheChuyenTrangThai('DangGiao', 'ChoXacNhan'));

        // Hủy được khi chưa hoàn thành; đơn đã xong / đã hủy thì khóa.
        $this->assertTrue($service->coTheChuyenTrangThai('ChoXacNhan', 'DaHuy'));
        $this->assertFalse($service->coTheChuyenTrangThai('HoanThanh', 'DaHuy'));
        $this->assertFalse($service->coTheChuyenTrangThai('DaHuy', 'DangGiao'));
    }
}
