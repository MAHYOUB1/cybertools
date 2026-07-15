<?php
/**
 * init-passwords.php - يتم تشغيله مرة واحدة عند بدء الحاوية
 * يحوّل كلمات المرور النصية إلى bcrypt hashes
 */
$maxRetries = 30;
$retry = 0;

while ($retry < $maxRetries) {
    try {
        $pdo = new PDO(
            'mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';port=3306;dbname=cybertools;charset=utf8mb4',
            getenv('DB_USER') ?: 'cyberuser',
            getenv('DB_PASS') ?: 'cyberpass123',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        break;
    } catch (PDOException $e) {
        $retry++;
        echo "Waiting for database... ($retry/$maxRetries)\n";
        sleep(2);
    }
}

if ($retry >= $maxRetries) {
    echo "ERROR: Could not connect to database\n";
    exit(1);
}

echo "Connected to database. Updating passwords to bcrypt...\n";

$passwords = [
    'admin'      => 'admin123',
    'seller1'    => 'seller123',
    'seller2'    => 'seller123',
    'seller3'    => 'seller123',
    'customer1'  => 'pass123',
    'customer2'  => 'pass123',
    'customer3'  => 'pass123',
    'customer4'  => 'pass123',
    'customer5'  => 'pass123',
    'customer6'  => 'pass123',
    'customer7'  => 'pass123',
    'customer8'  => 'pass123',
    'customer9'  => 'pass123',
    'customer10' => 'pass123',
];

$stmt = $pdo->prepare("UPDATE users SET password = :hash WHERE username = :username");

foreach ($passwords as $username => $plainPassword) {
    $hash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt->execute(['hash' => $hash, 'username' => $username]);
    echo "  ✓ Updated: $username\n";
}

echo "All passwords updated to bcrypt successfully!\n";
