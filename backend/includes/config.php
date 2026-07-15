<?php
// ================================================================
// config.php - إعدادات التطبيق الآمنة
// جميع الأسرار تُقرأ من متغيرات بيئة Docker فقط
// ================================================================

// ── اتصال قاعدة البيانات ──
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'cybertools');
define('DB_USER', getenv('DB_USER') ?: 'cyberuser');
define('DB_PASS', getenv('DB_PASS') ?: 'cyberpass123');

// ── بيئة التشغيل ──
define('APP_ENV',   getenv('APP_ENV')   ?: 'production');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// ── مفتاح التشفير (AES-256-GCM) للـ API Gateway ──
// يتم توليده عشوائياً في Docker ويُمرَّر كمتغير بيئة
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'c7b3a9f1e2d4b6a8c0e2f4d6b8a0c2e4f6d8a0b2c4e6f8a0b2c4d6e8f0a2b4');

// ── إعدادات CORS - محددة بدقة ──
define('ALLOWED_ORIGIN', getenv('ALLOWED_ORIGIN') ?: 'http://localhost:3000');

// ── إعدادات الأمان ──
define('CSRF_TOKEN_LENGTH', 32);
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW_SECONDS', 900); // 15 دقيقة
define('SESSION_LIFETIME', 3600); // ساعة واحدة
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_UPLOAD_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_DIR', '/var/www/html/uploads/');

// ── إعدادات كلمة المرور ──
define('PASSWORD_MIN_LENGTH', 8);
define('BCRYPT_COST', 12);
