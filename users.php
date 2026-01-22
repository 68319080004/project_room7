<?php
// ============================================
// ไฟล์: admin/users.php
// คำอธิบาย: จัดการผู้ใช้งานระบบ (เฉพาะ Owner)
// ============================================

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/functions.php';
require_once '../models/User.php';

requireRole('owner'); // เฉพาะ Owner เท่านั้น

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$message = '';
$messageType = '';

// เพิ่มผู้ใช้ใหม่
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    if (strlen($password) < 6) {
        $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        $messageType = 'danger';
    } else {
        $user_id = $user->create($username, $password, $full_name, $phone, $role);
        
        if ($user_id) {
            // อัปเดท email
            $user->update($user_id, ['email' => $email]);
            
            $message = "เพิ่มผู้ใช้สำเร็จ! Username: <strong>{$username}</strong>";
            $messageType = 'success';
        } else {
            $message = 'Username นี้ถูกใช้งานแล้ว';
            $messageType = 'danger';
        }
    }
}

// แก้ไขผู้ใช้
if (isset($_POST['edit_user'])) {
    $data = [
        'full_name' => $_POST['full_name'],
        'phone' => $_POST['phone'],
        'email' => $_POST['email'],
        'role' => $_POST['role'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    if ($user->update($_POST['user_id'], $data)) {
        $message = 'แก้ไขข้อมูลสำเร็จ!';
        $messageType = 'success';
    } else {
        $message = 'เกิดข้อผิดพลาด';
        $messageType = 'danger';
    }
}

// เปลี่ยนรหัสผ่าน
if (isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        $messageType = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'รหัสผ่านไม่ตรงกัน';
        $messageType = 'danger';
    } else {
        if ($user->changePassword($_POST['user_id'], $new_password)) {
            $message = 'เปลี่ยนรหัสผ่านสำเร็จ!';
            $messageType = 'success';
        } else {
            $message = 'เกิดข้อผิดพลาด';
            $messageType = 'danger';
        }
    }
}

// ลบผู้ใช้ (แค่ปิดการใช้งาน)
if (isset($_GET['deactivate'])) {
    $user->update($_GET['deactivate'], ['is_active' => 0]);
    $message = 'ปิดการใช้งานผู้ใช้สำเร็จ';
    $messageType = 'warning';
}

// เปิดการใช้งาน
if (isset($_GET['activate'])) {
    $user->update($_GET['activate'], ['is_active' => 1]);
    $message = 'เปิดการใช้งานผู้ใช้สำเร็จ';
    $messageType = 'success';
}

// ดึงรายการผู้ใช้ทั้งหมด
$users = $user->getAll();

// นับจำนวนตาม Role
$countByRole = [
    'owner' => 0,
    'admin' => 0,
    'member' => 0,
    'inactive' => 0
];

foreach ($users as $u) {
    if ($u['is_active']) {
        $countByRole[$u['role']]++;
    } else {
        $countByRole['inactive']++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน - ระบบจัดการหอพัก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #81FBB8 0%, #28C76F 100%);
            --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-warning: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --gradient-danger: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-secondary: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
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

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        .stat-card.card-owner::before { background: var(--gradient-danger); }
        .stat-card.card-admin::before { background: var(--gradient-primary); }
        .stat-card.card-member::before { background: var(--gradient-success); }
        .stat-card.card-inactive::before { background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); }

        .stat-icon {
            font-size: 3rem;
            opacity: 0.2;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .stat-card.card-owner .stat-icon { color: #f5576c; }
        .stat-card.card-admin .stat-icon { color: #667eea; }
        .stat-card.card-member .stat-icon { color: #28C76F; }
        .stat-card.card-inactive .stat-icon { color: #6b7280; }

        .stat-content {
            position: relative;
            z-index: 2;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-card.card-owner .stat-number { color: #f5576c; }
        .stat-card.card-admin .stat-number { color: #667eea; }
        .stat-card.card-member .stat-number { color: #28C76F; }
        .stat-card.card-inactive .stat-number { color: #6b7280; }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }

        .card-header-custom {
            background: var(--gradient-primary);
            color: white;
            padding: 1.75rem 2rem;
            border: none;
        }

        .card-header-custom h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-box input {
            width: 100%;
            padding: 0.9rem 1.2rem 0.9rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
        }

        /* Table Styling */
        .table-custom {
            margin: 0;
        }

        .table-custom thead {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }

        .table-custom thead th {
            border: none;
            padding: 1.25rem 1rem;
            font-weight: 700;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table-custom tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-custom tbody tr:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.03) 0%, rgba(118, 75, 162, 0.03) 100%);
            transform: scale(1.005);
        }

        .table-custom tbody td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .badge-owner {
            background: var(--gradient-danger);
            color: white;
        }

        .badge-admin {
            background: var(--gradient-primary);
            color: white;
        }

        .badge-member {
            background: var(--gradient-success);
            color: white;
        }

        .badge-active {
            background: var(--gradient-success);
            color: white;
        }

        .badge-inactive {
            background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%);
            color: white;
        }

        .badge-you {
            background: var(--gradient-info);
            color: white;
            font-size: 0.7rem;
            padding: 0.3rem 0.8rem;
            margin-left: 0.5rem;
        }

        /* Buttons */
        .btn-add-user {
            background: white;
            color: #667eea;
            border: 2px solid white;
            padding: 0.75rem 1.75rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-add-user:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-3px);
            color: #667eea;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-edit {
            background: var(--gradient-info);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 172, 254, 0.4);
            color: white;
        }

        .btn-password {
            background: var(--gradient-warning);
            color: white;
        }

        .btn-password:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(250, 112, 154, 0.4);
            color: white;
        }

        .btn-deactivate {
            background: var(--gradient-danger);
            color: white;
        }

        .btn-deactivate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 147, 251, 0.4);
            color: white;
        }

        .btn-activate {
            background: var(--gradient-success);
            color: white;
        }

        .btn-activate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(129, 251, 184, 0.4);
            color: white;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 25px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            padding: 1.75rem 2rem;
            border: none;
            color: white;
        }

        .modal-header.bg-primary {
            background: var(--gradient-primary) !important;
        }

        .modal-header.bg-info {
            background: var(--gradient-info) !important;
        }

        .modal-header.bg-warning {
            background: var(--gradient-warning) !important;
        }

        .modal-title {
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border: none;
            background: #f9fafb;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 15px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }

        /* Alert Styling */
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(129, 251, 184, 0.2) 0%, rgba(40, 199, 111, 0.2) 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(240, 147, 251, 0.2) 0%, rgba(245, 87, 108, 0.2) 100%);
            color: #721c24;
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(250, 112, 154, 0.2) 0%, rgba(254, 225, 64, 0.2) 100%);
            color: #856404;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 5rem;
            opacity: 0.2;
            margin-bottom: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-header {
                padding: 1.5rem;
            }

            .table-responsive {
                margin: 0 -1rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .btn-action {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }
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
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h1>
                                <i class="bi bi-person-gear"></i> จัดการผู้ใช้งาน
                            </h1>
                            <p class="text-muted mb-0" style="font-size: 1rem; margin-top: 0.5rem;">
                                จัดการและควบคุมผู้ใช้งานในระบบ
                            </p>
                        </div>
                        <button type="button" class="btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus-fill"></i>
                            เพิ่มผู้ใช้ใหม่
                        </button>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'x-circle') ?>-fill"></i>
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="stats-container">
                    <div class="stat-card card-owner">
                        <div class="stat-icon">
                            <i class="bi bi-shield-fill-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">
                                <i class="bi bi-award"></i> Owner
                            </div>
                            <div class="stat-number"><?= $countByRole['owner'] ?></div>
                            <small class="text-muted">เจ้าของหอพัก</small>
                        </div>
                    </div>

                    <div class="stat-card card-admin">
                        <div class="stat-icon">
                            <i class="bi bi-person-fill-gear"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">
                                <i class="bi bi-gear"></i> Admin
                            </div>
                            <div class="stat-number"><?= $countByRole['admin'] ?></div>
                            <small class="text-muted">ผู้ดูแลระบบ</small>
                        </div>
                    </div>

                    <div class="stat-card card-member">
                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">
                                <i class="bi bi-person"></i> Member
                            </div>
                            <div class="stat-number"><?= $countByRole['member'] ?></div>
                            <small class="text-muted">ผู้เช่า</small>
                        </div>
                    </div>

                    <div class="stat-card card-inactive">
                        <div class="stat-icon">
                            <i class="bi bi-person-fill-slash"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">
                                <i class="bi bi-slash-circle"></i> Inactive
                            </div>
                            <div class="stat-number"><?= $countByRole['inactive'] ?></div>
                            <small class="text-muted">ปิดใช้งาน</small>
                        </div>
                    </div>
                </div>

                <!-- Search Box -->
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" class="form-control" placeholder="ค้นหาผู้ใช้ ชื่อ Username เบอร์โทร หรือ Email...">
                </div>

                <!-- Main Table Card -->
                <div class="main-card">
                    <div class="card-header-custom">
                        <h5>
                            <i class="bi bi-table"></i>
                            รายชื่อผู้ใช้ทั้งหมด (<?= count($users) ?> คน)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-custom" id="usersTable">
                                <thead>
                                    <tr>
                                        <th><i class="bi bi-person-badge"></i> Username</th>
                                        <th><i class="bi bi-person"></i> ชื่อ-นามสกุล</th>
                                        <th><i class="bi bi-telephone"></i> เบอร์โทร</th>
                                        <th><i class="bi bi-envelope"></i> Email</th>
                                        <th><i class="bi bi-shield"></i> Role</th>
                                        <th><i class="bi bi-flag-fill"></i> สถานะ</th>
                                        <th><i class="bi bi-calendar3"></i> สร้างเมื่อ</th>
                                        <th class="text-center"><i class="bi bi-gear-fill"></i> จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr class="user-row">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--gradient-info); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                                        <?= mb_substr($u['username'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= $u['username'] ?></strong>
                                                        <?php if ($u['user_id'] == $_SESSION['user_id']): ?>
                                                            <span class="status-badge badge-you">คุณ</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><strong><?= $u['full_name'] ?></strong></td>
                                            <td>
                                                <?php if($u['phone']): ?>
                                                    <a href="tel:<?= $u['phone'] ?>" style="color: #6b7280; text-decoration: none;">
                                                        <i class="bi bi-telephone-fill"></i> <?= $u['phone'] ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($u['email']): ?>
                                                    <a href="mailto:<?= $u['email'] ?>" style="color: #6b7280; text-decoration: none;">
                                                        <i class="bi bi-envelope-fill"></i> <?= $u['email'] ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $roleClass = match($u['role']) {
                                                    'owner' => 'badge-owner',
                                                    'admin' => 'badge-admin',
                                                    'member' => 'badge-member',
                                                    default => ''
                                                };
                                                $roleIcon = match($u['role']) {
                                                    'owner' => 'bi-shield-fill-check',
                                                    'admin' => 'bi-person-fill-gear',
                                                    'member' => 'bi-person-fill',
                                                    default => 'bi-person'
                                                };
                                                $roleNames = [
                                                    'owner' => 'Owner',
                                                    'admin' => 'Admin',
                                                    'member' => 'Member'
                                                ];
                                                ?>
                                                <span class="status-badge <?= $roleClass ?>">
                                                    <i class="bi <?= $roleIcon ?>"></i>
                                                    <?= $roleNames[$u['role']] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($u['is_active']): ?>
                                                    <span class="status-badge badge-active">
                                                        <i class="bi bi-check-circle-fill"></i> ใช้งาน
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge badge-inactive">
                                                        <i class="bi bi-x-circle-fill"></i> ปิดใช้งาน
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong style="color: #495057;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></strong>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> <?= date('H:i', strtotime($u['created_at'])) ?> น.
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1 flex-wrap justify-content-center">
                                                    <button class="btn-action btn-edit" 
                                                            onclick='editUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                        <i class="bi bi-pencil-fill"></i> แก้ไข
                                                    </button>
                                                    <button class="btn-action btn-password" 
                                                            onclick='changePassword(<?= $u["user_id"] ?>, "<?= $u["username"] ?>")'>
                                                        <i class="bi bi-key-fill"></i> รหัส
                                                    </button>
                                                    <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                                        <?php if ($u['is_active']): ?>
                                                            <a href="?deactivate=<?= $u['user_id'] ?>" 
                                                               class="btn-action btn-deactivate"
                                                               onclick="return confirm('ยืนยันการปิดการใช้งาน?')">
                                                                <i class="bi bi-x-circle-fill"></i> ปิด
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?activate=<?= $u['user_id'] ?>" 
                                                               class="btn-action btn-activate"
                                                               onclick="return confirm('ยืนยันการเปิดการใช้งาน?')">
                                                                <i class="bi bi-check-circle-fill"></i> เปิด
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <h4>ไม่มีผู้ใช้งาน</h4>
                            <p class="text-muted">ยังไม่มีผู้ใช้งานในระบบ</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal เพิ่มผู้ใช้ -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้ใหม่
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" required placeholder="ตัวอย่าง: john_doe">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร">
                            <small class="text-muted"><i class="bi bi-info-circle"></i> อย่างน้อย 6 ตัวอักษร</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required placeholder="ตัวอย่าง: สมชาย ใจดี">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">เบอร์โทร <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" required placeholder="ตัวอย่าง: 0812345678">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" placeholder="ตัวอย่าง: example@email.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" required>
                                <option value="">-- เลือก Role --</option>
                                <option value="admin">👨‍💼 Admin (ผู้ดูแลระบบ)</option>
                                <option value="member">👤 Member (ผู้เช่า)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; font-weight: 600;">
                            <i class="bi bi-x-circle"></i> ยกเลิก
                        </button>
                        <button type="submit" name="add_user" class="btn btn-primary" style="border-radius: 50px; font-weight: 600; background: var(--gradient-primary); border: none;">
                            <i class="bi bi-check-circle"></i> บันทึก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขผู้ใช้ -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil-fill"></i> แก้ไขข้อมูลผู้ใช้
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" readonly style="background: #f3f4f6;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">เบอร์โทร <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="owner">👑 Owner (เจ้าของหอพัก)</option>
                                <option value="admin">👨‍💼 Admin (ผู้ดูแลระบบ)</option>
                                <option value="member">👤 Member (ผู้เช่า)</option>
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                            <label class="form-check-label" for="edit_is_active">
                                <i class="bi bi-check-circle-fill text-success"></i> เปิดการใช้งาน
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; font-weight: 600;">
                            <i class="bi bi-x-circle"></i> ยกเลิก
                        </button>
                        <button type="submit" name="edit_user" class="btn btn-info text-white" style="border-radius: 50px; font-weight: 600; background: var(--gradient-info); border: none;">
                            <i class="bi bi-check-circle"></i> บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal เปลี่ยนรหัสผ่าน -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="user_id" id="pwd_user_id">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="bi bi-key-fill"></i> เปลี่ยนรหัสผ่าน
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info border-0" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.2) 0%, rgba(0, 242, 254, 0.2) 100%); border-radius: 12px;">
                            <strong><i class="bi bi-person-badge"></i> Username:</strong> <span id="pwd_username"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_password" required placeholder="อย่างน้อย 6 ตัวอักษร">
                            <small class="text-muted"><i class="bi bi-info-circle"></i> อย่างน้อย 6 ตัวอักษร</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" required placeholder="พิมพ์รหัสผ่านอีกครั้ง">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 50px; font-weight: 600;">
                            <i class="bi bi-x-circle"></i> ยกเลิก
                        </button>
                        <button type="submit" name="change_password" class="btn btn-warning" style="border-radius: 50px; font-weight: 600; background: var(--gradient-warning); border: none; color: white;">
                            <i class="bi bi-key-fill"></i> เปลี่ยนรหัสผ่าน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function changePassword(userId, username) {
            document.getElementById('pwd_user_id').value = userId;
            document.getElementById('pwd_username').textContent = username;
            
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1}s both`;
            });
        });
    </script>
</body>
</html>