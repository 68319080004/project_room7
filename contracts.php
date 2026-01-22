<?php
// ============================================
// ไฟล์: admin/contracts.php
// คำอธิบาย: จัดการสัญญาเช่าทั้งหมด
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Contract.php';

requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
$contract = new Contract($db);

$message = '';
$messageType = '';

// ลบสัญญา Draft
if (isset($_GET['delete'])) {
    if ($contract->delete($_GET['delete'])) {
        $message = 'ลบสัญญาสำเร็จ';
        $messageType = 'success';
    } else {
        $message = 'ไม่สามารถลบสัญญาที่เปิดใช้งานแล้ว';
        $messageType = 'danger';
    }
}

// ดึงสัญญาทั้งหมด
$filters = [];
if (isset($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

$contracts = $contract->getAll($filters);

// นับจำนวนตามสถานะ
$allContracts = $contract->getAll();
$statusCount = [
    'all' => count($allContracts),
    'draft' => 0,
    'active' => 0,
    'expired' => 0
];

foreach ($allContracts as $c) {
    $statusCount[$c['contract_status']]++;
}

// ตรวจสอบสัญญาที่ใกล้หมดอายุ
$expiringSoon = $contract->getExpiringSoon(30);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสัญญาเช่า - ระบบจัดการหอพัก</title>
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
                        <i class="bi bi-file-earmark-text"></i> จัดการสัญญาเช่า
                    </h1>
                    <a href="contracts_create.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> สร้างสัญญาใหม่
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- สถิติ -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h6 class="card-title mb-0">สัญญาทั้งหมด</h6>
                                <h2 class="mb-0"><?php echo $statusCount['all']; ?></h2>
                                <small>รายการ</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h6 class="card-title mb-0">ฉบับร่าง</h6>
                                <h2 class="mb-0"><?php echo $statusCount['draft']; ?></h2>
                                <small>Draft</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h6 class="card-title mb-0">ใช้งานอยู่</h6>
                                <h2 class="mb-0"><?php echo $statusCount['active']; ?></h2>
                                <small>Active</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h6 class="card-title mb-0">ใกล้หมดอายุ</h6>
                                <h2 class="mb-0"><?php echo count($expiringSoon); ?></h2>
                                <small>30 วัน</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- แจ้งเตือนสัญญาใกล้หมดอายุ -->
                <?php if (count($expiringSoon) > 0): ?>
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">
                            <i class="bi bi-exclamation-triangle"></i> สัญญาใกล้หมดอายุ
                        </h5>
                        <p class="mb-2">มีสัญญาที่จะหมดอายุภายใน 30 วัน จำนวน <?php echo count($expiringSoon); ?> รายการ:</p>
                        <ul class="mb-0">
                            <?php foreach (array_slice($expiringSoon, 0, 5) as $exp): ?>
                                <li>
                                    <strong>ห้อง <?php echo $exp['room_number']; ?></strong> - 
                                    <?php echo $exp['tenant_name']; ?> 
                                    (หมดอายุ: <?php echo formatThaiDate($exp['end_date']); ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- ตัวกรอง -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <a href="contracts.php" class="btn btn-outline-primary <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">
                                <i class="bi bi-list"></i> ทั้งหมด (<?php echo $statusCount['all']; ?>)
                            </a>
                            <a href="?status=draft" class="btn btn-outline-warning <?php echo isset($_GET['status']) && $_GET['status'] == 'draft' ? 'active' : ''; ?>">
                                <i class="bi bi-file-earmark"></i> ฉบับร่าง (<?php echo $statusCount['draft']; ?>)
                            </a>
                            <a href="?status=active" class="btn btn-outline-success <?php echo isset($_GET['status']) && $_GET['status'] == 'active' ? 'active' : ''; ?>">
                                <i class="bi bi-check-circle"></i> ใช้งานอยู่ (<?php echo $statusCount['active']; ?>)
                            </a>
                            <a href="?status=expired" class="btn btn-outline-danger <?php echo isset($_GET['status']) && $_GET['status'] == 'expired' ? 'active' : ''; ?>">
                                <i class="bi bi-x-circle"></i> หมดอายุ (<?php echo $statusCount['expired']; ?>)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ตารางสัญญา -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-table"></i> รายการสัญญา</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($contracts) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>เลขที่สัญญา</th>
                                            <th>ห้อง</th>
                                            <th>ผู้เช่า</th>
                                            <th>วันที่เริ่ม</th>
                                            <th>วันที่สิ้นสุด</th>
                                            <th>ค่าเช่า</th>
                                            <th>สถานะ</th>
                                            <th width="200">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contracts as $c): ?>
                                            <?php
                                            // คำนวณวันคงเหลือ
                                            $today = new DateTime();
                                            $endDate = new DateTime($c['end_date']);
                                            $daysLeft = $today->diff($endDate)->days;
                                            $isExpiringSoon = ($daysLeft <= 30 && $c['contract_status'] == 'active');
                                            ?>
                                            <tr class="<?php echo $isExpiringSoon ? 'table-warning' : ''; ?>">
                                                <td>
                                                    <strong><?php echo $c['contract_number']; ?></strong>
                                                    <?php if ($isExpiringSoon): ?>
                                                        <br><small class="text-danger">
                                                            <i class="bi bi-exclamation-triangle"></i> 
                                                            เหลือ <?php echo $daysLeft; ?> วัน
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $c['room_number']; ?></span>
                                                </td>
                                                <td><?php echo $c['tenant_name']; ?></td>
                                                <td><?php echo formatThaiDate($c['start_date']); ?></td>
                                                <td><?php echo formatThaiDate($c['end_date']); ?></td>
                                                <td>฿<?php echo formatMoney($c['monthly_rent']); ?></td>
                                                <td>
                                                    <?php if ($c['contract_status'] == 'draft'): ?>
                                                        <span class="badge bg-warning">ฉบับร่าง</span>
                                                    <?php elseif ($c['contract_status'] == 'active'): ?>
                                                        <span class="badge bg-success">ใช้งานอยู่</span>
                                                    <?php elseif ($c['contract_status'] == 'expired'): ?>
                                                        <span class="badge bg-danger">หมดอายุ</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="contract_view.php?id=<?php echo $c['contract_id']; ?>" 
                                                       class="btn btn-sm btn-info" target="_blank">
                                                        <i class="bi bi-eye"></i> ดู
                                                    </a>
                                                    <a href="contract_view.php?id=<?php echo $c['contract_id']; ?>&print=1" 
                                                       class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="bi bi-printer"></i> พิมพ์
                                                    </a>
                                                    <?php if ($c['contract_status'] == 'draft'): ?>
                                                        <a href="?delete=<?php echo $c['contract_id']; ?>" 
                                                           class="btn btn-sm btn-danger"
                                                           onclick="return confirm('ยืนยันการลบสัญญา?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">ยังไม่มีสัญญาในระบบ</p>
                                <a href="contracts_create.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> สร้างสัญญาแรก
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>