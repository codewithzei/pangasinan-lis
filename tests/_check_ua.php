<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4','root','');
foreach ($pdo->query("DESCRIBE user_accounts")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . ' ';
}
echo PHP_EOL;
