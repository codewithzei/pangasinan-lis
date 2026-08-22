<?php

require_once __DIR__ . '/../config/database.php';

class TestController
{
    public function database(): void
    {
        $database = new Database();
        $pdo = $database->connect();

        echo '<h1>Database Connected</h1>';

        echo '<p>MySQL connection is working.</p>';
    }
}