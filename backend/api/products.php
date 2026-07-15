<?php
// ================================================================
// api/products.php - إدارة المنتجات (آمن)
// PDO Prepared Statements + Whitelist Validation + IDOR Protection
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':    $id ? getProduct($id) : getProducts(); break;
    case 'POST':   addProduct();    break;
    case 'PUT':    updateProduct($id); break;
    case 'DELETE': deleteProduct($id); break;
    default: jsonError('Method غير مدعوم', 405);
}

// ────────────────────────────────────────────────────────────────
function getProducts(): void {
    $db = getDB();

    $search   = clean($_GET['search']   ?? '');
    $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $sort     = $_GET['sort']  ?? 'created_at';
    $order    = $_GET['order'] ?? 'DESC';
    $limit    = max(1, min(50, (int)($_GET['limit'] ?? 12)));   // حد أقصى 50
    $page     = max(1, (int)($_GET['page']  ?? 1));
    $offset   = ($page - 1) * $limit;

    // Whitelist Validation - فقط القيم المسموح بها
    $allowedSorts  = ['name', 'price', 'rating', 'created_at'];
    $allowedOrders = ['ASC', 'DESC'];
    $sort  = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
    $order = in_array(strtoupper($order), $allowedOrders, true) ? strtoupper($order) : 'DESC';

    // بناء الاستعلام مع Prepared Statements
    $where  = "WHERE p.is_active = 1";
    $params = [];

    if ($search !== '') {
        $where .= " AND (p.name LIKE :search OR p.description LIKE :search2)";
        $params['search']  = "%$search%";
        $params['search2'] = "%$search%";
    }

    if ($category > 0) {
        $where .= " AND p.category_id = :category";
        $params['category'] = $category;
    }

    // sort و order من whitelist لذا هما آمنان للإدراج المباشر
    $query = "SELECT p.*, c.name as category_name, s.store_name
              FROM products p
              JOIN categories c ON p.category_id = c.id
              JOIN sellers s ON p.seller_id = s.id
              $where
              ORDER BY p.$sort $order
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // عدد النتائج الكلي
    $countQuery = "SELECT COUNT(*) as total FROM products p $where";
    $countStmt  = $db->prepare($countQuery);
    foreach ($params as $key => $val) {
        $countStmt->bindValue(":$key", $val);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];

    // تعقيم الخرج ضد XSS
    foreach ($products as &$p) {
        $p['name']        = sanitizeOutput($p['name']);
        $p['description'] = sanitizeOutput($p['description'] ?? '');
    }

    jsonResponse([
        'success' => true,
        'data'    => $products,
        'total'   => (int)$total,
        'page'    => $page,
        'limit'   => $limit,
        'pages'   => (int)ceil($total / $limit),
    ]);
}

