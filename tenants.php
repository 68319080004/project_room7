    <?php
    // ============================================
    // ไฟล์: admin/tenants.php
    // คำอธิบาย: จัดการผู้เช่า (Modern Dashboard Style)
    // ============================================

    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/session.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../models/Tenant.php';
    require_once __DIR__ . '/../models/Room.php';
    require_once __DIR__ . '/../models/User.php';

    requireRole(['admin', 'owner']);

    $database = new Database();
    $db = $database->getConnection();

    $tenant = new Tenant($db);
    $room = new Room($db);
    $user = new User($db);

    $message = '';
    $messageType = '';

    // เพิ่มผู้เช่าใหม่
    if (isset($_POST['add_tenant'])) {
        $username = 'member_' . uniqid();
        $password = 'temp' . rand(1000, 9999);
        
        $user_id = $user->create($username, $password, $_POST['full_name'], $_POST['phone'], 'member');
        
        if ($user_id) {
            $data = [
                'user_id' => $user_id,
                'room_id' => $_POST['room_id'],
                'full_name' => $_POST['full_name'],
                'phone' => $_POST['phone'],
                'id_card' => $_POST['id_card'] ?? null,
                'line_id' => $_POST['line_id'] ?? null,
                'facebook' => $_POST['facebook'] ?? null,
                'emergency_contact' => $_POST['emergency_contact'] ?? null,
                'emergency_phone' => $_POST['emergency_phone'] ?? null,
                'move_in_date' => $_POST['move_in_date'],
                'deposit_amount' => $_POST['deposit_amount'] ?? 0,
                'discount_amount' => $_POST['discount_amount'] ?? 0
            ];
            
            if ($tenant->create($data)) {
                $message = "เพิ่มผู้เช่าสำเร็จ!<br><strong>Username:</strong> {$username}<br><strong>Password:</strong> {$password}<br><span class='text-danger'>กรุณาบันทึกข้อมูลนี้แล้วส่งให้ผู้เช่า</span>";
                $messageType = 'success';
            } else {
                $message = 'เกิดข้อผิดพลาดในการเพิ่มผู้เช่า';
                $messageType = 'danger';
            }
        } else {
            $message = 'เกิดข้อผิดพลาดในการสร้างบัญชีผู้ใช้';
            $messageType = 'danger';
        }
    }

    // แก้ไขผู้เช่า
    if (isset($_POST['edit_tenant'])) {
        $data = [
            'full_name' => $_POST['full_name'],
            'phone' => $_POST['phone'],
            'line_id' => $_POST['line_id'] ?? null,
            'facebook' => $_POST['facebook'] ?? null,
            'emergency_contact' => $_POST['emergency_contact'] ?? null,
            'emergency_phone' => $_POST['emergency_phone'] ?? null,
            'discount_amount' => $_POST['discount_amount'] ?? 0
        ];
        
        if ($tenant->update($_POST['tenant_id'], $data)) {
            $message = 'แก้ไขข้อมูลสำเร็จ!';
            $messageType = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาด';
            $messageType = 'danger';
        }
    }

    // ย้ายผู้เช่าออก
    if (isset($_GET['move_out'])) {
        if ($tenant->moveOut($_GET['move_out'], date('Y-m-d'))) {
            $message = 'บันทึกการย้ายออกสำเร็จ! ห้องจะเปลี่ยนสถานะเป็นว่างอัตโนมัติ';
            $messageType = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาด';
            $messageType = 'danger';
        }
    }

    // ดึงรายการผู้เช่า
    $tenants = $tenant->getAll(true);
    $availableRooms = $room->getAll('available');

    // นับจำนวนผู้เช่า
    $totalTenants = count($tenants);
    $vipTenants = count(array_filter($tenants, fn($t) => $t['discount_amount'] > 0));
    $totalRooms = $totalTenants + count($availableRooms);
    $occupancyRate = $totalRooms > 0 ? round(($totalTenants / $totalRooms) * 100, 1) : 0;
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>จัดการผู้เช่า - ระบบจัดการหอพัก</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            }

            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                background-attachment: fixed;
                font-family: 'Inter', sans-serif;
                min-height: 100vh;
            }

            .main-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 30px;
                margin: 20px;
                padding: 30px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }

            /* Header Style */
            .page-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 20px;
                padding: 2rem;
                margin-bottom: 2rem;
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
                color: white;
            }

            .page-header h1 {
                font-weight: 700;
                margin: 0;
                font-size: 2rem;
            }

            .page-header p {
                margin: 0.5rem 0 0 0;
                opacity: 0.9;
            }

            /* Stat Cards */
            .stat-card {
                border: none;
                border-radius: 20px;
                padding: 1.5rem;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                overflow: hidden;
                position: relative;
                height: 100%;
            }

            .stat-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                opacity: 0.1;
                background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 100%);
            }

            .stat-card:hover {
                transform: translateY(-10px) scale(1.02);
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            }

            .stat-card.primary { background: var(--primary-gradient); color: white; }
            .stat-card.success { background: var(--success-gradient); color: white; }
            .stat-card.warning { background: var(--warning-gradient); color: white; }
            .stat-card.info { background: var(--info-gradient); color: white; }

            .stat-icon {
                font-size: 3.5rem;
                opacity: 0.2;
                position: absolute;
                right: 1.5rem;
                top: 50%;
                transform: translateY(-50%) rotate(-15deg);
                transition: all 0.4s;
            }

            .stat-card:hover .stat-icon {
                transform: translateY(-50%) rotate(0deg) scale(1.1);
                opacity: 0.3;
            }

            .stat-value {
                font-size: 2.5rem;
                font-weight: 800;
                margin: 0;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
                position: relative;
                z-index: 1;
            }

            .stat-label {
                font-size: 0.9rem;
                opacity: 0.9;
                font-weight: 500;
                margin-bottom: 0.5rem;
                position: relative;
                z-index: 1;
            }

            .stat-change {
                font-size: 0.85rem;
                margin-top: 0.5rem;
                opacity: 0.9;
                position: relative;
                z-index: 1;
            }

            /* Table Card */
            .table-card {
                border: none;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                overflow: hidden;
                background: white;
            }

            .table-card .card-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 1.5rem;
                font-weight: 600;
            }

            /* Modern Table */
            .table-modern {
                margin: 0;
            }

            .table-modern thead th {
                background: linear-gradient(to right, #f8f9fa, #e9ecef);
                border: none;
                font-weight: 600;
                color: #495057;
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                padding: 1.2rem 1rem;
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .table-modern tbody td {
                padding: 1.2rem 1rem;
                vertical-align: middle;
                border-bottom: 1px solid #f0f0f0;
            }

            .table-modern tbody tr {
                transition: all 0.3s;
            }

            .table-modern tbody tr:hover {
                background: linear-gradient(to right, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
                transform: scale(1.01);
                box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            }

            /* Badges */
            .badge-custom {
                padding: 0.5rem 1rem;
                border-radius: 50px;
                font-weight: 600;
                font-size: 0.85rem;
                letter-spacing: 0.5px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }

            .badge-room {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                font-size: 1rem;
                padding: 0.6rem 1.2rem;
            }

            .badge-vip {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }

            /* Buttons */
            .btn-action {
                border-radius: 12px;
                padding: 0.5rem 1.2rem;
                font-weight: 500;
                transition: all 0.3s;
                border: none;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            }

            .btn-action:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            }

            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }

            .btn-info {
                background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            }

            .btn-warning {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }

            .btn-success {
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            }

            .btn-add {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 0.8rem 2rem;
                border-radius: 15px;
                font-weight: 600;
                box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
                transition: all 0.3s;
            }

            .btn-add:hover {
                transform: translateY(-3px);
                box-shadow: 0 12px 30px rgba(102, 126, 234, 0.5);
            }

            /* Modal */
            .modal-content {
                border: none;
                border-radius: 25px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }

            .modal-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 1.5rem 2rem;
            }

            .modal-header .btn-close {
                filter: brightness(0) invert(1);
            }

            .modal-body {
                padding: 2rem;
            }

            .form-label {
                font-weight: 600;
                color: #495057;
                margin-bottom: 0.5rem;
                font-size: 0.9rem;
            }

            .form-control, .form-select {
                border: 2px solid #e9ecef;
                border-radius: 12px;
                padding: 0.75rem 1rem;
                transition: all 0.3s;
            }

            .form-control:focus, .form-select:focus {
                border-color: #667eea;
                box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            }

            /* Alert */
            .alert {
                border: none;
                border-radius: 15px;
                padding: 1.2rem 1.5rem;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            }

            .alert-success {
                background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
                border-left: 4px solid #11998e;
            }

            .alert-danger {
                background: linear-gradient(135deg, rgba(250, 112, 154, 0.1) 0%, rgba(254, 225, 64, 0.1) 100%);
                border-left: 4px solid #fa709a;
            }

            /* Empty State */
            .empty-state {
                padding: 4rem 2rem;
                text-align: center;
            }

            .empty-state i {
                font-size: 4rem;
                opacity: 0.3;
                margin-bottom: 1rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }

            .empty-state p {
                color: #6c757d;
                font-size: 1.1rem;
            }

            /* Contact Icons */
            .contact-icon {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.4rem 0.8rem;
                background: rgba(102, 126, 234, 0.1);
                border-radius: 8px;
                font-size: 0.9rem;
            }

            .contact-icon i {
                color: #667eea;
            }

            /* Animations */
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .animate-slide-in {
                animation: slideIn 0.5s ease-out;
            }

            /* Scrollbar */
            .table-responsive::-webkit-scrollbar {
                height: 8px;
            }

            .table-responsive::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }

            .table-responsive::-webkit-scrollbar-thumb {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 10px;
            }
        </style>
    </head>
    <body>
        <?php include 'includes/navbar.php'; ?>

        <div class="container-fluid">
            <div class="row">
                <?php include 'includes/sidebar.php'; ?>

                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                    <div class="main-container animate-slide-in">
                        <!-- Header -->
                        <div class="page-header">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h1>
                                        <i class="bi bi-people-fill"></i> จัดการผู้เช่า
                                    </h1>
                                    <p class="mb-0">รายการผู้เช่าทั้งหมดในระบบ</p>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addTenantModal">
                                        <i class="bi bi-person-plus-fill"></i> เพิ่มผู้เช่าใหม่
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show animate-slide-in">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Statistics Cards -->
                        <div class="row g-4 mb-4">
                            <div class="col-lg-3 col-md-6">
                                <div class="stat-card primary animate-slide-in" style="animation-delay: 0.1s">
                                    <i class="bi bi-people stat-icon"></i>
                                    <p class="stat-label">ผู้เช่าทั้งหมด</p>
                                    <h2 class="stat-value"><?php echo $totalTenants; ?></h2>
                                    <div class="stat-change">
                                        <i class="bi bi-graph-up"></i> รายการทั้งหมด
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="stat-card success animate-slide-in" style="animation-delay: 0.2s">
                                    <i class="bi bi-star-fill stat-icon"></i>
                                    <p class="stat-label">สมาชิก VIP</p>
                                    <h2 class="stat-value"><?php echo $vipTenants; ?></h2>
                                    <div class="stat-change">
                                        <i class="bi bi-award"></i> มีส่วนลดพิเศษ
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="stat-card warning animate-slide-in" style="animation-delay: 0.3s">
                                    <i class="bi bi-door-open-fill stat-icon"></i>
                                    <p class="stat-label">ห้องว่าง</p>
                                    <h2 class="stat-value"><?php echo count($availableRooms); ?></h2>
                                    <div class="stat-change">
                                        <i class="bi bi-arrow-right"></i> พร้อมให้เช่า
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="stat-card info animate-slide-in" style="animation-delay: 0.4s">
                                    <i class="bi bi-percent stat-icon"></i>
                                    <p class="stat-label">อัตราการเข้าพัก</p>
                                    <h2 class="stat-value"><?php echo $occupancyRate; ?>%</h2>
                                    <div class="stat-change">
                                        <i class="bi bi-pie-chart-fill"></i> Occupancy Rate
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tenants Table -->
                        <div class="table-card animate-slide-in" style="animation-delay: 0.5s">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-list-ul"></i> รายชื่อผู้เช่าทั้งหมด
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-modern mb-0">
                                        <thead>
                                            <tr>
                                                <th width="100">ห้อง</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>ช่องทางติดต่อ</th>
                                                <th>วันที่เข้าพัก</th>
                                                <th>ส่วนลด</th>
                                                <th width="220" class="text-center">จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($tenants) > 0): ?>
                                                <?php foreach ($tenants as $t): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge badge-room">
                                                                <?php echo $t['room_number']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div>
                                                                <strong style="font-size: 1.05rem;"><?php echo $t['full_name']; ?></strong>
                                                                <?php if ($t['discount_amount'] > 0): ?>
                                                                    <span class="badge badge-vip badge-custom ms-2">
                                                                        <i class="bi bi-star-fill"></i> VIP
                                                                    </span>
                                                                <?php endif; ?>
                                                                <div class="text-muted small mt-1">
                                                                    <i class="bi bi-telephone"></i> <?php echo $t['phone']; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column gap-2">
                                                                <?php if ($t['line_id']): ?>
                                                                    <span class="contact-icon">
                                                                        <i class="bi bi-line"></i>
                                                                        <?php echo $t['line_id']; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if ($t['facebook']): ?>
                                                                    <span class="contact-icon">
                                                                        <i class="bi bi-facebook"></i>
                                                                        <?php echo $t['facebook']; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if (!$t['line_id'] && !$t['facebook']): ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <i class="bi bi-calendar-check text-primary"></i>
                                                                <?php echo formatThaiDate($t['move_in_date']); ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php if ($t['discount_amount'] > 0): ?>
                                                                <span class="badge bg-success badge-custom">
                                                                    <i class="bi bi-tag-fill"></i>
                                                                    -฿<?php echo formatMoney($t['discount_amount']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-2 justify-content-center">
                                                                <button class="btn btn-sm btn-info btn-action" 
                                                                        onclick='editTenant(<?php echo json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                                    <i class="bi bi-pencil-square"></i>
                                                                </button>
                                                                <a href="?move_out=<?php echo $t['tenant_id']; ?>" 
                                                                class="btn btn-sm btn-warning btn-action"
                                                                onclick="return confirm('ยืนยันการย้ายออกของ <?php echo $t['full_name']; ?>?\n\nห้อง <?php echo $t['room_number']; ?> จะเปลี่ยนสถานะเป็นว่าง')">
                                                                    <i class="bi bi-box-arrow-right"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6">
                                                        <div class="empty-state">
                                                            <i class="bi bi-inbox"></i>
                                                            <p class="mb-0">ยังไม่มีผู้เช่าในระบบ</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- Modal เพิ่มผู้เช่า -->
        <div class="modal fade" id="addTenantModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-person-plus-fill"></i> เพิ่มผู้เช่าใหม่
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                <strong>หมายเหตุ:</strong> ระบบจะสร้างบัญชีผู้ใช้ให้อัตโนมัติ และแสดง Username/Password หลังจากบันทึกสำเร็จ
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-person"></i> ชื่อ-นามสกุล <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="full_name" 
                                        placeholder="กรอกชื่อ-นามสกุลจริง" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-telephone"></i> เบอร์โทร <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel" class="form-control" name="phone" 
                                        placeholder="08X-XXX-XXXX" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-door-open"></i> เลือกห้อง <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" name="room_id" required>
                                        <option value="">-- เลือกห้องว่าง --</option>
                                        <?php foreach ($availableRooms as $r): ?>
                                            <option value="<?php echo $r['room_id']; ?>">
                                                ห้อง <?php echo $r['room_number']; ?> 
                                                (<?php echo $r['room_type']; ?>) - 
                                                ฿<?php echo formatMoney($r['monthly_rent']); ?>/เดือน
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-calendar"></i> วันที่เข้าพัก <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" name="move_in_date" 
                                        value="<?php echo date('Y-m-d'); ?>" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-credit-card"></i> เลขบัตรประชาชน
                                    </label>
                                    <input type="text" class="form-control" name="id_card" 
                                        placeholder="X-XXXX-XXXXX-XX-X" maxlength="17">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-cash-stack"></i> เงินประกัน (บาท)
                                    </label>
                                    <input type="number" step="0.01" class="form-control" name="deposit_amount" 
                                        value="0" placeholder="0.00">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-line text-success"></i> LINE ID
                                    </label>
                                    <input type="text" class="form-control" name="line_id" 
                                        placeholder="@yourline">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-facebook text-primary"></i> Facebook
                                    </label>
                                    <input type="text" class="form-control" name="facebook" 
                                        placeholder="ชื่อ Facebook">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-person-fill"></i> ผู้ติดต่อฉุกเฉิน
                                    </label>
                                    <input type="text" class="form-control" name="emergency_contact" 
                                        placeholder="ชื่อผู้ติดต่อฉุกเฉิน">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-telephone-fill"></i> เบอร์ฉุกเฉิน
                                    </label>
                                    <input type="tel" class="form-control" name="emergency_phone" 
                                        placeholder="08X-XXX-XXXX">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-action" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> ยกเลิก
                            </button>
                            <button type="submit" name="add_tenant" class="btn btn-success btn-action">
                                <i class="bi bi-check-circle"></i> บันทึกผู้เช่า
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal แก้ไขผู้เช่า -->
        <div class="modal fade" id="editTenantModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="tenant_id" id="edit_tenant_id">
                        <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h5 class="modal-title">
                                <i class="bi bi-pencil-square"></i> แก้ไขข้อมูลผู้เช่า
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เบอร์โทร <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" id="edit_phone" required>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-line text-success"></i> LINE ID
                                    </label>
                                    <input type="text" class="form-control" name="line_id" id="edit_line_id">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-facebook text-primary"></i> Facebook
                                    </label>
                                    <input type="text" class="form-control" name="facebook" id="edit_facebook">
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-tag-fill text-success"></i> 
                                        ส่วนลดรายเดือน (บาท)
                                    </label>
                                    <input type="number" step="0.01" class="form-control" 
                                        name="discount_amount" id="edit_discount_amount" placeholder="0.00">
                                    <small class="text-muted">ส่วนลดจะถูกหักจากยอดรวมในใบเสร็จทุกเดือน</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">ผู้ติดต่อฉุกเฉิน</label>
                                    <input type="text" class="form-control" name="emergency_contact" id="edit_emergency_contact">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">เบอร์ฉุกเฉิน</label>
                                    <input type="tel" class="form-control" name="emergency_phone" id="edit_emergency_phone">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-action" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> ยกเลิก
                            </button>
                            <button type="submit" name="edit_tenant" class="btn btn-success btn-action">
                                <i class="bi bi-save"></i> บันทึกการแก้ไข
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function editTenant(tenant) {
                document.getElementById('edit_tenant_id').value = tenant.tenant_id;
                document.getElementById('edit_full_name').value = tenant.full_name;
                document.getElementById('edit_phone').value = tenant.phone;
                document.getElementById('edit_line_id').value = tenant.line_id || '';
                document.getElementById('edit_facebook').value = tenant.facebook || '';
                document.getElementById('edit_discount_amount').value = tenant.discount_amount || 0;
                document.getElementById('edit_emergency_contact').value = tenant.emergency_contact || '';
                document.getElementById('edit_emergency_phone').value = tenant.emergency_phone || '';
                
                new bootstrap.Modal(document.getElementById('editTenantModal')).show();
            }

            // Smooth scroll animation
            document.addEventListener('DOMContentLoaded', function() {
                const cards = document.querySelectorAll('.animate-slide-in');
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.opacity = '1';
                    }, index * 100);
                });
            });
        </script>
    </body>
    </html>