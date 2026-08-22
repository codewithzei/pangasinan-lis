<?php

require_once __DIR__ . '/app/config/database.php';

$database = new Database();
$pdo = $database->connect();

echo "Database connected successfully." . PHP_EOL . PHP_EOL;

createMigrationsTable($pdo);

$command = $argv[1] ?? 'migrate';
$migrationsDir = __DIR__ . '/database/migrations';
$seedersDir = __DIR__ . '/database/seeders';
$option = $argv[2] ?? null;

switch ($command) {
    case 'migrate':
        runMigrations($pdo, $migrationsDir);
        if ($option === '--seed') {
            echo PHP_EOL;
            runSeeders($pdo, $seedersDir);
        }
        break;
    case 'rollback':
        rollbackLastMigration($pdo, $migrationsDir);
        break;
    case 'status':
        showMigrationStatus($pdo, $migrationsDir);
        break;
    case 'fresh':
        freshMigrations($pdo, $migrationsDir);
        if ($option === '--seed') {
            echo PHP_EOL;
            runSeeders($pdo, $seedersDir);
        }
        break;
    case 'seed':
        runSeeders($pdo, $seedersDir, $option);
        break;
    case 'seed:list':
        listSeeders($pdo, $seedersDir);
        break;
    default:
        echo "Unknown command: {$command}" . PHP_EOL;
        echo "Usage:" . PHP_EOL;
        echo "  php migrate.php migrate [--seed]" . PHP_EOL;
        echo "  php migrate.php rollback" . PHP_EOL;
        echo "  php migrate.php status" . PHP_EOL;
        echo "  php migrate.php fresh [--seed]" . PHP_EOL;
        echo "  php migrate.php seed [SeederClassName]" . PHP_EOL;
        echo "  php migrate.php seed:list" . PHP_EOL;
        exit(1);
}

function createMigrationsTable(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        batch INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
}

function getPendingMigrations(PDO $pdo, string $migrationsDir): array
{
    $files = glob($migrationsDir . '/*.php');
    natsort($files);

    $stmt = $pdo->query("SELECT migration FROM migrations");
    $ran = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pending = [];
    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (!in_array($name, $ran)) {
            $pending[] = $file;
        }
    }

    return $pending;
}

function getNextBatch(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations");
    return (int) $stmt->fetchColumn();
}

function runMigrations(PDO $pdo, string $migrationsDir): void
{
    $pending = getPendingMigrations($pdo, $migrationsDir);

    if (empty($pending)) {
        echo "Nothing to migrate." . PHP_EOL;
        return;
    }

    $batch = getNextBatch($pdo);

    foreach ($pending as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $className = deriveClassName($name);

        require_once $file;

        if (!class_exists($className)) {
            echo "SKIP: Class {$className} not found in {$name}.php" . PHP_EOL;
            continue;
        }

        echo "Migrating: {$name}" . PHP_EOL;

        $migration = new $className();
        $start = microtime(true);

        $migration->up($pdo);

        $stmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->execute([$name, $batch]);

        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "Migrated:  {$name} ({$duration}ms)" . PHP_EOL . PHP_EOL;
    }

    echo "Migration completed." . PHP_EOL;
}

function rollbackLastMigration(PDO $pdo, string $migrationsDir): void
{
    $stmt = $pdo->query("SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last) {
        echo "Nothing to rollback." . PHP_EOL;
        return;
    }

    $batch = $last['batch'];
    $stmt = $pdo->prepare("SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC");
    $stmt->execute([$batch]);
    $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($migrations as $name) {
        $file = $migrationsDir . '/' . $name . '.php';
        $className = deriveClassName($name);

        if (!file_exists($file)) {
            echo "SKIP: Migration file {$name}.php not found" . PHP_EOL;
            continue;
        }

        require_once $file;

        if (!class_exists($className)) {
            echo "SKIP: Class {$className} not found in {$name}.php" . PHP_EOL;
            continue;
        }

        echo "Rolling back: {$name}" . PHP_EOL;

        $migration = new $className();
        $start = microtime(true);

        $migration->down($pdo);

        $stmt = $pdo->prepare("DELETE FROM migrations WHERE migration = ?");
        $stmt->execute([$name]);

        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "Rolled back:  {$name} ({$duration}ms)" . PHP_EOL . PHP_EOL;
    }

    echo "Rollback completed." . PHP_EOL;
}

function showMigrationStatus(PDO $pdo, string $migrationsDir): void
{
    $files = glob($migrationsDir . '/*.php');
    natsort($files);

    $stmt = $pdo->query("SELECT migration, batch FROM migrations ORDER BY id");
    $ran = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    echo "+------------------------------------------+---------+-------+" . PHP_EOL;
    echo "| Migration                                | Status  | Batch |" . PHP_EOL;
    echo "+------------------------------------------+---------+-------+" . PHP_EOL;

    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $status = isset($ran[$name]) ? "Ran" : "Pending";
        $batch = $ran[$name] ?? "-";
        printf("| %-40s | %-7s | %-5s |%s", $name, $status, $batch, PHP_EOL);
    }

    echo "+------------------------------------------+---------+-------+" . PHP_EOL;
}

function freshMigrations(PDO $pdo, string $migrationsDir): void
{
    echo "Dropping all tables..." . PHP_EOL;

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "Dropped: {$table}" . PHP_EOL;
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo PHP_EOL . "Running all migrations..." . PHP_EOL . PHP_EOL;
    createMigrationsTable($pdo);
    runMigrations($pdo, $migrationsDir);
}

function deriveClassName(string $fileName): string
{
    $parts = explode('_', $fileName);
    array_shift($parts);
    return implode('', array_map('ucfirst', $parts));
}

function loadSeederClasses(string $seedersDir): void
{
    $baseClass = $seedersDir . '/Seeder.php';
    if (file_exists($baseClass)) {
        require_once $baseClass;
    }

    $files = glob($seedersDir . '/*.php');
    foreach ($files as $file) {
        if (basename($file) === 'Seeder.php') {
            continue;
        }
        require_once $file;
    }
}

function runSeeders(PDO $pdo, string $seedersDir, ?string $specificSeeder = null): void
{
    loadSeederClasses($seedersDir);

    $dbSeeder = new DatabaseSeeder($pdo);

    if ($specificSeeder !== null) {
        if (strpos($specificSeeder, '.php') !== false) {
            $specificSeeder = pathinfo($specificSeeder, PATHINFO_FILENAME);
        }
        echo "Running specific seeder: {$specificSeeder}" . PHP_EOL . PHP_EOL;
        $dbSeeder->run([$specificSeeder]);
        return;
    }

    $dbSeeder->run();
}

function listSeeders(PDO $pdo, string $seedersDir): void
{
    loadSeederClasses($seedersDir);
    $dbSeeder = new DatabaseSeeder($pdo);
    $seeders = $dbSeeder->getSeeders();

    echo "+------------------------------------------+" . PHP_EOL;
    echo "| Registered Seeders                       |" . PHP_EOL;
    echo "+------------------------------------------+" . PHP_EOL;

    foreach ($seeders as $seeder) {
        printf("| %-40s |%s", $seeder, PHP_EOL);
    }

    echo "+------------------------------------------+" . PHP_EOL;
    echo "Total: " . count($seeders) . " seeder(s)" . PHP_EOL;
}
