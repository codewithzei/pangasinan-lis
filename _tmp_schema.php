<?php
require_once 'app/config/database.php';
$db  = new Database();
$pdo = $db->connect();
echo "user_info columns: ";
$cols = $pdo->query('SHOW COLUMNS FROM user_info')->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols) . PHP_EOL;
