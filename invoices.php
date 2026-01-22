<?php
// ============================================
// ไฟล์: admin/invoices.php
// คำอธิบาย: จัดการใบเสร็จ/บิล (ฉบับสมบูรณ์ พร้อมส่ง Email)
// ============================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';
require_once '../models/Invoice.php';
require_once '../models/Room.php';

requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
$invoice = new Invoice($db);
$room = new Room($db);

$message = '';
$messageType = '';

// เดือนและปีปัจจุบัน
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// สร้างใบเสร็จทั้งหมดในเดือน
if (isset($_POST['generate_all'])) {
    $rooms = $db->query("SELECT room_id FROM rooms WHERE room_status = 'occupied'")->fetchAll();
    
    $success = 0;
    $failed = 0;
    
    foreach ($rooms as $r) {
        try {
            if ($invoice->generateInvoice($r['room_id'], $currentMonth, $currentYear)) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
    }
    
    if ($success > 0) {
        $message = "สร้างใบเสร็จสำเร็จ {$success} รายการ";
        if ($failed > 0) {
            $message .= " (ไม่สำเร็จ {$failed} รายการ)";
        }
        $messageType = 'success';
    } else {
        $message = "ไม่สามารถสร้างใบเสร็จได้ กรุณาตรวจสอบว่าได้บันทึกมิเตอร์แล้ว";
        $messageType = 'warning';
    }
}

// อัพเดทสถานะเป็น "ชำระแล้ว"
if (isset($_POST['mark_as_paid'])) {
    $invoice_id = $_POST['invoice_id'];
    $paid_amount = $_POST['paid_amount'];
    $paid_date = $_POST['paid_date'];
    
    if ($invoice->updatePaymentStatus($invoice_id, 'paid', $paid_amount, $paid_date)) {
        $message = "อัพเดทสถานะเป็น 'ชำระแล้ว' สำเร็จ!";
        $messageType = 'success';
    } else {
        $message = "เกิดข้อผิดพลาด";
        $messageType = 'danger';
    }
}

// ดึงใบเสร็จ
$invoices = $invoice->getAll([
    'month' => $currentMonth,
    'year' => $currentYear
]);

// คำนวณสรุปยอด
$summary = $invoice->getMonthlySummary($currentMonth, $currentYear);

// ดึงห้องที่มีผู้เช่า
$occupiedRooms = $room->getAll('occupied');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการใบเสร็จ - ระบบจัดการหอพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .invoice-badge { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-receipt"></i> จัดการใบเสร็จ/บิล
                    </h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <i class="bi bi-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- เลือกเดือน/ปี และสร้างบิล -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> เลือกเดือน/ปี และสร้างบิล</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">เลือกเดือน</label>
                                <select name="month" class="form-select">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                            <?php echo getThaiMonth($m); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เลือกปี</label>
                                <select name="year" class="form-select">
                                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                            <?php echo toBuddhistYear($y); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> แสดงข้อมูล
                                </button>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#generateAllModal">
                                    <i class="bi bi-file-earmark-plus"></i> สร้างบิลทั้งหมด
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- สรุปยอดรวม -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h6 class="card-title mb-0">จำนวนบิล</h6>
                                <h2 class="mb-0"><?php echo $summary['total_invoices'] ?? 0; ?></h2>
                                <small>รายการ</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title mb-0">ยอดรวมทั้งหมด</h6>
                                <h2 class="mb-0">฿<?php echo number_format($summary['total_amount'] ?? 0); ?></h2>
                                <small>บาท</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title mb-0">ชำระแล้ว</h6>
                                <h2 class="mb-0">฿<?php echo number_format($summary['total_paid'] ?? 0); ?></h2>
                                <small>บาท</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6 class="card-title mb-0">ค้างชำระ</h6>
                                <h2 class="mb-0">฿<?php echo number_format($summary['total_unpaid'] ?? 0); ?></h2>
                                <small>บาท</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ตารางใบเสร็จ -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul"></i> 
                            รายการใบเสร็จ - <?php echo getThaiMonth($currentMonth) . ' ' . toBuddhistYear($currentYear); ?>
                            <span class="badge bg-light text-dark ms-2"><?php echo count($invoices); ?> รายการ</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($invoices) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="150">เลขที่บิล</th>
                                            <th width="80">ห้อง</th>
                                            <th>ผู้เช่า</th>
                                            <th class="text-end">ค่าเช่า</th>
                                            <th class="text-end">ค่าน้ำ</th>
                                            <th class="text-end">ค่าไฟ</th>
                                            <th class="text-end">รวมทั้งหมด</th>
                                            <th width="120">สถานะ</th>
                                            <th width="200">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $inv): ?>
                                            <tr>
                                                <td><small class="text-muted"><?php echo $inv['invoice_number']; ?></small></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $inv['room_number']; ?></span>
                                                </td>
                                                <td><?php echo $inv['tenant_name']; ?></td>
                                                <td class="text-end"><?php echo formatMoney($inv['monthly_rent']); ?></td>
                                                <td class="text-end text-info"><?php echo formatMoney($inv['water_charge']); ?></td>
                                                <td class="text-end text-warning"><?php echo formatMoney($inv['electric_charge']); ?></td>
                                                <td class="text-end fw-bold text-primary fs-6">
                                                    ฿<?php echo formatMoney($inv['total_amount']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php echo getPaymentStatusBadge($inv['payment_status']); ?>
                                                </td>
                                                <td>
                                                    <!-- ปุ่มดู -->
                                                    <a href="invoice_view.php?id=<?php echo $inv['invoice_id']; ?>" 
                                                       class="btn btn-sm btn-info" target="_blank" title="ดูบิล">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <!-- ปุ่ม PDF -->
                                                    <a href="invoice_pdf.php?id=<?php echo $inv['invoice_id']; ?>" 
                                                       class="btn btn-sm btn-danger" target="_blank" title="ดาวน์โหลด PDF">
                                                        <i class="bi bi-file-pdf"></i>
                                                    </a>
                                                    
                                                    <!-- ปุ่มส่ง Email -->
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick="sendEmail(<?php echo $inv['invoice_id']; ?>, '<?php echo $inv['tenant_name']; ?>')"
                                                            title="ส่งทาง Email">
                                                        <i class="bi bi-envelope"></i>
                                                    </button>
                                                    
                                                    <!-- ปุ่มชำระแล้ว -->
                                                    <?php if ($inv['payment_status'] == 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" 
                                                                onclick="markAsPaid(<?php echo $inv['invoice_id']; ?>, <?php echo $inv['total_amount']; ?>)"
                                                                title="ชำระแล้ว">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="3" class="text-end">รวมทั้งหมด:</th>
                                            <th class="text-end">฿<?php echo formatMoney(array_sum(array_column($invoices, 'monthly_rent'))); ?></th>
                                            <th class="text-end text-info">฿<?php echo formatMoney(array_sum(array_column($invoices, 'water_charge'))); ?></th>
                                            <th class="text-end text-warning">฿<?php echo formatMoney(array_sum(array_column($invoices, 'electric_charge'))); ?></th>
                                            <th class="text-end fw-bold text-primary">฿<?php echo formatMoney(array_sum(array_column($invoices, 'total_amount'))); ?></th>
                                            <th colspan="2"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                                <h4 class="mt-3 text-muted">ยังไม่มีใบเสร็จในเดือนนี้</h4>
                                <p class="text-muted">กรุณาบันทึกมิเตอร์ก่อน แล้วกดปุ่ม "สร้างบิลทั้งหมด"</p>
                                <button type="button" class="btn btn-success btn-lg mt-3" data-bs-toggle="modal" data-bs-target="#generateAllModal">
                                    <i class="bi bi-file-earmark-plus"></i> สร้างบิลทั้งหมด
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal ยืนยันสร้างบิลทั้งหมด -->
    <div class="modal fade" id="generateAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-file-earmark-plus"></i> สร้างบิลทั้งหมด
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>คำเตือน:</strong> ก่อนสร้างบิล กรุณาตรวจสอบว่าได้บันทึกมิเตอร์น้ำ-ไฟของทุกห้องแล้ว
                        </div>
                        <p>ต้องการสร้างใบเสร็จสำหรับห้องที่มีผู้เช่าทั้งหมด?</p>
                        <p><strong>เดือน:</strong> <?php echo getThaiMonth($currentMonth) . ' ' . toBuddhistYear($currentYear); ?></p>
                        <p><strong>จำนวนห้อง:</strong> <?php echo count($occupiedRooms); ?> ห้อง</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="generate_all" class="btn btn-success">ยืนยันสร้างบิล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ชำระแล้ว -->
    <div class="modal fade" id="markPaidModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="invoice_id" id="paid_invoice_id">
                    <input type="hidden" name="paid_amount" id="paid_amount">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-check-circle"></i> ยืนยันการชำระเงิน
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>ยืนยันว่าลูกค้าได้ชำระเงินแล้ว?</p>
                        <div class="mb-3">
                            <label class="form-label">จำนวนเงินที่ชำระ (บาท)</label>
                            <input type="number" step="0.01" class="form-control" 
                                   name="paid_amount" id="paid_amount_input" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">วันที่ชำระ</label>
                            <input type="date" class="form-control" name="paid_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="mark_as_paid" class="btn btn-success">ยืนยัน</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ส่ง Email -->
    <div class="modal fade" id="emailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-envelope"></i> ส่งบิลทาง Email</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="email_invoice_id">
                    <div class="mb-3">
                        <label class="form-label">ผู้รับ:</label>
                        <input type="text" class="form-control" id="email_recipient_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">อีเมล <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email_address" 
                               placeholder="example@email.com" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" onclick="confirmSendEmail()">
                        <i class="bi bi-send"></i> ส่ง Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAsPaid(invoiceId, amount) {
            document.getElementById('paid_invoice_id').value = invoiceId;
            document.getElementById('paid_amount_input').value = amount;
            new bootstrap.Modal(document.getElementById('markPaidModal')).show();
        }

        function sendEmail(invoiceId, tenantName) {
            document.getElementById('email_invoice_id').value = invoiceId;
            document.getElementById('email_recipient_name').value = tenantName;
            document.getElementById('email_address').value = '';
            
            new bootstrap.Modal(document.getElementById('emailModal')).show();
        }

        function confirmSendEmail() {
            const invoiceId = document.getElementById('email_invoice_id').value;
            const email = document.getElementById('email_address').value;
            
            if (!email) {
                alert('กรุณากรอกอีเมล');
                return;
            }
            
            // แสดง Loading
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังส่ง...';
            
            fetch('send_email.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'invoice_id=' + invoiceId + '&email=' + email
            })
            .then(response => response.json())
            .then(data => {
                alert(data.success ? '✅ ' + data.message : '❌ ' + data.message);
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('emailModal')).hide();
                }
            })
            .catch(error => {
                alert('❌ เกิดข้อผิดพลาด: ' + error);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
    </script>
</body>
</html>