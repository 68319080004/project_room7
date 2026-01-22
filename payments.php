<?php
// ============================================
// ไฟล์: admin/payments.php
// คำอธิบาย: ตรวจสอบและอนุมัติการชำระเงิน
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Payment.php';

requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
$payment = new Payment($db);

$message = '';
$messageType = '';

// อนุมัติการชำระเงิน
if (isset($_POST['approve'])) {
    if ($payment->approve($_POST['payment_id'], $_SESSION['user_id'])) {
        $message = 'อนุมัติการชำระเงินสำเร็จ!';
        $messageType = 'success';
    } else {
        $message = 'เกิดข้อผิดพลาด';
        $messageType = 'danger';
    }
}

// ปฏิเสธการชำระเงิน
if (isset($_POST['reject'])) {
    if ($payment->reject($_POST['payment_id'], $_SESSION['user_id'])) {
        $message = 'ปฏิเสธการชำระเงินแล้ว';
        $messageType = 'warning';
    } else {
        $message = 'เกิดข้อผิดพลาด';
        $messageType = 'danger';
    }
}

// ดึงรายการที่รอตรวจสอบ
$pendingPayments = $payment->getAll('pending');

// ดึงรายการทั้งหมด
$allPayments = $payment->getAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบการชำระเงิน - ระบบจัดการหอพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .slip-preview {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }
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
                        <i class="bi bi-credit-card"></i> ตรวจสอบการชำระเงิน
                    </h1>
                    <div>
                        <span class="badge bg-warning fs-6">รอตรวจสอบ: <?php echo count($pendingPayments); ?> รายการ</span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- รายการรอตรวจสอบ -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> รอตรวจสอบ</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($pendingPayments) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>วันที่แจ้ง</th>
                                            <th>ห้อง</th>
                                            <th>ผู้เช่า</th>
                                            <th>เลขที่บิล</th>
                                            <th>จำนวนเงิน</th>
                                            <th>วันที่โอน</th>
                                            <th>ธนาคาร</th>
                                            <th>สลิป</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingPayments as $p): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                                                <td><strong><?php echo $p['room_number']; ?></strong></td>
                                                <td><?php echo $p['tenant_name']; ?></td>
                                                <td><small><?php echo $p['invoice_number']; ?></small></td>
                                                <td class="fw-bold text-primary">฿<?php echo formatMoney($p['payment_amount']); ?></td>
                                                <td><?php echo formatThaiDate($p['payment_date']); ?></td>
                                                <td><?php echo $p['bank_name'] ?: '-'; ?></td>
                                                <td>
                                                    <?php if ($p['payment_slip']): ?>
                                                        <button class="btn btn-sm btn-info" 
                                                                onclick="showSlip('<?php echo $p['payment_slip']; ?>')">
                                                            <i class="bi bi-image"></i> ดู
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>">
                                                        <button type="submit" name="approve" class="btn btn-sm btn-success"
                                                                onclick="return confirm('ยืนยันการอนุมัติ?')">
                                                            <i class="bi bi-check-circle"></i> อนุมัติ
                                                        </button>
                                                        <button type="submit" name="reject" class="btn btn-sm btn-danger"
                                                                onclick="return confirm('ยืนยันการปฏิเสธ?')">
                                                            <i class="bi bi-x-circle"></i> ปฏิเสธ
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                <p>ไม่มีรายการรอตรวจสอบ</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ประวัติการชำระเงินทั้งหมด -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list"></i> ประวัติการชำระเงินทั้งหมด</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>วันที่</th>
                                        <th>ห้อง</th>
                                        <th>ผู้เช่า</th>
                                        <th>จำนวนเงิน</th>
                                        <th>วิธีชำระ</th>
                                        <th>สถานะ</th>
                                        <th>ผู้ตรวจสอบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allPayments as $p): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($p['payment_date'])); ?></td>
                                            <td><strong><?php echo $p['room_number']; ?></strong></td>
                                            <td><?php echo $p['tenant_name']; ?></td>
                                            <td>฿<?php echo formatMoney($p['payment_amount']); ?></td>
                                            <td>
                                                <?php
                                                $methods = [
                                                    'cash' => 'เงินสด',
                                                    'transfer' => 'โอนเงิน',
                                                    'qr' => 'QR Code',
                                                    'other' => 'อื่นๆ'
                                                ];
                                                echo $methods[$p['payment_method']] ?? $p['payment_method'];
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($p['payment_status'] == 'approved'): ?>
                                                    <span class="badge bg-success">อนุมัติแล้ว</span>
                                                <?php elseif ($p['payment_status'] == 'rejected'): ?>
                                                    <span class="badge bg-danger">ปฏิเสธ</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">รอตรวจสอบ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $p['verified_at'] ? date('d/m/Y H:i', strtotime($p['verified_at'])) : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal แสดงสลิป -->
    <div class="modal fade" id="slipModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-image"></i> สลิปการโอนเงิน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="slipImage" src="" class="slip-preview">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSlip(filename) {
            document.getElementById('slipImage').src = '../uploads/slips/' + filename;
            new bootstrap.Modal(document.getElementById('slipModal')).show();
        }
    </script>
</body>
</html>