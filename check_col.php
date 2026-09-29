<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$pdo = new PDO(
    'mysql:host=' . $_ENV['DB_HOST'] . ';port=' . $_ENV['DB_PORT'] . ';dbname=' . $_ENV['DB_DATABASE'] . ';charset=utf8mb4',
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD']
);
$r = $pdo->query("SHOW COLUMNS FROM user_accounts WHERE Field = 'id'")->fetch(PDO::FETCH_ASSOC);
echo "user_accounts.id type: " . $r['Type'] . PHP_EOL;
