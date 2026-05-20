<?php
$host = 'db';
$db   = 'omnidrop';
$user = 'omniuser';
$pass = 'omnipassword!!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Fetch Global Settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$sys_settings = [];
while ($row = $stmt->fetch()) {
    $sys_settings[$row['setting_key']] = $row['setting_value'];
}
$theme_color = isset($sys_settings['theme_color']) ? $sys_settings['theme_color'] : '#3b82f6';
?>
