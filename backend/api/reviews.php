<?php
// ================================================================
// api/reviews.php - المراجعات والتقييمات (آمن)
// XSS Protection + PDO Prepared Statements
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':  getReviews(); break;
    case 'POST': addReview();  break;
    default: jsonError('Method غير مدعوم', 405);
}

// ────────────────────────────────────────────────────────────────
function getReviews(): void {
    $product_id = (int)($_GET['product_id'] ?? 0);
    if (!$product_id) jsonError('product_id مطلوب');

    $db = getDB();

    $stmt = $db->prepare("SELECT r.*, u.username, u.first_name, u.last_name
                          FROM reviews r JOIN users u ON r.user_id = u.id
                          WHERE r.product_id = :pid
                          ORDER BY r.created_at DESC");
    $stmt->execute(['pid' => $product_id]);
    $reviews = $stmt->fetchAll();

    // تعقيم جميع المخرجات ضد XSS
    foreach ($reviews as &$row) {
        $row['title']      = sanitizeOutput($row['title'] ?? '');
        $row['comment']    = sanitizeOutput($row['comment'] ?? '');
        $row['username']   = sanitizeOutput($row['username']);
        $row['first_name'] = sanitizeOutput($row['first_name'] ?? '');
        $row['last_name']  = sanitizeOutput($row['last_name'] ?? '');
    }

    jsonResponse(['success' => true, 'data' => $reviews]);
}

// ────────────────────────────────────────────────────────────────
function addReview(): void {
    $user = requireAuth();
    validateCSRFToken();

    $data = getRequestBody();
    $db   = getDB();

    $product_id = (int)($data['product_id'] ?? 0);
    $rating     = (int)($data['rating']     ?? 0);
    $title      = sanitize($data['title']   ?? '');
    $comment    = sanitize($data['comment'] ?? '');

    // التحقق من البيانات
    if (!$product_id || !$rating || !$comment) jsonError('بيانات ناقصة');
    if ($rating < 1 || $rating > 5) jsonError('التقييم يجب أن يكون بين 1 و5');
    if (strlen($comment) > 1000) jsonError('التعليق يجب أن لا يتجاوز 1000 حرف');
    if (strlen($title) > 200) jsonError('العنوان يجب أن لا يتجاوز 200 حرف');

    // التحقق من وجود المنتج
    $stmt = $db->prepare("SELECT id FROM products WHERE id = :pid AND is_active = 1");
    $stmt->execute(['pid' => $product_id]);
    if (!$stmt->fetch()) jsonError('المنتج غير موجود', 404);

    $userId = $user['id'];

    // إضافة أو تحديث المراجعة
    $stmt = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, title, comment)
                          VALUES (:pid, :uid, :rating, :title, :comment)
                          ON DUPLICATE KEY UPDATE rating = :rating2, title = :title2, comment = :comment2");
    $stmt->execute([
        'pid'      => $product_id,
        'uid'      => $userId,
        'rating'   => $rating,
        'title'    => $title,
        'comment'  => $comment,
        'rating2'  => $rating,
        'title2'   => $title,
        'comment2' => $comment,
    ]);

    // تحديث متوسط التقييم
    $stmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM reviews WHERE product_id = :pid");
    $stmt->execute(['pid' => $product_id]);
    $avg = $stmt->fetch();

    $stmt = $db->prepare("UPDATE products SET rating = :avg, reviews_count = :cnt WHERE id = :pid");
    $stmt->execute([
        'avg' => round($avg['avg_rating'], 2),
        'cnt' => $avg['cnt'],
        'pid' => $product_id,
    ]);

    logActivity($userId, 'add_review', 'product', $product_id);
    jsonResponse(['success' => true, 'message' => 'تم إضافة المراجعة'], 201);
}
