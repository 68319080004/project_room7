<?php
// ============================================
// ไฟล์: admin/reports_advanced.php
// คำอธิบาย: รายงานขั้นสูง (ภาษี, P&L, Cash Flow)
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Invoice.php';

requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
$invoice = new Invoice($db);

// เลือกปี
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');

// ======================================
// 1. รายงานภาษี (Tax Report)
// ======================================
$taxQuery = "SELECT 
                MONTH(i.created_at) as month,
                SUM(i.total_amount) as total_revenue,
                SUM(i.paid_amount) as total_paid,
                COUNT(*) as invoice_count
              FROM invoices i
              WHERE YEAR(i.created_at) = :year
              GROUP BY MONTH(i.created_at)
              ORDER BY MONTH(i.created_at)";

$stmt = $db->prepare($taxQuery);
$stmt->execute([':year' => $selectedYear]);
$taxData = $stmt->fetchAll();

$yearlyRevenue = 0;
$yearlyPaid = 0;
foreach ($taxData as $t) {
    $yearlyRevenue += $t['total_revenue'];
    $yearlyPaid += $t['total_paid'];
}

// ======================================
// 2. Profit & Loss Statement
// ======================================

// รายได้
$revenueQuery = "SELECT 
                    'รายได้จากค่าเช่า' as item,
                    SUM(monthly_rent) as amount
                 FROM invoices 
                 WHERE YEAR(created_at) = :year AND payment_status = 'paid'
                 UNION ALL
                 SELECT 
                    'รายได้จากค่าน้ำ' as item,
                    SUM(water_charge) as amount
                 FROM invoices 
                 WHERE YEAR(created_at) = :year AND payment_status = 'paid'
                 UNION ALL
                 SELECT 
                    'รายได้จากค่าไฟ' as item,
                    SUM(electric_charge) as amount
                 FROM invoices 
                 WHERE YEAR(created_at) = :year AND payment_status = 'paid'
                 UNION ALL
                 SELECT 
                    'รายได้จากค่าขยะ' as item,
                    SUM(garbage_fee) as amount
                 FROM invoices 
                 WHERE YEAR(created_at) = :year AND payment_status = 'paid'";

$stmt = $db->prepare($revenueQuery);
$stmt->execute([':year' => $selectedYear]);
$revenueItems = $stmt->fetchAll();

$totalRevenue = array_sum(array_column($revenueItems, 'amount'));

// ค่าใช้จ่าย (สมมติ - ควรมีตารางแยก)
$expenses = [
    ['item' => 'ค่าน้ำประปา (ต้นทาง)', 'amount' => $totalRevenue * 0.15],
    ['item' => 'ค่าไฟฟ้า (ต้นทาง)', 'amount' => $totalRevenue * 0.20],
    ['item' => 'ค่าซ่อมแซม', 'amount' => $totalRevenue * 0.05],
    ['item' => 'ค่าใช้จ่ายอื่นๆ', 'amount' => $totalRevenue * 0.03]
];

$totalExpenses = array_sum(array_column($expenses, 'amount'));
$netProfit = $totalRevenue - $totalExpenses;
$profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;

// ======================================
// 3. Cash Flow Statement
// ======================================
$cashFlowQuery = "SELECT 
                    MONTH(paid_date) as month,
                    SUM(paid_amount) as cash_in
                  FROM invoices
                  WHERE YEAR(paid_date) = :year 
                  AND payment_status = 'paid'
                  GROUP BY MONTH(paid_date)
                  ORDER BY MONTH(paid_date)";

$stmt = $db->prepare($cashFlowQuery);
$stmt->execute([':year' => $selectedYear]);
$cashFlowData = $stmt->fetchAll();

// กราฟรายได้ต่อเดือน
$monthlyData = array_fill(1, 12, 0);
foreach ($taxData as $t) {
    $monthlyData[$t['month']] = $t['total_paid'];
}