// ────────────────────────────────────────────────────────────────
function getProduct(int $id): void {
    $db = getDB();

    // الاستعلام الآمن - مع التحقق من is_active
    $stmt = $db->prepare("SELECT p.*, c.name as category_name, s.store_name, s.id as sid
                          FROM products p
                          JOIN categories c ON p.category_id = c.id
                          JOIN sellers s ON p.seller_id = s.id
                          WHERE p.id = :id AND p.is_active = 1");
    $stmt->execute(['id' => $id]);
    $product = $stmt->fetch();

    if (!$product) jsonError('المنتج غير موجود', 404);

    // جلب المراجعات مع تعقيم XSS
    $stmt = $db->prepare("SELECT r.*, u.username, u.first_name
                          FROM reviews r JOIN users u ON r.user_id = u.id
                          WHERE r.product_id = :pid ORDER BY r.created_at DESC");
    $stmt->execute(['pid' => $id]);
    $reviews = $stmt->fetchAll();

    // تعقيم كل المراجعات ضد XSS
    foreach ($reviews as &$r) {
        $r['title']      = sanitizeOutput($r['title'] ?? '');
        $r['comment']    = sanitizeOutput($r['comment'] ?? '');
        $r['username']   = sanitizeOutput($r['username']);
        $r['first_name'] = sanitizeOutput($r['first_name'] ?? '');
    }
    $product['reviews'] = $reviews;

    // تعقيم بيانات المنتج
    $product['name']        = sanitizeOutput($product['name']);
    $product['description'] = sanitizeOutput($product['description'] ?? '');

    jsonResponse(['success' => true, 'data' => $product]);
}

// ────────────────────────────────────────────────────────────────
function addProduct(): void {
    $user = requireAuth();
    if (!in_array($user['role'], ['seller', 'admin'])) jsonError('غير مصرح', 403);

    // التحقق من CSRF Token
    validateCSRFToken();

    $data = getRequestBody();
    $db   = getDB();

    $name        = sanitize($data['name'] ?? '');
    $description = sanitize($data['description'] ?? '');
    $price       = max(0, (float)($data['price'] ?? 0));
    $category_id = (int)($data['category_id'] ?? 0);
    $stock       = max(0, (int)($data['stock'] ?? 0));

    if (!$name || $price <= 0 || !$category_id) jsonError('البيانات المطلوبة ناقصة');

    $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $name)) . '-' . bin2hex(random_bytes(4));

    // التحقق من seller_id بشكل آمن
    $stmt = $db->prepare("SELECT id FROM sellers WHERE user_id = :uid");
    $stmt->execute(['uid' => $user['id']]);
    $seller = $stmt->fetch();
    if (!$seller) jsonError('لم يُعثر على حساب البائع', 403);

    $stmt = $db->prepare("INSERT INTO products (seller_id, category_id, name, slug, description, price, stock)
                          VALUES (:sid, :cid, :name, :slug, :desc, :price, :stock)");
    $stmt->execute([
        'sid'   => $seller['id'],
        'cid'   => $category_id,
        'name'  => $name,
        'slug'  => $slug,
        'desc'  => $description,
        'price' => $price,
        'stock' => $stock,
    ]);

    logActivity($user['id'], 'add_product', 'product', (int)$db->lastInsertId());

    jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'تم إضافة المنتج'], 201);
}

// ────────────────────────────────────────────────────────────────
function updateProduct(?int $id): void {
    if (!$id) jsonError('معرّف المنتج مطلوب', 400);
    $user = requireAuth();
    validateCSRFToken();

    $db   = getDB();
    $data = getRequestBody();

    // التحقق من ملكية المنتج (IDOR Protection)
    if ($user['role'] !== 'admin') {
        $stmt = $db->prepare("SELECT p.id FROM products p
                              JOIN sellers s ON p.seller_id = s.id
                              WHERE p.id = :pid AND s.user_id = :uid");
        $stmt->execute(['pid' => $id, 'uid' => $user['id']]);
        if (!$stmt->fetch()) jsonError('ليس لديك صلاحية لتعديل هذا المنتج', 403);
    }

    $name        = sanitize($data['name'] ?? '');
    $description = sanitize($data['description'] ?? '');
    $price       = max(0, (float)($data['price'] ?? 0));
    $stock       = max(0, (int)($data['stock'] ?? 0));

    $stmt = $db->prepare("UPDATE products SET name = :name, description = :desc,
                          price = :price, stock = :stock WHERE id = :id");
    $stmt->execute([
        'name'  => $name,
        'desc'  => $description,
        'price' => $price,
        'stock' => $stock,
        'id'    => $id,
    ]);

    logActivity($user['id'], 'update_product', 'product', $id);
    jsonResponse(['success' => true, 'message' => 'تم تحديث المنتج']);
}

// ────────────────────────────────────────────────────────────────
function deleteProduct(?int $id): void {
    if (!$id) jsonError('معرّف المنتج مطلوب', 400);
    $user = requireAuth();
    validateCSRFToken();

    $db = getDB();

    // التحقق من ملكية المنتج (IDOR Protection)
    if ($user['role'] !== 'admin') {
        $stmt = $db->prepare("SELECT p.id FROM products p
                              JOIN sellers s ON p.seller_id = s.id
                              WHERE p.id = :pid AND s.user_id = :uid");
        $stmt->execute(['pid' => $id, 'uid' => $user['id']]);
        if (!$stmt->fetch()) jsonError('ليس لديك صلاحية لحذف هذا المنتج', 403);
    }

    $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
    $stmt->execute(['id' => $id]);

    logActivity($user['id'], 'delete_product', 'product', $id);
    jsonResponse(['success' => true, 'message' => 'تم حذف المنتج']);
}
