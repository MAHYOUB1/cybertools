<?php
// ================================================================
// api/cart.php - إدارة سلة التسوق (آمن)
// IDOR Protection + PDO Prepared Statements
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':    getCart($user);       break;
    case 'POST':   addToCart($user);     break;
    case 'PUT':    updateCart($user, $id); break;
    case 'DELETE': removeFromCart($user, $id); break;
    default: jsonError('Method غير مدعوم', 405);
}

// ────────────────────────────────────────────────────────────────
function getCart(array $user): void {
    $db     = getDB();
    $userId = $user['id'];

    $stmt = $db->prepare("SELECT c.id, c.quantity, p.id as product_id, p.name,
                          p.price, p.image, p.stock
                          FROM cart c JOIN products p ON c.product_id = p.id
                          WHERE c.user_id = :uid");
    $stmt->execute(['uid' => $userId]);
    $items = $stmt->fetchAll();

    $total = 0;
    foreach ($items as &$row) {
        $row['name']     = sanitizeOutput($row['name']);
        $row['subtotal'] = round($row['price'] * $row['quantity'], 2);
        $total += $row['subtotal'];
    }

    jsonResponse(['success' => true, 'items' => $items, 'total' => $total, 'count' => count($items)]);
}

// ────────────────────────────────────────────────────────────────
function addToCart(array $user): void {
    validateCSRFToken();

    $data       = getRequestBody();
    $product_id = (int)($data['product_id'] ?? 0);
    $quantity   = max(1, (int)($data['quantity'] ?? 1));
    $userId     = $user['id'];
    $db         = getDB();

    if (!$product_id) jsonError('معرّف المنتج مطلوب');

    // التحقق من توفر المنتج (Prepared Statement)
    $stmt = $db->prepare("SELECT id, stock FROM products WHERE id = :pid AND is_active = 1");
    $stmt->execute(['pid' => $product_id]);
    $product = $stmt->fetch();

    if (!$product) jsonError('المنتج غير موجود', 404);
    if ($product['stock'] < $quantity) jsonError('الكمية المطلوبة غير متوفرة');

    // إضافة مع التعامل مع التكرار
    $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (:uid, :pid, :qty)
                          ON DUPLICATE KEY UPDATE quantity = quantity + :qty2");
    $stmt->execute([
        'uid'  => $userId,
        'pid'  => $product_id,
        'qty'  => $quantity,
        'qty2' => $quantity,
    ]);

    jsonResponse(['success' => true, 'message' => 'تمت الإضافة للسلة']);
}

// ────────────────────────────────────────────────────────────────
function updateCart(array $user, ?int $id): void {
    if (!$id) jsonError('معرّف عنصر السلة مطلوب', 400);
    validateCSRFToken();

    $data     = getRequestBody();
    $quantity = (int)($data['quantity'] ?? 1);
    $db       = getDB();

    // IDOR Protection: التحقق أن عنصر السلة يخص المستخدم الحالي
    $stmt = $db->prepare("SELECT id FROM cart WHERE id = :cid AND user_id = :uid");
    $stmt->execute(['cid' => $id, 'uid' => $user['id']]);
    if (!$stmt->fetch()) jsonError('العنصر غير موجود أو ليس لديك صلاحية', 403);

    if ($quantity <= 0) {
        $stmt = $db->prepare("DELETE FROM cart WHERE id = :cid AND user_id = :uid");
        $stmt->execute(['cid' => $id, 'uid' => $user['id']]);
        jsonResponse(['success' => true, 'message' => 'تم حذف العنصر']);
    }

    $stmt = $db->prepare("UPDATE cart SET quantity = :qty WHERE id = :cid AND user_id = :uid");
    $stmt->execute(['qty' => $quantity, 'cid' => $id, 'uid' => $user['id']]);
    jsonResponse(['success' => true, 'message' => 'تم تحديث الكمية']);
}

// ────────────────────────────────────────────────────────────────
function removeFromCart(array $user, ?int $id): void {
    if (!$id) jsonError('معرّف عنصر السلة مطلوب', 400);
    validateCSRFToken();

    $db = getDB();

    // IDOR Protection: حذف فقط عناصر المستخدم الحالي
    $stmt = $db->prepare("DELETE FROM cart WHERE id = :cid AND user_id = :uid");
    $stmt->execute(['cid' => $id, 'uid' => $user['id']]);

    if ($stmt->rowCount() === 0) jsonError('العنصر غير موجود', 404);
    jsonResponse(['success' => true, 'message' => 'تم حذف العنصر من السلة']);
}
