document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('orderPieChart');
    if (!canvas || typeof Chart === 'undefined') return;

    // Đọc dữ liệu từ thuộc tính data-values do Blade gán
    let values = [0, 0, 0, 0];
    try {
        values = JSON.parse(canvas.dataset.values);
    } catch (e) {
        console.error('Không đọc được dữ liệu biểu đồ:', e);
    }

    const tong = values.reduce((a, b) => a + b, 0);

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Chờ xác nhận', 'Đang giao', 'Hoàn thành', 'Đã hủy'],
            datasets: [{
                data: values,
                backgroundColor: ['#ffc107', '#0d6efd', '#198754', '#dc3545'],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, usePointStyle: true }
                },
                tooltip: {
                    callbacks: {
                        label: function (c) {
                            const phanTram = tong ? Math.round(c.parsed / tong * 100) : 0;
                            return ' ' + c.label + ': ' + c.parsed + ' đơn (' + phanTram + '%)';
                        }
                    }
                }
            }
        }
    });
});