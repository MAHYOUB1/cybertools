<?php
// ================================================================
// api/seller/stats.php - إحصائيات البائع (آمن)
// Role-Based Access Control + PDO Prepared Statements
// ================================================================
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
require_once '../../includes/security.php';
setHeaders();

// Access Control: البائعون والمسؤولون فقط
$user = requireRole('seller');
$db   = getDB();

// جلب معلومات المتجر بشكل آمن
$stmt = $db->prepare("SELECT * FROM sellers WHERE user_id = :uid");
$stmt->execute(['uid' => $user['id']]);
$seller = $stmt->fetch();

if (!$seller) jsonError('لم يُعثر على حساب البائع', 403);
$sid = $seller['id'];

// إحصائيات المنتجات
$stmt = $db->prepare("SELECT COUNT(*) as c FROM products WHERE seller_id = :sid AND is_active = 1");
$stmt->execute(['sid' => $sid]);
$totalProducts = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT oi.order_id) as c FROM order_items oi WHERE oi.seller_id = :sid");
$stmt->execute(['sid' => $sid]);
$totalOrders = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COALESCE(SUM(oi.total), 0) as s FROM order_items oi
                      JOIN orders o ON oi.order_id = o.id
                      WHERE oi.seller_id = :sid AND o.status NOT IN ('cancelled','refunded')");
$stmt->execute(['sid' => $sid]);
$totalRevenue = $stmt->fetch()['s'];

// المنتجات الأعلى مبيعاً للبائع
$stmt = $db->prepare("SELECT p.name, p.price, SUM(oi.quantity) as sold
                      FROM order_items oi JOIN products p ON oi.product_id = p.id
                      WHERE oi.seller_id = :sid GROUP BY p.id ORDER BY sold DESC LIMIT 5");
$stmt->execute(['sid' => $sid]);
$top = $stmt->fetchAll();
foreach ($top as &$t) {
    $t['name'] = sanitizeOutput($t['name']);
}

// آخر الطلبات
$stmt = $db->prepare("SELECT o.order_number, o.status, o.created_at, oi.product_name, oi.quantity, oi.total
                      FROM order_items oi JOIN orders o ON oi.order_id = o.id
                      WHERE oi.seller_id = :sid ORDER BY o.created_at DESC LIMIT 10");
$stmt->execute(['sid' => $sid]);
$orders = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'seller'  => [
        'id'               => $seller['id'],
        'store_name'       => sanitizeOutput($seller['store_name']),
        'store_description'=> sanitizeOutput($seller['store_description'] ?? ''),
        'commission_rate'  => $seller['commission_rate'],
        'is_verified'      => $seller['is_verified'],
    ],
    'stats' => [
        'total_products' => (int)$totalProducts,
        'total_orders'   => (int)$totalOrders,
        'total_revenue'  => (float)$totalRevenue,
        'commission'     => round((float)$totalRevenue * ($seller['commission_rate'] / 100), 2),
    ],
    'top_products'  => $top,
    'recent_orders' => $orders,
]);
