<?php

class DatabaseSeeder
{
    protected PDO $pdo;

    protected array $seeders = [
        RoleSeeder::class,
        PositionSeeder::class,
        DistrictSeeder::class,
        MuniCitySeeder::class,
        DocumentTypeSeeder::class,
        DocumentStatusSeeder::class,
        RoutingOptionSeeder::class,
        HospitalSeeder::class,
        ExternalOfficeSeeder::class,
        SourceTypeSeeder::class,
        CommunicationCategorySeeder::class,
        OpinionOfficeSeeder::class,
        OpinionStatusSeeder::class,
        MasterSeeder::class,
        TermSeeder::class,
        SpMemberSeeder::class,
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function run(?array $specificSeeders = null): void
    {
        $seedersToRun = $specificSeeders ?? $this->seeders;

        foreach ($seedersToRun as $seederClass) {
            if (!class_exists($seederClass)) {
                $file = __DIR__ . '/' . $seederClass . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            }

            if (!class_exists($seederClass)) {
                echo "SKIP: Seeder class {$seederClass} not found" . PHP_EOL;
                continue;
            }

            echo "Seeding: {$seederClass}" . PHP_EOL;
            $start = microtime(true);

            $seeder = new $seederClass($this->pdo);
            $seeder->run();

            $duration = round((microtime(true) - $start) * 1000, 2);
            echo "Seeded:  {$seederClass} ({$duration}ms)" . PHP_EOL . PHP_EOL;
        }

        echo "Database seeding completed." . PHP_EOL;
    }

    public function addSeeder(string $seederClass): void
    {
        $this->seeders[] = $seederClass;
    }

    public function getSeeders(): array
    {
        return $this->seeders;
    }
}
