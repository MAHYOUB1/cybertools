<?php
// ================================================================
// api/orders.php - إدارة الطلبات (آمن)
// IDOR Protection + Prepared Statements + Server-Side Price Validation
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':  getUserOrders(); break;
    case 'POST': createOrder();  break;
    default: jsonError('Method غير مدعوم', 405);
}

// ────────────────────────────────────────────────────────────────
function getUserOrders(): void {
    $user = requireAuth();
    $db   = getDB();

    // IDOR Protection: المستخدم يرى طلباته فقط
    // المسؤول يمكنه رؤية طلبات أي مستخدم
    $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user['id'];

    if ($targetUserId !== $user['id'] && $user['role'] !== 'admin') {
        jsonError('غير مصرح. لا يمكنك رؤية طلبات مستخدمين آخرين.', 403);
    }

    $stmt = $db->prepare("SELECT o.*, COUNT(oi.id) as items_count
                          FROM orders o
                          LEFT JOIN order_items oi ON o.id = oi.order_id
                          WHERE o.user_id = :uid
                          GROUP BY o.id
                          ORDER BY o.created_at DESC");
    $stmt->execute(['uid' => $targetUserId]);
    $orders = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $orders]);
}

// ────────────────────────────────────────────────────────────────
function createOrder(): void {
    $user = requireAuth();
    validateCSRFToken();

    $data = getRequestBody();
    $db   = getDB();

    $userId  = $user['id'];
    $address = sanitize($data['address'] ?? '');
    $name    = sanitize($data['billing_name'] ?? $user['name']);
    $email   = sanitize($data['billing_email'] ?? $user['email'] ?? '');
    $phone   = sanitize($data['billing_phone'] ?? '');

    $frontendItems = $data['items'] ?? [];
    if (empty($frontendItems)) jsonError('السلة فارغة');

    // بدء Transaction لضمان التناسق
    $db->beginTransaction();

    try {
        $items    = [];
        $subtotal = 0;

        foreach ($frontendItems as $fItem) {
            $pid = (int)($fItem['id'] ?? 0);
            $qty = max(1, (int)($fItem['quantity'] ?? 1));
            if ($pid === 0) continue;

            // جلب السعر من الخادم (لا نثق بسعر الفرونت إند)
            $stmt = $db->prepare("SELECT id as product_id, price, name as product_name, seller_id, stock 
                                  FROM products WHERE id = :pid AND is_active = 1 FOR UPDATE");
            $stmt->execute(['pid' => $pid]);
            $p = $stmt->fetch();

            if (!$p) {
                $db->rollBack();
                jsonError('أحد المنتجات غير موجود');
            }
            if ($p['stock'] < $qty) {
                $db->rollBack();
                jsonError("المخزون غير كافٍ للمنتج: " . sanitizeOutput($p['product_name']));
            }

            $p['quantity'] = $qty;
            $p['total']    = round($p['price'] * $qty, 2);
            $subtotal     += $p['total'];
            $items[]       = $p;
        }

        $tax   = round($subtotal * 0.15, 2);
        $total = round($subtotal + $tax, 2);
        $orderNumber = generateOrderNumber();

        // إنشاء الطلب
        $stmt = $db->prepare("INSERT INTO orders (user_id, order_number, subtotal, tax, total, shipping_address, billing_name, billing_email, billing_phone)
                              VALUES (:uid, :onum, :subtotal, :tax, :total, :addr, :name, :email, :phone)");
        $stmt->execute([
            'uid'      => $userId,
            'onum'     => $orderNumber,
            'subtotal' => $subtotal,
            'tax'      => $tax,
            'total'    => $total,
            'addr'     => $address,
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
        ]);
        $orderId = (int)$db->lastInsertId();

        // إضافة تفاصيل الطلب وتحديث المخزون
        $itemStmt  = $db->prepare("INSERT INTO order_items (order_id, product_id, seller_id, product_name, quantity, price, total)
                                   VALUES (:oid, :pid, :sid, :pname, :qty, :price, :total)");
        $stockStmt = $db->prepare("UPDATE products SET stock = stock - :qty WHERE id = :pid AND stock >= :qty2");

        foreach ($items as $item) {
            $itemStmt->execute([
                'oid'   => $orderId,
                'pid'   => $item['product_id'],
                'sid'   => $item['seller_id'],
                'pname' => $item['product_name'],
                'qty'   => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['total'],
            ]);
            $stockStmt->execute([
                'qty'  => $item['quantity'],
                'pid'  => $item['product_id'],
                'qty2' => $item['quantity'],
            ]);
        }

        // تفريغ السلة
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);

        $db->commit();

        logActivity($userId, 'create_order', 'order', $orderId);

        jsonResponse([
            'success'      => true,
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'total'        => $total,
            'message'      => 'تم إنشاء الطلب بنجاح',
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('[Order Error] ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إنشاء الطلب', 500);
    }
}
