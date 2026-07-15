<?php
// ================================================================
// api/payments.php - معالجة المدفوعات (آمن)
// لا تخزين بيانات بطاقات + CSRF + IDOR Protection
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST مطلوب', 405);

$user = requireAuth();
validateCSRFToken();

$data = getRequestBody();
$db   = getDB();

$order_id    = (int)($data['order_id']    ?? 0);
$method      = $data['method']            ?? 'credit_card';
$card_number = preg_replace('/\D/', '', $data['card_number'] ?? '');
$card_holder = sanitize($data['card_holder'] ?? '');
$card_expiry = sanitize($data['card_expiry'] ?? '');
$card_cvv    = $data['card_cvv']          ?? '';

if (!$order_id) jsonError('رقم الطلب مطلوب');

// التحقق من صحة بيانات البطاقة
if (strlen($card_number) < 13 || strlen($card_number) > 19) jsonError('رقم البطاقة غير صالح');
if (strlen($card_cvv) < 3 || strlen($card_cvv) > 4) jsonError('CVV غير صالح');

// IDOR Protection: التحقق أن الطلب يخص المستخدم الحالي
$stmt = $db->prepare("SELECT * FROM orders WHERE id = :oid AND user_id = :uid");
$stmt->execute(['oid' => $order_id, 'uid' => $user['id']]);
$order = $stmt->fetch();

if (!$order) jsonError('الطلب غير موجود أو ليس لديك صلاحية', 404);
if ($order['status'] !== 'pending') jsonError('هذا الطلب تم معالجته مسبقاً');

// محاكاة معالجة الدفع
$success = simulatePayment($card_number, $card_holder);

$status = $success ? 'success' : 'failed';
$txnId  = 'TXN-' . strtoupper(bin2hex(random_bytes(8)));
$last4  = substr($card_number, -4);

// تخزين آخر 4 أرقام فقط - لا نخزّن رقم البطاقة الكامل أبداً
$stmt = $db->prepare("INSERT INTO payments (order_id, user_id, amount, method, status, card_last_four, card_holder, transaction_id)
                      VALUES (:oid, :uid, :amount, :method, :status, :last4, :holder, :txn)");
$stmt->execute([
    'oid'    => $order_id,
    'uid'    => $user['id'],
    'amount' => $order['total'],
    'method' => $method,
    'status' => $status,
    'last4'  => $last4,
    'holder' => $card_holder,
    'txn'    => $txnId,
]);

if ($success) {
    $stmt = $db->prepare("UPDATE orders SET status = 'processing' WHERE id = :oid");
    $stmt->execute(['oid' => $order_id]);

    logActivity($user['id'], 'payment_success', 'order', $order_id, "TXN: $txnId");

    jsonResponse([
        'success'        => true,
        'transaction_id' => $txnId,
        'status'         => 'success',
        'message'        => 'تم الدفع بنجاح',
        'amount'         => $order['total'],
    ]);
} else {
    logActivity($user['id'], 'payment_failed', 'order', $order_id);
    jsonError('فشل الدفع. يرجى التحقق من بيانات البطاقة.', 402);
}

// محاكاة بوابة الدفع (تعليمي)
function simulatePayment(string $cardNumber, string $holder): bool {
    $validCards = ['4532015112830366', '5425233430109903', '374245455400126'];
    return in_array($cardNumber, $validCards);
}
