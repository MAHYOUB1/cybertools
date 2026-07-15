<?php
// ================================================================
// api/admin/stats.php - إحصائيات المسؤول (آمن)
// Role-Based Access Control + Command Injection Prevention
// ================================================================
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
require_once '../../includes/security.php';
setHeaders();

$action = $_GET['action'] ?? 'stats';

// Access Control: المسؤول فقط يمكنه الوصول
$user = requireRole('admin');

$db = getDB();

// ────────────────────────────────────────────────────────────────
if ($action === 'ping') {
    $ip = $_GET['ip'] ?? '127.0.0.1';

    // التحقق الصارم من صحة عنوان IP
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        jsonError('عنوان IP غير صالح. يجب إدخال عنوان IP حقيقي فقط.', 400);
    }

    // استخدام escapeshellarg لمنع Command Injection
    $safeIP = escapeshellarg($ip);
    $result = shell_exec("ping -c 2 $safeIP 2>&1");

    logActivity($user['id'], 'ping', 'system', null, "IP: $ip");
    jsonResponse(['success' => true, 'output' => $result]);
    exit();
}

// ────────────────────────────────────────────────────────────────
if ($action === 'users') {
    $stmt = $db->prepare("SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();

    // تعقيم الخرج
    foreach ($users as &$u) {
        $u['username'] = sanitizeOutput($u['username']);
        $u['email']    = sanitizeOutput($u['email']);
    }

    jsonResponse(['success' => true, 'data' => $users]);
    exit();
}

// ────────────────────────────────────────────────────────────────
// إحصائيات عامة (Prepared Statements)
$totalUsers    = $db->query("SELECT COUNT(*) as c FROM users WHERE role='customer'")->fetch()['c'];
$totalSellers  = $db->query("SELECT COUNT(*) as c FROM sellers")->fetch()['c'];
$totalProducts = $db->query("SELECT COUNT(*) as c FROM products WHERE is_active=1")->fetch()['c'];
$totalOrders   = $db->query("SELECT COUNT(*) as c FROM orders")->fetch()['c'];
$totalRevenue  = $db->query("SELECT COALESCE(SUM(total), 0) as s FROM orders WHERE status NOT IN ('cancelled','refunded')")->fetch()['s'];
$pendingOrders = $db->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch()['c'];

// المنتجات الأعلى مبيعاً
$topStmt = $db->query("SELECT p.name, SUM(oi.quantity) as sold, SUM(oi.total) as revenue
                        FROM order_items oi JOIN products p ON oi.product_id = p.id
                        GROUP BY p.id ORDER BY sold DESC LIMIT 5");
$top = $topStmt->fetchAll();
foreach ($top as &$t) {
    $t['name'] = sanitizeOutput($t['name']);
}

// المستخدمون الجدد (آخر 30 يوم)
$newUsers = $db->query("SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['c'];

jsonResponse([
    'success'      => true,
    'stats'        => [
        'total_users'    => (int)$totalUsers,
        'total_sellers'  => (int)$totalSellers,
        'total_products' => (int)$totalProducts,
        'total_orders'   => (int)$totalOrders,
        'total_revenue'  => (float)$totalRevenue,
        'pending_orders' => (int)$pendingOrders,
        'new_users'      => (int)$newUsers,
    ],
    'top_products' => $top,
]);
