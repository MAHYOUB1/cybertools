<?php
// ================================================================
// api/auth.php - المصادقة الآمنة
// PDO Prepared Statements + bcrypt + Rate Limiting + Session Hardening
// ================================================================
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/security.php';
setHeaders();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':    handleLogin();    break;
    case 'register': handleRegister(); break;
    case 'logout':   handleLogout();   break;
    case 'csrf':     handleCSRF();     break;
    default: jsonError('إجراء غير معروف', 400);
}

// ────────────────────────────────────────────────────────────────
function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST مطلوب', 405);

    $data     = getRequestBody();
    $username = clean($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (!$username || !$password) jsonError('يجب إدخال اسم المستخدم وكلمة المرور');

    // Rate Limiting: حماية من هجمات Brute Force
    checkRateLimit('login', getClientIP());

    $db = getDB();

    // استعلام آمن مع Prepared Statement - مستحيل حقن SQL
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // التحقق من كلمة المرور باستخدام bcrypt
    if (!$user || !password_verify($password, $user['password'])) {
        // رسالة خطأ عامة - لا نكشف إن كان اسم المستخدم صحيحاً أم لا
        jsonError('اسم المستخدم أو كلمة المرور غير صحيحة', 401);
    }

    // Session Regeneration: منع Session Fixation
    startSession();
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'       => $user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
        'role'     => $user['role'],
        'name'     => sanitizeOutput($user['first_name'] . ' ' . $user['last_name']),
    ];
    $_SESSION['_last_regen'] = time();

    // تسجيل النشاط بشكل آمن
    logActivity($user['id'], 'login', 'user', $user['id']);

    // توليد CSRF token جديد للجلسة
    $csrfToken = generateCSRFToken();

    jsonResponse([
        'success'    => true,
        'user'       => $_SESSION['user'],
        'csrf_token' => $csrfToken,
        'message'    => 'تم تسجيل الدخول بنجاح',
    ]);
}

// ────────────────────────────────────────────────────────────────
function handleRegister(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST مطلوب', 405);

    $data       = getRequestBody();
    $username   = clean($data['username']   ?? '');
    $email      = filter_var(clean($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password   = $data['password']         ?? '';
    $first_name = sanitize($data['first_name'] ?? '');
    $last_name  = sanitize($data['last_name']  ?? '');
    $phone      = clean($data['phone']      ?? '');
    $is_seller  = !empty($data['is_seller']);
    $store_name = sanitize($data['store_name'] ?? '');
    $store_desc = sanitize($data['store_desc'] ?? '');

    // التحقق من الحقول المطلوبة
    if (!$username || !$email || !$password) jsonError('الحقول المطلوبة ناقصة');
    if (!$email) jsonError('البريد الإلكتروني غير صالح');
    if (strlen($password) < PASSWORD_MIN_LENGTH) jsonError('كلمة المرور يجب أن تكون ' . PASSWORD_MIN_LENGTH . ' أحرف على الأقل');
    if ($is_seller && !$store_name) jsonError('اسم المتجر مطلوب للبائعين');

    // التحقق من صحة اسم المستخدم (أحرف وأرقام فقط)
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        jsonError('اسم المستخدم يجب أن يحتوي على أحرف إنجليزية وأرقام فقط (3-30 حرف)');
    }

    // Rate Limiting للتسجيل
    checkRateLimit('register', getClientIP());

    $db = getDB();

    // التحقق من عدم تكرار اسم المستخدم أو البريد (Prepared Statement)
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
    $stmt->execute(['username' => $username, 'email' => $email]);
    if ($stmt->fetch()) jsonError('اسم المستخدم أو البريد الإلكتروني مستخدم مسبقاً');

    // تشفير كلمة المرور بـ bcrypt
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $role = $is_seller ? 'seller' : 'customer';

    // إنشاء الحساب (Prepared Statement)
    $stmt = $db->prepare("INSERT INTO users (username, email, password, first_name, last_name, phone, role)
                          VALUES (:username, :email, :password, :first_name, :last_name, :phone, :role)");
    $stmt->execute([
        'username'   => $username,
        'email'      => $email,
        'password'   => $hashedPassword,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'phone'      => $phone,
        'role'       => $role,
    ]);
    $userId = $db->lastInsertId();

    if ($is_seller) {
        $stmt = $db->prepare("INSERT INTO sellers (user_id, store_name, store_description) VALUES (:uid, :name, :desc)");
        $stmt->execute(['uid' => $userId, 'name' => $store_name, 'desc' => $store_desc]);
    }

    // بدء الجلسة
    startSession();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int)$userId,
        'username' => $username,
        'email'    => $email,
        'role'     => $role,
        'name'     => "$first_name $last_name",
    ];
    $_SESSION['_last_regen'] = time();

    $csrfToken = generateCSRFToken();

    logActivity((int)$userId, 'register', 'user', (int)$userId);

    jsonResponse([
        'success'    => true,
        'user'       => $_SESSION['user'],
        'csrf_token' => $csrfToken,
        'message'    => 'تم إنشاء الحساب بنجاح',
    ], 201);
}

// ────────────────────────────────────────────────────────────────
function handleLogout(): void {
    startSession();
    $user = getCurrentUser();
    if ($user) {
        logActivity($user['id'], 'logout', 'user', $user['id']);
    }
    $_SESSION = [];
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'تم تسجيل الخروج']);
}

// ────────────────────────────────────────────────────────────────
function handleCSRF(): void {
    requireAuth();
    $token = generateCSRFToken();
    jsonResponse(['success' => true, 'csrf_token' => $token]);
}
