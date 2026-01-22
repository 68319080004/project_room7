<?php
// ============================================
// ไฟล์: admin/settings.php
// คำอธิบาย: ตั้งค่าระบบ (เฉพาะ Owner)
// ============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/SystemSettings.php';

requireRole(roles: 'owner');

$database = new Database();
$db = $database->getConnection();
$settings = new SystemSettings(db: $db);

$message = '';
$messageType = '';

// บันทึกการตั้งค่า
if (isset($_POST['save_settings'])) {
    $data = [
        'water_rate_per_unit' => $_POST['water_rate_per_unit'],
        'water_minimum_unit' => $_POST['water_minimum_unit'],
        'water_minimum_charge' => $_POST['water_minimum_charge'],
        'electric_rate_per_unit' => $_POST['electric_rate_per_unit'],
        'garbage_fee' => $_POST['garbage_fee'],
        'dormitory_name' => $_POST['dormitory_name'],
        'dormitory_address' => $_POST['dormitory_address'],
        'dormitory_phone' => $_POST['dormitory_phone']
    ];
    
    if ($settings->updateMultiple($data, $_SESSION['user_id'])) {
        $message = 'บันทึกการตั้งค่าสำเร็จ!';
        $messageType = 'success';
    } else {
        $message = 'เกิดข้อผิดพลาด';
        $messageType = 'danger';
    }
}

// ดึงการตั้งค่าปัจจุบัน
$allSettings = $settings->getAll();
$currentSettings = [];
foreach ($allSettings as $s) {
    $currentSettings[$s['setting_key']] = $s['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - ระบบจัดการหอพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --secondary-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --success-gradient: linear-gradient(135deg, #81FBB8 0%, #28C76F 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            border: none;
            padding: 1.5rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            opacity: 0.9;
        }

        .card-header h5 {
            position: relative;
            z-index: 1;
            margin: 0;
        }

        .card-header i {
            font-size: 1.3rem;
            margin-right: 0.5rem;
        }

        .bg-primary-gradient {
            background: var(--primary-gradient) !important;
        }

        .bg-info-gradient {
            background: var(--info-gradient) !important;
        }

        .bg-warning-gradient {
            background: var(--warning-gradient) !important;
        }

        .bg-secondary-gradient {
            background: var(--secondary-gradient) !important;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }

        .form-label i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .btn-save {
            background: var(--success-gradient);
            border: none;
            color: white;
            padding: 1rem 3rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            box-shadow: 0 10px 25px rgba(40, 199, 111, 0.3);
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(40, 199, 111, 0.4);
            color: white;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .page-header h1 {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .text-muted {
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
        }

        .text-muted::before {
            content: "💡";
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .input-group-text {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 12px 0 0 12px;
            font-weight: 600;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: fadeIn 0.5s ease-out;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }

        .info-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            display: inline-block;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="page-header">
                    <h1>
                        <i class="bi bi-gear-fill"></i> ตั้งค่าระบบ
                    </h1>
                    <p class="text-muted mb-0" style="margin-top: 0.5rem;">จัดการการตั้งค่าทั้งหมดของระบบหอพักที่นี่</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row">
                        <!-- ข้อมูลหอพัก -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-primary-gradient text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-building"></i> ข้อมูลหอพัก
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-4">
                                        <label class="form-label">
                                            <i class="bi bi-house-door-fill"></i> ชื่อหอพัก
                                        </label>
                                        <input type="text" class="form-control" name="dormitory_name" 
                                               value="<?php echo $currentSettings['dormitory_name']; ?>" 
                                               placeholder="ระบุชื่อหอพัก" required>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">
                                            <i class="bi bi-geo-alt-fill"></i> ที่อยู่
                                        </label>
                                        <textarea class="form-control" name="dormitory_address" rows="3" 
                                                  placeholder="ระบุที่อยู่หอพัก" required><?php echo $currentSettings['dormitory_address']; ?></textarea>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">
                                            <i class="bi bi-telephone-fill"></i> เบอร์โทรศัพท์
                                        </label>
                                        <input type="text" class="form-control" name="dormitory_phone" 
                                               value="<?php echo $currentSettings['dormitory_phone']; ?>" 
                                               placeholder="เช่น 02-xxx-xxxx" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ค่าน้ำ-ค่าไฟ -->
                        <div class="col-lg-6">
                            <!-- อัตราค่าน้ำ -->
                            <div class="card mb-4">
                                <div class="card-header bg-info-gradient text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-droplet-fill"></i> อัตราค่าน้ำ
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-cash"></i> ค่าน้ำต่อหน่วย (บาท)
                                        </label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control" name="water_rate_per_unit" 
                                                   value="<?php echo $currentSettings['water_rate_per_unit']; ?>" required>
                                            <span class="input-group-text">฿/หน่วย</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-speedometer"></i> จำนวนยูนิตขั้นต่ำ
                                        </label>
                                        <input type="number" step="0.01" class="form-control" name="water_minimum_unit" 
                                               value="<?php echo $currentSettings['water_minimum_unit']; ?>" required>
                                        <small class="text-muted">ถ้าใช้ไม่ถึงจำนวนนี้ จะคิดตามค่าขั้นต่ำ</small>
                                    </div>
                                    <div class="mb-0">
                                        <label class="form-label">
                                            <i class="bi bi-cash-coin"></i> ค่าน้ำขั้นต่ำ (บาท)
                                        </label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control" name="water_minimum_charge" 
                                                   value="<?php echo $currentSettings['water_minimum_charge']; ?>" required>
                                            <span class="input-group-text">฿</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- อัตราค่าไฟ -->
                            <div class="card mb-4">
                                <div class="card-header bg-warning-gradient text-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-lightning-fill"></i> อัตราค่าไฟ
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-0">
                                        <label class="form-label">
                                            <i class="bi bi-cash"></i> ค่าไฟต่อหน่วย (บาท)
                                        </label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control" name="electric_rate_per_unit" 
                                                   value="<?php echo $currentSettings['electric_rate_per_unit']; ?>" required>
                                            <span class="input-group-text">฿/หน่วย</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ค่าขยะ -->
                            <div class="card">
                                <div class="card-header bg-secondary-gradient text-dark">
                                    <h5 class="mb-0">
                                        <i class="bi bi-trash3-fill"></i> ค่าขยะ
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-0">
                                        <label class="form-label">
                                            <i class="bi bi-calendar-month"></i> ค่าขยะรายเดือน (บาท)
                                        </label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control" name="garbage_fee" 
                                                   value="<?php echo $currentSettings['garbage_fee']; ?>" required>
                                            <span class="input-group-text">฿/เดือน</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center my-5">
                        <button type="submit" name="save_settings" class="btn btn-save">
                            <i class="bi bi-check-circle-fill"></i> บันทึกการตั้งค่า
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth scroll and animation effects
        document.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
                this.parentElement.style.transition = 'transform 0.2s ease';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Auto-hide alert after 5 seconds
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    </script>
</body>
</html>