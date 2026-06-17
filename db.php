<?php
// includes/db.php — herbruikbare databaseverbinding (PDO)
// Elk PHP-bestand dat de database nodig heeft, doet bovenaan:
//   require_once __DIR__ . '/../includes/db.php';
// Daarna is $pdo beschikbaar.

$DB_HOST = 'localhost';
$DB_NAME = 'minesweeper';
$DB_USER = 'root';     // standaardgebruiker bij XAMPP — pas aan voor jouw setup
$DB_PASS = '';         // bij XAMPP standaard leeg

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            // fouten als exceptions i.p.v. stille mislukkingen
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // resultaten als associatieve arrays (kolomnaam => waarde)
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // echte prepared statements i.p.v. emulatie -> sterkere bescherming
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['fout' => 'Databaseverbinding mislukt']);
    exit;
}
