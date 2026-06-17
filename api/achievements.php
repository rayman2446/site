<?php
// api/achievements.php — RESTful endpoint voor achievements
//   GET -> de volledige lijst met ontgrendeld-status en voortgang

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/achievements.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['fout' => 'Niet ingelogd']);
    exit;
}

// evalueert tegen je scores en slaat nieuw behaalde achievements meteen op
$resultaat = evalueerAchievements($pdo, (int) $_SESSION['user_id']);
echo json_encode($resultaat);
