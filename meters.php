<?php
// ============================================
// ไฟล์: admin/meters.php
// คำอธิบาย: หน้าบันทึกมิเตอร์น้ำ-ไฟ
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Room.php';
require_once __DIR__ . '/../models/Meter.php';


requireRole(roles: ['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();

$room = new Room($db);
$meter = new Meter($db);

// เดือนและปีปัจจุบัน
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$message = '';
$messageType = '';

// บันทึกมิเตอร์
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_meter'])) {
    $data = [
        'room_id' => $_POST['room_id'],
        'meter_month' => $_POST['meter_month'],
        'meter_year' => $_POST['meter_year'],
        'water_previous' => $_POST['water_previous'],
        'water_current' => $_POST['water_current'],
        'electric_previous' => $_POST['electric_previous'],
        'electric_current' => $_POST['electric_current'],
        'recorded_by' => $_SESSION['user_id']
    ];
    
    if ($meter->create($data)) {
        $message = 'บันทึกมิเตอร์สำเร็จ!';
        $messageType = 'success';
    } else {
        $message = 'เกิดข้อผิดพลาดในการบันทึก';
        $messageType = 'danger';
    }
}

// ดึงห้องที่มีผู้เช่า
$rooms = $room->getAll('occupied');

// ดึงมิเตอร์ในเดือนที่เลือก
$meters = $meter->getAllByMonth($currentMonth, $currentYear);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกมิเตอร์ - ระบบจัดการหอพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .meter-card {
            border-left: 4px solid #0d6efd;
        }
        .meter-input {
            font-size: 1.1rem;
            font-weight: bold;
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
                        <i class="bi bi-speedometer"></i> บันทึกมิเตอร์น้ำ-ไฟ
                    </h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- เลือกเดือน/ปี -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">เลือกเดือน</label>
                                <select name="month" class="form-select">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                            <?php echo getThaiMonth($m); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เลือกปี</label>
                                <select name="year" class="form-select">
                                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                            <?php echo toBuddhistYear($y); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> แสดงข้อมูล
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- แสดงห้องทั้งหมด -->
                <div class="row">
                    <?php foreach ($rooms as $r): ?>
                        <?php
                        // ดึงมิเตอร์ล่าสุด
                        $latestMeter = $meter->getLatestByRoom($r['room_id']);
                        
                        // ดึงมิเตอร์เดือนนี้
                        $currentMeter = $meter->getByMonthYear($r['room_id'], $currentMonth, $currentYear);
                        
                        // มิเตอร์เริ่มต้น
                        $waterPrev = $currentMeter ? $currentMeter['water_previous'] : ($latestMeter ? $latestMeter['water_current'] : 0);
                        $electricPrev = $currentMeter ? $currentMeter['electric_previous'] : ($latestMeter ? $latestMeter['electric_current'] : 0);
                        $waterCurrent = $currentMeter ? $currentMeter['water_current'] : '';
                        $electricCurrent = $currentMeter ? $currentMeter['electric_current'] : '';
                        ?>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card meter-card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-door-open"></i> ห้อง <?php echo $r['room_number']; ?>
                                    </h5>
                                    <small><?php echo $r['tenant_name'] ?: 'ไม่มีผู้เช่า'; ?></small>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="needs-validation" novalidate>
                                        <input type="hidden" name="room_id" value="<?php echo $r['room_id']; ?>">
                                        <input type="hidden" name="meter_month" value="<?php echo $currentMonth; ?>">
                                        <input type="hidden" name="meter_year" value="<?php echo $currentYear; ?>">
                                        
                                        <!-- มิเตอร์น้ำ -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="bi bi-droplet-fill text-primary"></i> มิเตอร์น้ำ
                                            </label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <label class="form-label small">ต้นเดือน</label>
                                                    <input type="number" step="0.01" class="form-control meter-input" 
                                                           name="water_previous" value="<?php echo $waterPrev; ?>" 
                                                           readonly style="background: #f0f0f0;">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small text-danger">ปลายเดือน *</label>
                                                    <input type="number" step="0.01" class="form-control meter-input" 
                                                           name="water_current" value="<?php echo $waterCurrent; ?>" 
                                                           required min="<?php echo $waterPrev; ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- มิเตอร์ไฟ -->
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">
                                                <i class="bi bi-lightning-fill text-warning"></i> มิเตอร์ไฟ
                                            </label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <label class="form-label small">ต้นเดือน</label>
                                                    <input type="number" step="0.01" class="form-control meter-input" 
                                                           name="electric_previous" value="<?php echo $electricPrev; ?>" 
                                                           readonly style="background: #f0f0f0;">
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small text-danger">ปลายเดือน *</label>
                                                    <input type="number" step="0.01" class="form-control meter-input" 
                                                           name="electric_current" value="<?php echo $electricCurrent; ?>" 
                                                           required min="<?php echo $electricPrev; ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($currentMeter): ?>
                                            <div class="alert alert-info small mb-3">
                                                <i class="bi bi-info-circle"></i> 
                                                ใช้น้ำ: <strong><?php echo formatMoney($currentMeter['water_usage']); ?> ยูนิต</strong> | 
                                                ใช้ไฟ: <strong><?php echo formatMoney($currentMeter['electric_usage']); ?> ยูนิต</strong>
                                            </div>
                                        <?php endif; ?>

                                        <button type="submit" name="save_meter" class="btn btn-success w-100">
                                            <i class="bi bi-save"></i> บันทึก
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($rooms) == 0): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> ไม่มีห้องที่มีผู้เช่าในขณะนี้
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>