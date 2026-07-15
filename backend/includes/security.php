<?php
// ================================================================
// security.php - طبقة الأمان المتقدمة + API Gateway
// تشفير AES-256-GCM + حماية Replay + Request Validation
// ================================================================
require_once __DIR__ . '/config.php';

/**
 * ══════════════════════════════════════════════════════════════
 * تشفير AES-256-GCM
 * يُستخدم لتشفير الـ Payload بين الفرونت إند والباك إند
 * مما يجعل البيانات غير مقروءة في Network Tab
 * ══════════════════════════════════════════════════════════════
 */
class SecureEncryption {
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    /**
     * تشفير البيانات
     * @param string $plaintext النص المراد تشفيره
     * @return string Base64(nonce + tag + ciphertext)
     */
    public static function encrypt(string $plaintext): string {
        $key   = hex2bin(ENCRYPTION_KEY);
        $nonce = random_bytes(12); // 96-bit nonce لـ GCM
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext, self::CIPHER, $key,
            OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('فشل التشفير');
        }

        // nonce (12 bytes) + tag (16 bytes) + ciphertext
        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * فك التشفير
     * @param string $encoded Base64(nonce + tag + ciphertext)
     * @return string|false النص الأصلي أو false عند الفشل
     */
    public static function decrypt(string $encoded): string|false {
        $key  = hex2bin(ENCRYPTION_KEY);
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 28) return false;

        $nonce      = substr($data, 0, 12);
        $tag        = substr($data, 12, self::TAG_LENGTH);
        $ciphertext = substr($data, 12 + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext, self::CIPHER, $key,
            OPENSSL_RAW_DATA, $nonce, $tag
        );

        return $plaintext;
    }
}

/**
 * ══════════════════════════════════════════════════════════════
 * خريطة عمليات API Gateway
 * بدلاً من كشف مسارات API الحقيقية، نستخدم معرّفات مشفرة
 * ══════════════════════════════════════════════════════════════
 */
class APIGateway {
    /**
     * خريطة Operation IDs → الملفات والدوال الحقيقية
     * المطور يرى فقط "op: a1b2c3" في Network Tab
     */
    private static array $operations = [
        // Auth
        'ax01' => ['file' => 'auth.php',           'params' => ['action' => 'login']],
        'ax02' => ['file' => 'auth.php',           'params' => ['action' => 'register']],
        'ax03' => ['file' => 'auth.php',           'params' => ['action' => 'logout']],
        'ax04' => ['file' => 'auth.php',           'params' => ['action' => 'csrf']],
        // Products
        'px01' => ['file' => 'products.php',       'params' => []],
        'px02' => ['file' => 'products.php',       'params' => []], // single product by id
        // Cart
        'cx01' => ['file' => 'cart.php',           'params' => []],
        // Orders
        'ox01' => ['file' => 'orders.php',         'params' => []],
        // Payments
        'fx01' => ['file' => 'payments.php',       'params' => []],
        // Reviews
        'rx01' => ['file' => 'reviews.php',        'params' => []],
        // Users
        'ux01' => ['file' => 'users.php',          'params' => []],
        // Admin
        'dx01' => ['file' => 'admin/stats.php',    'params' => []],
        'dx02' => ['file' => 'admin/stats.php',    'params' => ['action' => 'users']],
        'dx03' => ['file' => 'admin/stats.php',    'params' => ['action' => 'ping']],
        // Seller
        'sx01' => ['file' => 'seller/stats.php',   'params' => []],
    ];

    /**
     * معالجة الطلب المشفر من الفرونت إند
     */
    public static function handleRequest(): void {
        $rawInput = file_get_contents('php://input');
        $envelope = json_decode($rawInput, true);

        if (!$envelope || !isset($envelope['op'])) {
            // طلب عادي (غير مشفر) - للتوافق مع الطلبات المباشرة
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid request format']);
            exit();
        }

        $opId      = $envelope['op'] ?? '';
        $encrypted = $envelope['d']  ?? '';
        $nonce     = $envelope['n']  ?? '';
        $timestamp = $envelope['t']  ?? 0;

        // التحقق من Operation ID
        if (!isset(self::$operations[$opId])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown operation']);
            exit();
        }

        // حماية Anti-Replay: رفض الطلبات الأقدم من 5 دقائق
        if (abs(time() - intval($timestamp)) > 300) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Request expired']);
            exit();
        }

        // فك تشفير البيانات إن وجدت
        $payload = [];
        if (!empty($encrypted)) {
            $decrypted = SecureEncryption::decrypt($encrypted);
            if ($decrypted === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Decryption failed']);
                exit();
            }
            $payload = json_decode($decrypted, true) ?: [];
        }

        $operation = self::$operations[$opId];

        // إعداد المتغيرات العامة للملف المستهدف
        $_GET    = array_merge($_GET, $operation['params'], $payload['query'] ?? []);
        $_POST   = $payload['body'] ?? [];
        $_SERVER['REQUEST_METHOD'] = strtoupper($payload['method'] ?? $_SERVER['REQUEST_METHOD']);

        // تحويل body إلى php://input mock
        if (!empty($payload['body'])) {
            $GLOBALS['_gateway_body'] = json_encode($payload['body']);
        }

        // تضمين الملف المستهدف
        $filePath = __DIR__ . '/../api/' . $operation['file'];
        if (file_exists($filePath)) {
            require $filePath;
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Service not found']);
        }
        exit();
    }

    /**
     * تشفير الاستجابة قبل إرسالها
     */
    public static function encryptResponse(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        return json_encode([
            'e' => SecureEncryption::encrypt($json),
            't' => time(),
        ]);
    }
}

/**
 * ══════════════════════════════════════════════════════════════
 * قراءة body الطلب - تدعم الطلبات العادية والمشفرة عبر Gateway
 * ══════════════════════════════════════════════════════════════
 */
function getRequestBody(): array {
    if (isset($GLOBALS['_gateway_body'])) {
        return json_decode($GLOBALS['_gateway_body'], true) ?: [];
    }
    return json_decode(file_get_contents('php://input'), true) ?: [];
}
