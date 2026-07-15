<?php
// ================================================================
// functions.php - دوال مساعدة مشتركة (نسخة آمنة)
// ================================================================
require_once __DIR__ . '/config.php';

// ────────────────────────────────────────────────────────────────
// HTTP Headers الآمنة
// ────────────────────────────────────────────────────────────────
function setHeaders(): void {
    // نوع المحتوى
    header('Content-Type: application/json; charset=utf-8');

    // CORS - محددة بدقة لمصدر واحد فقط
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Request-ID');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');

    // Security Headers - حماية من هجمات متعددة
    header('X-Content-Type-Options: nosniff');        // منع MIME sniffing
    header('X-Frame-Options: DENY');                   // منع Clickjacking
    header('X-XSS-Protection: 1; mode=block');         // حماية XSS إضافية
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");

    // إخفاء معلومات الخادم
    header_remove('X-Powered-By');
    header_remove('Server');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }
}

// ────────────────────────────────────────────────────────────────
// استجابات JSON
// ────────────────────────────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit();
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

// ────────────────────────────────────────────────────────────────
// إدارة الجلسات الآمنة (Session Hardening)
// ────────────────────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // إعدادات الجلسة الآمنة
        ini_set('session.cookie_httponly', '1');    // منع JS من قراءة الكوكي
        ini_set('session.cookie_samesite', 'Strict'); // منع CSRF عبر المواقع
        ini_set('session.use_strict_mode', '1');    // رفض session IDs غير معروفة
        ini_set('session.use_only_cookies', '1');   // منع session ID في URL
        ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
        ini_set('session.cookie_lifetime', (string)SESSION_LIFETIME);
        ini_set('session.sid_length', '48');        // session ID طويل وآمن
        ini_set('session.sid_bits_per_character', '6'); // تعقيد أعلى

        session_start();

        // تحديث الجلسة كل 30 دقيقة لمنع Session Fixation
        if (isset($_SESSION['_last_regen']) && time() - $_SESSION['_last_regen'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }
        if (!isset($_SESSION['_last_regen'])) {
            $_SESSION['_last_regen'] = time();
        }
    }
}

// ────────────────────────────────────────────────────────────────
// المصادقة والتفويض
// ────────────────────────────────────────────────────────────────
function getCurrentUser(): ?array {
    startSession();
    return $_SESSION['user'] ?? null;
}

function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        jsonError('غير مصرح. يجب تسجيل الدخول.', 401);
    }
    return $user;
}

function requireRole(string $role): array {
    $user = requireAuth();
    if ($user['role'] !== $role && $user['role'] !== 'admin') {
        jsonError('غير مصرح. ليس لديك صلاحية.', 403);
    }
    return $user;
}

// ────────────────────────────────────────────────────────────────
// حماية CSRF (Cross-Site Request Forgery)
// ────────────────────────────────────────────────────────────────
function generateCSRFToken(): string {
    startSession();
    $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_time']  = time();
    return $token;
}

function validateCSRFToken(): void {
    startSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
        jsonError('CSRF token غير صالح. يرجى تحديث الصفحة.', 403);
    }

    // التحقق من صلاحية التوكن (ساعة واحدة)
    if (isset($_SESSION['csrf_time']) && time() - $_SESSION['csrf_time'] > 3600) {
        jsonError('انتهت صلاحية CSRF token. يرجى تحديث الصفحة.', 403);
    }
}

// ────────────────────────────────────────────────────────────────
// Rate Limiting (حماية من Brute Force)
// ────────────────────────────────────────────────────────────────
function checkRateLimit(string $action, string $identifier): void {
    $db  = getDB();
    $key = $action . ':' . $identifier;
    $windowStart = date('Y-m-d H:i:s', time() - RATE_LIMIT_WINDOW_SECONDS);

    // حساب المحاولات في النافذة الزمنية
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM rate_limits WHERE action_key = :key AND attempted_at > :window");
    $stmt->execute(['key' => $key, 'window' => $windowStart]);
    $count = $stmt->fetch()['cnt'];

    if ($count >= RATE_LIMIT_MAX_ATTEMPTS) {
        $retryAfter = RATE_LIMIT_WINDOW_SECONDS;
        header("Retry-After: $retryAfter");
        jsonError("تم تجاوز الحد المسموح. يرجى المحاولة بعد " . ceil($retryAfter / 60) . " دقيقة.", 429);
    }

    // تسجيل المحاولة
    $stmt = $db->prepare("INSERT INTO rate_limits (action_key, ip_address, attempted_at) VALUES (:key, :ip, NOW())");
    $stmt->execute(['key' => $key, 'ip' => $_SERVER['REMOTE_ADDR']]);

    // تنظيف المحاولات القديمة (يتم عشوائياً لتقليل الحمل)
    if (mt_rand(1, 10) === 1) {
        $db->exec("DELETE FROM rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
}

// ────────────────────────────────────────────────────────────────
// تنظيف وتعقيم المدخلات
// ────────────────────────────────────────────────────────────────
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitizeOutput(string $input): string {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function clean(string $input): string {
    return trim(strip_tags($input));
}

// ────────────────────────────────────────────────────────────────
// رفع الملفات الآمن
// ────────────────────────────────────────────────────────────────
function secureUploadFile(array $file, string $dir = 'uploads/'): string {
    // التحقق من وجود أخطاء
    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonError('حدث خطأ أثناء رفع الملف.', 400);
    }

    // التحقق من حجم الملف
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        jsonError('حجم الملف يتجاوز الحد المسموح (2MB).', 400);
    }

    // التحقق من نوع MIME الحقيقي (ليس من الاسم فقط)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_UPLOAD_TYPES, true)) {
        jsonError('نوع الملف غير مسموح. الأنواع المسموحة: JPEG, PNG, GIF, WebP', 400);
    }

    // التحقق من امتداد الملف
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        jsonError('امتداد الملف غير مسموح.', 400);
    }

    // توليد اسم عشوائي فريد (UUID v4) لمنع path traversal
    $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath  = UPLOAD_DIR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonError('فشل حفظ الملف.', 500);
    }

    return $dir . $newFilename;
}

// ────────────────────────────────────────────────────────────────
// أدوات مساعدة
// ────────────────────────────────────────────────────────────────
function generateOrderNumber(): string {
    return 'ORD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function getClientIP(): string {
    // لا نثق بـ X-Forwarded-For في بيئة غير موثوقة
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getUserAgent(): string {
    return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
}

/**
 * تسجيل نشاط المستخدم بشكل آمن
 */
function logActivity(int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
                              VALUES (:uid, :action, :etype, :eid, :details, :ip, :ua)");
        $stmt->execute([
            'uid'     => $userId,
            'action'  => $action,
            'etype'   => $entityType,
            'eid'     => $entityId,
            'details' => $details,
            'ip'      => getClientIP(),
            'ua'      => getUserAgent(),
        ]);
    } catch (PDOException $e) {
        error_log('[Activity Log Error] ' . $e->getMessage());
    }
}
