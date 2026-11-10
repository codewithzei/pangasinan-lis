<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=pangasinan_lis;charset=utf8mb4','root','');
foreach (['roles','source_types','document_types'] as $t) {
    echo $t . ': ';
    foreach ($pdo->query("DESCRIBE {$t}")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        echo $c['Field'] . ' ';
    }
    echo PHP_EOL;
}
