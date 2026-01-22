<?php
// ============================================
// ไฟล์: admin/invoice_create_manual.php
// คำอธิบาย: สร้างบิลใหม่แบบ Manual ครบถ้วน 100%
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Room.php';

requireRole(['admin', 'owner']);

$database = new Database();
$db = $database->getConnection();
$room = new Room($db);

$message = '';
$messageType = '';

// สร้างบิล Manual
if (isset($_POST['create_manual_invoice'])) {
    $room_id = $_POST['room_id'];
    $invoice_month = $_POST['invoice_month'];
    $invoice_year = $_POST['invoice_year'];
    
    // ดึงข้อมูลผู้เช่า
    $stmt = $db->prepare("SELECT tenant_id FROM tenants WHERE room_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$room_id]);
    $tenantData = $stmt->fetch();
    
    if (!$tenantData) {
        $message = "ห้องนี้ไม่มีผู้เช่า";
        $messageType = 'danger';
    } else {
        // สร้างเลขที่บิล
        $invoice_number = 'INV' . $invoice_year . str_pad($invoice_month, 2, '0', STR_PAD_LEFT) . str_pad($room_id, 3, '0', STR_PAD_LEFT);
        
        // คำนวณวันครบกำหนด
        $due_date = date('Y-m-d', strtotime("+1 month", strtotime("$invoice_year-$invoice_month-01")));
        
        // คำนวณยอดรวม
        $total = $_POST['monthly_rent'] + $_POST['water_charge'] + $_POST['electric_charge'] 
               + $_POST['garbage_fee'] + $_POST['previous_balance'] + $_POST['other_charges'] 
               - $_POST['discount'];
        
        // บันทึกบิล
        $sql = "INSERT INTO invoices (
                    invoice_number, room_id, tenant_id, invoice_month, invoice_year,
                    monthly_rent, water_charge, electric_charge, garbage_fee,
                    previous_balance, discount, other_charges, other_charges_note,
                    total_amount, payment_status, due_date, created_by
                ) VALUES (
                    :invoice_number, :room_id, :tenant_id, :invoice_month, :invoice_year,
                    :monthly_rent, :water_charge, :electric_charge, :garbage_fee,
                    :previous_balance, :discount, :other_charges, :other_charges_note,
                    :total_amount, 'pending', :due_date, :created_by
                )
                ON DUPLICATE KEY UPDATE
                    monthly_rent = :monthly_rent2,
                    water_charge = :water_charge2,
                    electric_charge = :electric_charge2,
                    garbage_fee = :garbage_fee2,
                    previous_balance = :previous_balance2,
                    discount = :discount2,
                    other_charges = :other_charges2,
                    other_charges_note = :other_charges_note2,
                    total_amount = :total_amount2";
        
        $stmt = $db->prepare($sql);
        
        try {
            $stmt->execute([
                ':invoice_number' => $invoice_number,
                ':room_id' => $room_id,
                ':tenant_id' => $tenantData['tenant_id'],
                ':invoice_month' => $invoice_month,
                ':invoice_year' => $invoice_year,
                ':monthly_rent' => $_POST['monthly_rent'],
                ':water_charge' => $_POST['water_charge'],
                ':electric_charge' => $_POST['electric_charge'],
                ':garbage_fee' => $_POST['garbage_fee'],
                ':previous_balance' => $_POST['previous_balance'],
                ':discount' => $_POST['discount'],
                ':other_charges' => $_POST['other_charges'],
                ':other_charges_note' => $_POST['other_charges_note'],
                ':total_amount' => $total,
                ':due_date' => $due_date,
                ':created_by' => $_SESSION['user_id'],
                // สำหรับ ON DUPLICATE KEY UPDATE
                ':monthly_rent2' => $_POST['monthly_rent'],
                ':water_charge2' => $_POST['water_charge'],
                ':electric_charge2' => $_POST['electric_charge'],
                ':garbage_fee2' => $_POST['garbage_fee'],
                ':previous_balance2' => $_POST['previous_balance'],
                ':discount2' => $_POST['discount'],
                ':other_charges2' => $_POST['other_charges'],
                ':other_charges_note2' => $_POST['other_charges_note'],
                ':total_amount2' => $total
            ]);
            
            $message = "สร้างบิลสำเร็จ! เลขที่บิล: <strong>{$invoice_number}</strong>";
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// ดึงห้องที่มีผู้เช่า
$occupiedRooms = $room->getAll('occupied');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างบิล Manual - ระบบจัดการหอพัก</title>
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
                        <i class="bi bi-file-earmark-plus"></i> สร้างบิลแบบ Manual
                    </h1>
                    <a href="invoices.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> กลับ
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-pencil-fill"></i> กรอกข้อมูลบิลใหม่
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>หมายเหตุ:</strong> ใช้เมนูนี้เมื่อต้องการสร้างบิลด้วยตนเอง โดยไม่ผ่านระบบมิเตอร์
                                </div>

                                <form method="POST" id="manualForm">
                                    <!-- เลือกห้องและเดือน -->
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label">เลือกห้อง <span class="text-danger">*</span></label>
                                            <select class="form-select" name="room_id" id="room_select" required>
                                                <option value="">-- เลือกห้อง --</option>
                                                <?php foreach ($occupiedRooms as $r): ?>
                                                    <option value="<?php echo $r['room_id']; ?>" 
                                                            data-rent="<?php echo $r['monthly_rent']; ?>"
                                                            data-tenant="<?php echo $r['tenant_name']; ?>">
                                                        ห้อง <?php echo $r['room_number']; ?> - <?php echo $r['tenant_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">เดือน <span class="text-danger">*</span></label>
                                            <select class="form-select" name="invoice_month" required>
                                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                                        <?php echo getThaiMonth($m); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">ปี <span class="text-danger">*</span></label>
                                            <select class="form-select" name="invoice_year" required>
                                                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                                    <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>>
                                                        <?php echo toBuddhistYear($y); ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <hr>

                                    <h5 class="mb-3 text-primary">
                                        <i class="bi bi-calculator"></i> รายละเอียดค่าใช้จ่าย
                                    </h5>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-house"></i> ค่าเช่าห้อง (บาท) <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-md-8">
                                            <input type="number" step="0.01" class="form-control" name="monthly_rent" 
                                                   id="monthly_rent" value="0" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-droplet-fill text-info"></i> ค่าน้ำ (บาท) <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-md-8">
                                            <input type="number" step="0.01" class="form-control calc-field" 
                                                   name="water_charge" value="0" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-lightning-fill text-warning"></i> ค่าไฟ (บาท) <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-md-8">
                                            <input type="number" step="0.01" class="form-control calc-field" 
                                                   name="electric_charge" value="0" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-trash"></i> ค่าขยะ (บาท) <span class="text-danger">*</span>
                                        </label>
                                        <div class="col-md-8">
                                            <input type="number" step="0.01" class="form-control calc-field" 
                                                   name="garbage_fee" value="50" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-exclamation-circle text-danger"></i> ค่าค้างชำระ (บาท)
                                        </label>
                                        <div class="col-md-8">
                                            <input type="number" step="0.01" class="form-control calc-field" 
                                                   name="previous_balance" value="0" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-tag-fill text-success"></i> ส่วนลด (บาท)
                                        </label>
                                        <div class="col-md-8">
                                            <input type="number" step="0.01" class="form-control calc-field" 
                                                   name="discount" value="0" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-plus-circle"></i> ค่าใช้จ่ายอื่นๆ (บาท)
                                        </label>
                                        <div class="col-md-8">
                                            <input type="number" step="0.01" class="form-control calc-field" 
                                                   name="other_charges" value="0" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <label class="col-md-4 col-form-label">
                                            <i class="bi bi-chat-left-text"></i> หมายเหตุ
                                        </label>
                                        <div class="col-md-8">
                                            <textarea class="form-control" name="other_charges_note" rows="3" 
                                                      placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row mb-4">
                                        <label class="col-md-4 col-form-label">
                                            <strong class="fs-5">ยอดรวมทั้งสิ้น:</strong>
                                        </label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control form-control-lg fw-bold text-primary fs-4" 
                                                   id="total_display" readonly style="background: #e7f3ff;">
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" name="create_manual_invoice" class="btn btn-success btn-lg">
                                            <i class="bi bi-check-circle"></i> สร้างบิล
                                        </button>
                                        <button type="reset" class="btn btn-secondary">
                                            <i class="bi bi-arrow-counterclockwise"></i> รีเซ็ต
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card bg-light mb-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-lightbulb"></i> คำแนะนำ</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">💡 เมื่อไหร่ควรใช้?</h6>
                                <ul class="small">
                                    <li>เมื่อไม่มีข้อมูลมิเตอร์</li>
                                    <li>มีค่าใช้จ่ายพิเศษ</li>
                                    <li>ต้องการคิดแบบเหมา</li>
                                    <li>แก้ไขบิลที่มีปัญหา</li>
                                </ul>

                                <h6 class="text-success mt-3">✅ ข้อดี:</h6>
                                <ul class="small">
                                    <li>ยืดหยุ่น ปรับได้ทุกอย่าง</li>
                                    <li>ไม่ต้องบันทึกมิเตอร์</li>
                                    <li>เหมาะกับกรณีพิเศษ</li>
                                </ul>

                                <h6 class="text-warning mt-3">⚠️ ข้อควรระวัง:</h6>
                                <ul class="small">
                                    <li>ต้องคำนวณเองให้ถูกต้อง</li>
                                    <li>ระบุหมายเหตุให้ชัดเจน</li>
                                    <li>ตรวจสอบก่อนบันทึก</li>
                                </ul>
                            </div>
                        </div>

                        <div class="card bg-warning bg-opacity-10">
                            <div class="card-body">
                                <h6 class="text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> หมายเหตุสำคัญ
                                </h6>
                                <p class="small mb-0">
                                    บิลที่สร้างด้วยโหมดนี้จะไม่มีข้อมูลมิเตอร์ ควรใช้เฉพาะกรณีจำเป็นเท่านั้น
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('room_select').addEventListener('change', function() {
            const rent = this.options[this.selectedIndex].getAttribute('data-rent');
            if (rent) {
                document.getElementById('monthly_rent').value = rent;
                calculateTotal();
            }
        });

        function calculateTotal() {
            const rent = parseFloat(document.querySelector('[name="monthly_rent"]').value) || 0;
            const water = parseFloat(document.querySelector('[name="water_charge"]').value) || 0;
            const electric = parseFloat(document.querySelector('[name="electric_charge"]').value) || 0;
            const garbage = parseFloat(document.querySelector('[name="garbage_fee"]').value) || 0;
            const previous = parseFloat(document.querySelector('[name="previous_balance"]').value) || 0;
            const discount = parseFloat(document.querySelector('[name="discount"]').value) || 0;
            const other = parseFloat(document.querySelector('[name="other_charges"]').value) || 0;
            
            const total = rent + water + electric + garbage + previous + other - discount;
            
            document.getElementById('total_display').value = '฿' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        document.querySelectorAll('.calc-field, #monthly_rent').forEach(input => {
            input.addEventListener('input', calculateTotal);
        });

        calculateTotal();
    </script>
</body>
</html>