// Export Excel
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="report_' . $selectedYear . '.xls"');
    header('Cache-Control: max-age=0');

    echo "<html><meta charset='utf-8'><body>";
    echo "<h1>รายงานการเงิน ปี " . toBuddhistYear($selectedYear) . "</h1>";

    echo "<h2>1. รายงานภาษี</h2>";
    echo "<table border='1'>";
    echo "<tr><th>เดือน</th><th>รายได้รวม</th><th>รับชำระแล้ว</th></tr>";
    foreach ($taxData as $t) {
        echo "<tr>";
        echo "<td>" . getThaiMonth($t['month']) . "</td>";
        echo "<td>" . number_format($t['total_revenue'], 2) . "</td>";
        echo "<td>" . number_format($t['total_paid'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    echo "<h2>2. งบกำไรขาดทุน (P&L)</h2>";
    echo "<table border='1'>";
    echo "<tr><th colspan='2'>รายได้</th></tr>";
    foreach ($revenueItems as $r) {
        echo "<tr><td>{$r['item']}</td><td>" . number_format($r['amount'], 2) . "</td></tr>";
    }
    echo "<tr><th>รวมรายได้</th><th>" . number_format($totalRevenue, 2) . "</th></tr>";
    echo "<tr><th colspan='2'>ค่าใช้จ่าย</th></tr>";
    foreach ($expenses as $e) {
        echo "<tr><td>{$e['item']}</td><td>" . number_format($e['amount'], 2) . "</td></tr>";
    }
    echo "<tr><th>รวมค่าใช้จ่าย</th><th>" . number_format($totalExpenses, 2) . "</th></tr>";
    echo "<tr><th>กำไรสุทธิ</th><th>" . number_format($netProfit, 2) . "</th></tr>";
    echo "</table>";

    echo "</body></html>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานขั้นสูง - ระบบจัดการหอพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #81FBB8 0%, #28C76F 100%);
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-danger: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-dark: linear-gradient(135deg, #434343 0%, #000000 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .card {
                box-shadow: none !important;
                page-break-inside: avoid;
            }
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 25px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: var(--gradient-primary);
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(50%, -50%);
        }

        .page-header h1 {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            margin: 0;
        }

        /* Year Selector Card */
        .year-selector {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .year-selector .form-select {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .year-selector .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        /* Stats Cards */
        .stats-card {
            border: none;
            border-radius: 25px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            opacity: 0.95;
        }

        .stats-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .stats-card .icon-wrapper {
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 120px;
            opacity: 0.15;
            z-index: 1;
        }

        .stats-card .card-content {
            position: relative;
            z-index: 2;
        }

        .stats-card h6 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .stats-card h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stats-card small {
            font-size: 0.95rem;
            opacity: 0.8;
            font-weight: 600;
        }

        .bg-primary-gradient {
            background: var(--gradient-primary);
            color: white;
        }

        .bg-success-gradient {
            background: var(--gradient-success);
            color: white;
        }

        .bg-info-gradient {
            background: var(--gradient-info);
            color: white;
        }

        .bg-warning-gradient {
            background: var(--gradient-warning);
            color: white;
        }

        .bg-danger-gradient {
            background: var(--gradient-danger);
            color: white;
        }

        /* Report Cards */
        .report-card {
            border: none;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            animation: fadeInUp 0.6s ease-out;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .report-card .card-header {
            border: none;
            padding: 1.75rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .report-card .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            opacity: 0.95;
        }

        .report-card .card-header h5 {
            position: relative;
            z-index: 1;
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }

        .report-card .card-header i {
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }

        .report-card .card-body {
            padding: 2rem;
            background: white;
        }

        /* Table Styling */
        .table-custom {
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border-radius: 15px;
        }

        .table-custom thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
            padding: 1.25rem 1rem;
            border: none;
        }

        .table-custom tbody tr {
            transition: all 0.3s ease;
        }

        .table-custom tbody tr:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            transform: scale(1.01);
        }

        .table-custom tbody td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-custom tfoot td {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            font-weight: 700;
            font-size: 1.1rem;
            padding: 1.5rem 1rem;
            border: none;
        }

        /* P&L Tables */
        .pl-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .pl-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .pl-table tr:last-child td {
            border-bottom: none;
        }

        .revenue-section {
            background: linear-gradient(135deg, rgba(129, 251, 184, 0.1) 0%, rgba(40, 199, 111, 0.1) 100%);
            border-radius: 15px;
            padding: 1.5rem;
        }

        .expense-section {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%);
            border-radius: 15px;
            padding: 1.5rem;
        }

        .section-title {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid;
        }

        .section-title.revenue {
            color: #28C76F;
            border-color: #28C76F;
        }

        .section-title.expense {
            color: #f5576c;
            border-color: #f5576c;
        }

        .section-title i {
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }

        /* Net Profit Display */
        .net-profit-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            margin-top: 2rem;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
        }

        .net-profit-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .net-profit-amount {
            color: white;
            font-size: 3.5rem;
            font-weight: 900;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin: 0;
        }

        .profit-margin {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.3rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        /* Buttons */
        .btn-custom {
            border-radius: 15px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .btn-print {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-excel {
            background: var(--gradient-success);
            color: white;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .report-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .report-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .report-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .report-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        /* Money Formatting */
        .money-positive {
            color: #28C76F;
            font-weight: 700;
        }

        .money-negative {
            color: #f5576c;
            font-weight: 700;
        }

        /* Badge */
        .badge-custom {
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Page Header -->
                <div class="page-header no-print">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1>
                                <i class="bi bi-graph-up-arrow"></i> รายงานขั้นสูง
                            </h1>
                            <p class="text-muted mb-0" style="font-size: 1rem; margin-top: 0.5rem;">
                                วิเคราะห์รายงานทางการเงินอย่างละเอียด
                            </p>
                        </div>
                        <div class="btn-group">
                            <button onclick="window.print()" class="btn btn-custom btn-print">
                                <i class="bi bi-printer-fill"></i> พิมพ์รายงาน
                            </button>
                            <a href="?year=<?php echo $selectedYear; ?>&export=excel" class="btn btn-custom btn-excel">
                                <i class="bi bi-file-earmark-excel-fill"></i> Export Excel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Year Selector -->
                <div class="year-selector no-print">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label class="form-label fw-bold" style="margin: 0;">
                                <i class="bi bi-calendar3"></i> เลือกปีที่ต้องการดูรายงาน:
                            </label>
                        </div>
                        <div class="col-auto">
                            <select name="year" class="form-select" onchange="this.form.submit()">
                                <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selectedYear ? 'selected' : ''; ?>>
                                        พ.ศ. <?php echo toBuddhistYear($y); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <!-- Stats Overview -->
                <div class="row mb-4">
                    <div class="col-lg-4 mb-3">
                        <div class="stats-card bg-primary-gradient">
                            <div class="icon-wrapper">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="card-content">
                                <h6>รายได้รวม</h6>
                                <h2>฿<?php echo number_format($yearlyRevenue, 0); ?></h2>
                                <small>ปีงบประมาณ <?php echo toBuddhistYear($selectedYear); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div class="stats-card bg-success-gradient">
                            <div class="icon-wrapper">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div class="card-content">
                                <h6>รับชำระแล้ว</h6>
                                <h2>฿<?php echo number_format($yearlyPaid, 0); ?></h2>
                                <small><?php echo $yearlyRevenue > 0 ? number_format(($yearlyPaid / $yearlyRevenue) * 100, 1) : 0; ?>%
                                    ของรายได้ทั้งหมด</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-3">
                        <div
                            class="stats-card <?php echo $netProfit > 0 ? 'bg-info-gradient' : 'bg-danger-gradient'; ?>">
                            <div class="icon-wrapper">
                                <i class="bi bi-trophy-fill"></i>
                            </div>
                            <div class="card-content">
                                <h6>กำไรสุทธิ</h6>
                                <h2>฿<?php echo number_format($netProfit, 0); ?></h2>
                                <small>อัตรากำไร: <?php echo number_format($profitMargin, 1); ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tax Report -->
                <div class="report-card">
                    <div class="card-header bg-primary-gradient text-white">
                        <h5>
                            <i class="bi bi-receipt-cutoff"></i> รายงานภาษี (Tax Report)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-custom">
                                <thead>
                                    <tr>
                                        <th>เดือน</th>
                                        <th class="text-end">รายได้รวม</th>
                                        <th class="text-end">รับชำระแล้ว</th>
                                        <th class="text-center">จำนวนบิล</th>
                                        <th class="text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($taxData as $t):
                                        $percentage = $t['total_revenue'] > 0 ? ($t['total_paid'] / $t['total_revenue']) * 100 : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <i class="bi bi-calendar-check"></i>
                                                <strong><?php echo getThaiMonth($t['month']); ?></strong>
                                            </td>
                                            <td class="text-end">฿<?php echo formatMoney($t['total_revenue']); ?></td>
                                            <td class="text-end money-positive">
                                                ฿<?php echo formatMoney($t['total_paid']); ?></td>
                                            <td class="text-center">
                                                <span
                                                    class="badge badge-custom bg-primary"><?php echo $t['invoice_count']; ?>
                                                    บิล</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 8px; border-radius: 10px;">
                                                    <div class="progress-bar bg-success"
                                                        style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <small
                                                    class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td><i class="bi bi-calculator"></i> <strong>รวมทั้งปี</strong></td>
                                        <td class="text-end">฿<?php echo formatMoney($yearlyRevenue); ?></td>
                                        <td class="text-end money-positive">฿<?php echo formatMoney($yearlyPaid); ?>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                class="badge badge-custom bg-dark"><?php echo array_sum(array_column($taxData, 'invoice_count')); ?>
                                                บิล</span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- P&L Statement -->
                <div class="report-card">
                    <div class="card-header bg-success-gradient text-white">
                        <h5>
                            <i class="bi bi-bar-chart-line-fill"></i> งบกำไรขาดทุน (Profit & Loss Statement)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Revenue Section -->
                            <div class="col-lg-6 mb-4">
                                <div class="revenue-section">
                                    <div class="section-title revenue">
                                        <function_calls>
                                            <invoke name="artifacts">
                                                <parameter name="command">update</parameter>
                                                <parameter name="id">advanced_reports_enhanced</parameter>
                                                <parameter name="old_str">
                                                    <div class="section-title revenue">
                                                </parameter>
                                                <parameter name="new_str">
                                                    <div class="section-title revenue">
                                                        <i class="bi bi-arrow-up-circle-fill"></i> รายได้
                                                    </div>
                                                    <table class="pl-table w-100">
                                                        <?php foreach ($revenueItems as $r): ?>
                                                            <tr>
                                                                <td><?php echo $r['item']; ?></td>
                                                                <td class="text-end money-positive">
                                                                    ฿<?php echo formatMoney($r['amount']); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                        <tr
                                                            style="background: rgba(40, 199, 111, 0.2); font-weight: 700; font-size: 1.1rem;">
                                                            <td><strong>รวมรายได้ทั้งหมด</strong></td>
                                                            <td class="text-end money-positive">
                                                                <strong>฿<?php echo formatMoney($totalRevenue); ?></strong>
                                                            </td>
                                                        </tr>
                                                    </table>
                                    </div>
                                </div>
                                <!-- Expense Section -->
                                <div class="col-lg-6 mb-4">
                                    <div class="expense-section">
                                        <div class="section-title expense">
                                            <i class="bi bi-arrow-down-circle-fill"></i> ค่าใช้จ่าย
                                        </div>
                                        <table class="pl-table w-100">
                                            <?php foreach ($expenses as $e): ?>
                                                <tr>
                                                    <td><?php echo $e['item']; ?></td>
                                                    <td class="text-end money-negative">
                                                        ฿<?php echo formatMoney($e['amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr
                                                style="background: rgba(245, 87, 108, 0.2); font-weight: 700; font-size: 1.1rem;">
                                                <td><strong>รวมค่าใช้จ่ายทั้งหมด</strong></td>
                                                <td class="text-end money-negative">
                                                    <strong>฿<?php echo formatMoney($totalExpenses); ?></strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Net Profit -->
                            <div class="net-profit-container">
                                <div class="net-profit-label">
                                    <i class="bi bi-star-fill"></i> กำไรสุทธิ (Net Profit)
                                </div>
                                <div class="net-profit-amount">
                                    ฿<?php echo formatMoney($netProfit); ?>
                                </div>
                                <div class="profit-margin">
                                    <i class="bi bi-percent"></i> อัตรากำไร:
                                    <?php echo number_format($profitMargin, 2); ?>%
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Flow Chart -->
                    <div class="report-card">
                        <div class="card-header bg-info-gradient text-white">
                            <h5>
                                <i class="bi bi-cash-coin"></i> กระแสเงินสด (Cash Flow Statement)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="cashFlowChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Comparison Chart -->
                    <div class="report-card">
                        <div class="card-header bg-warning-gradient text-white">
                            <h5>
                                <i class="bi bi-bar-chart-fill"></i> กราฟเปรียบเทียบรายได้รายเดือน
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart" height="80"></canvas>
                            </div>
                        </div>
                    </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        const revenueGradient = revenueCtx.getContext('2d').createLinearGradient(0, 0, 0, 400);
        revenueGradient.addColorStop(0, 'rgba(102, 126, 234, 0.8)');
        revenueGradient.addColorStop(1, 'rgba(118, 75, 162, 0.3)');

        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                datasets: [{
                    label: 'รายได้ (บาท)',
                    data: <?php echo json_encode(array_values($monthlyData)); ?>,
                    backgroundColor: revenueGradient,
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 3,
                    borderRadius: 10,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 15,
                        titleFont: {
                            size: 16,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 14
                        },
                        borderColor: '#667eea',
                        borderWidth: 2,
                        callbacks: {
                            label: function (context) {
                                return 'รายได้: ฿' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            callback: function (value) {
                                return '฿' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });

        // Cash Flow Chart
        const cashFlowCtx = document.getElementById('cashFlowChart');
        const cashFlowGradient = cashFlowCtx.getContext('2d').createLinearGradient(0, 0, 0, 400);
        cashFlowGradient.addColorStop(0, 'rgba(79, 172, 254, 0.6)');
        cashFlowGradient.addColorStop(1, 'rgba(0, 242, 254, 0.1)');

        new Chart(cashFlowCtx, {
            type: 'line',
            data: {
                labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                datasets: [{
                    label: 'เงินสดรับเข้า',
                    data: <?php echo json_encode(array_values($monthlyData)); ?>,
                    borderColor: 'rgb(79, 172, 254)',
                    backgroundColor: cashFlowGradient,
                    borderWidth: 4,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 6,
                    pointHoverRadius: 10,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgb(79, 172, 254)',
                    pointBorderWidth: 3,
                    pointHoverBackgroundColor: 'rgb(79, 172, 254)',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 15,
                        titleFont: {
                            size: 16,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 14
                        },
                        borderColor: '#4facfe',
                        borderWidth: 2,
                        callbacks: {
                            label: function (context) {
                                return 'เงินสด: ฿' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            callback: function (value) {
                                return '฿' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });

        // Smooth scroll animation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>
</parameter>