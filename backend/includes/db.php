<?php
// ================================================================
// db.php - اتصال قاعدة البيانات الآمن (PDO)
// يستخدم PDO حصرياً مع Prepared Statements
// ================================================================
require_once __DIR__ . '/config.php';

/**
 * إنشاء اتصال PDO آمن مع إعدادات محكمة
 * - PDO يمنع SQL Injection تلقائياً عبر Prepared Statements
 * - ERRMODE_EXCEPTION يمنع تسرب أخطاء DB للمستخدم
 * - EMULATE_PREPARES=false يضمن Prepared Statements حقيقية على مستوى MySQL
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // Prepared Statements حقيقية
                PDO::ATTR_PERSISTENT         => true,   // Connection pooling
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            // تسجيل الخطأ داخلياً فقط - لا نكشف تفاصيل DB للمستخدم
            error_log('[DB ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات'], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
    return $pdo;
}

/**
 * تنفيذ استعلام آمن مع Prepared Statements
 * @param string $sql    الاستعلام مع placeholders (:name أو ?)
 * @param array  $params المعاملات للربط الآمن
 * @return PDOStatement
 */
function safeQuery(string $sql, array $params = []): PDOStatement {
    $db   = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
