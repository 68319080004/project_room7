<?php
// ============================================
// ไฟล์: admin/reports.php
// คำอธิบาย: รายงานและสรุปยอด
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';

requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
$invoice = new Invoice($db);

// ดึงรายงานรายปี
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$yearlyReport = $invoice->getYearlySummary($currentYear);

// คำนวณยอดรวมทั้งปี
$totalYearInvoices = 0;
$totalYearAmount = 0;
$totalYearPaid = 0;

foreach ($yearlyReport as $report) {
    $totalYearInvoices += $report['total_invoices'];
    $totalYearAmount += $report['total_amount'];
    $totalYearPaid += $report['total_paid'];
}

$totalYearUnpaid = $totalYearAmount - $totalYearPaid;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - ระบบจัดการหอพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-bar-chart"></i> รายงานและสรุปยอด
                    </h1>
                </div>

                <!-- เลือกปี -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">เลือกปี</label>
                                <select name="year" class="form-select" onchange="this.form.submit()">
                                    <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                            <?php echo toBuddhistYear($y); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- สรุปยอดรวมทั้งปี -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title">จำนวนบิลทั้งหมด</h6>
                                <h2><?php echo $totalYearInvoices; ?></h2>
                                <small>รายการ</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6 class="card-title">ยอดรวมทั้งหมด</h6>
                                <h2>฿<?php echo number_format($totalYearAmount); ?></h2>
                                <small>บาท</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title">ชำระแล้ว</h6>
                                <h2>฿<?php echo number_format($totalYearPaid); ?></h2>
                                <small><?php echo $totalYearAmount > 0 ? number_format(($totalYearPaid / $totalYearAmount) * 100, 1) : 0; ?>%</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6 class="card-title">ค้างชำระ</h6>
                                <h2>฿<?php echo number_format($totalYearUnpaid); ?></h2>
                                <small><?php echo $totalYearAmount > 0 ? number_format(($totalYearUnpaid / $totalYearAmount) * 100, 1) : 0; ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- กราฟรายได้รายเดือน -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart-fill"></i> กราฟรายได้ประจำปี <?php echo toBuddhistYear($currentYear); ?></h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyRevenueChart" height="80"></canvas>
                    </div>
                </div>

                <!-- ตารางรายละเอียดรายเดือน -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-table"></i> รายละเอียดรายเดือน</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>เดือน</th>
                                        <th class="text-end">จำนวนบิล</th>
                                        <th class="text-end">ยอดรวม (บาท)</th>
                                        <th class="text-end">ชำระแล้ว (บาท)</th>
                                        <th class="text-end">ค้างชำระ (บาท)</th>
                                        <th class="text-center">สัดส่วนชำระ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($yearlyReport as $report): ?>
                                        <?php
                                        $unpaid = $report['total_amount'] - $report['total_paid'];
                                        $paymentPercent = $report['total_amount'] > 0 ? ($report['total_paid'] / $report['total_amount']) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo getThaiMonth($report['invoice_month']); ?></strong></td>
                                            <td class="text-end"><?php echo $report['total_invoices']; ?></td>
                                            <td class="text-end"><?php echo formatMoney($report['total_amount']); ?></td>
                                            <td class="text-end text-success"><?php echo formatMoney($report['total_paid']); ?></td>
                                            <td class="text-end text-danger"><?php echo formatMoney($unpaid); ?></td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 25px;">
                                                    <div class="progress-bar bg-success" 
                                                         style="width: <?php echo $paymentPercent; ?>%">
                                                        <?php echo number_format($paymentPercent, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>รวมทั้งปี</th>
                                        <th class="text-end"><?php echo $totalYearInvoices; ?></th>
                                        <th class="text-end"><?php echo formatMoney($totalYearAmount); ?></th>
                                        <th class="text-end text-success"><?php echo formatMoney($totalYearPaid); ?></th>
                                        <th class="text-end text-danger"><?php echo formatMoney($totalYearUnpaid); ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ปุ่มพิมพ์ -->
                <div class="mt-4 text-center mb-4">
                    <button onclick="window.print()" class="btn btn-primary btn-lg">
                        <i class="bi bi-printer"></i> พิมพ์รายงาน
                    </button>
                    <button onclick="exportToExcel()" class="btn btn-success btn-lg">
                        <i class="bi bi-file-excel"></i> Export Excel
                    </button>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // ข้อมูลสำหรับกราฟ
        const monthlyData = <?php echo json_encode($yearlyReport); ?>;
        
        const months = monthlyData.map(d => {
            const thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 
                                'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            return thaiMonths[d.invoice_month];
        });
        
        const totalAmounts = monthlyData.map(d => parseFloat(d.total_amount));
        const paidAmounts = monthlyData.map(d => parseFloat(d.total_paid));
        
        // สร้างกราฟ
        const ctx = document.getElementById('monthlyRevenueChart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'ยอดรวมทั้งหมด',
                        data: totalAmounts,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'ชำระแล้ว',
                        data: paidAmounts,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '฿' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '฿' + context.parsed.y.toLocaleString('th-TH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // Export to Excel (Simple HTML table to Excel)
        function exportToExcel() {
            const table = document.querySelector('table');
            let html = '<html><head><meta charset="utf-8"></head><body>';
            html += '<h2>รายงานรายได้ประจำปี <?php echo toBuddhistYear($currentYear); ?></h2>';
            html += table.outerHTML;
            html += '</body></html>';
            
            const blob = new Blob([html], {
                type: 'application/vnd.ms-excel'
            });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'report_<?php echo $currentYear; ?>.xls';
            link.click();
        }
    </script>
</body>
</html>