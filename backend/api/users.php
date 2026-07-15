<?php
// ================================================================
// api/users.php - إدارة المستخدمين (آمن)
// IDOR Protection + Secure File Upload + XSS Prevention
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($method) {
    case 'GET':  getUser($id);    break;
    case 'PUT':  updateUser($id); break;
    default: jsonError('Method غير مدعوم', 405);
}

// ────────────────────────────────────────────────────────────────
function getUser(?int $id): void {
    $user = requireAuth();
    $db   = getDB();

    // IDOR Protection: المستخدم يرى بياناته فقط (المسؤول يرى الجميع)
    $targetId = $id ?: $user['id'];
    if ($targetId !== $user['id'] && $user['role'] !== 'admin') {
        jsonError('غير مصرح. لا يمكنك رؤية بيانات مستخدمين آخرين.', 403);
    }

    $stmt = $db->prepare("SELECT id, username, email, first_name, last_name, phone, address, role, avatar, created_at
                          FROM users WHERE id = :id");
    $stmt->execute(['id' => $targetId]);
    $userData = $stmt->fetch();

    if (!$userData) jsonError('المستخدم غير موجود', 404);

    // تعقيم الخرج
    $userData['first_name'] = sanitizeOutput($userData['first_name'] ?? '');
    $userData['last_name']  = sanitizeOutput($userData['last_name'] ?? '');
    $userData['address']    = sanitizeOutput($userData['address'] ?? '');

    jsonResponse(['success' => true, 'data' => $userData]);
}

// ────────────────────────────────────────────────────────────────
function updateUser(?int $id): void {
    $user = requireAuth();
    validateCSRFToken();
    $db = getDB();

    // IDOR Protection: المستخدم يعدّل بياناته فقط
    $targetId = $id ?: $user['id'];
    if ($targetId !== $user['id'] && $user['role'] !== 'admin') {
        jsonError('غير مصرح. لا يمكنك تعديل بيانات مستخدمين آخرين.', 403);
    }

    $data       = getRequestBody();
    $first_name = sanitize($data['first_name'] ?? '');
    $last_name  = sanitize($data['last_name']  ?? '');
    $phone      = sanitize($data['phone']      ?? '');
    $address    = sanitize($data['address']     ?? '');

    // التحقق من صحة رقم الهاتف
    if ($phone && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        jsonError('رقم الهاتف غير صالح');
    }

    // معالجة رفع الصورة بشكل آمن
    if (isset($_FILES['avatar'])) {
        $avatar = secureUploadFile($_FILES['avatar']);
        $stmt   = $db->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
        $stmt->execute(['avatar' => $avatar, 'id' => $targetId]);
    }

    // تحديث البيانات
    $stmt = $db->prepare("UPDATE users SET first_name = :fname, last_name = :lname,
                          phone = :phone, address = :addr WHERE id = :id");
    $stmt->execute([
        'fname' => $first_name,
        'lname' => $last_name,
        'phone' => $phone,
        'addr'  => $address,
        'id'    => $targetId,
    ]);

    logActivity($user['id'], 'update_profile', 'user', $targetId);
    jsonResponse(['success' => true, 'message' => 'تم تحديث البيانات']);
}
