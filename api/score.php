<?php
// api/score.php — RESTful endpoint voor scores
//   POST -> nieuwe score opslaan (de game roept dit aan na elk spel)
//   GET  -> scores uitlezen voor het scorebord
//
// Beveiliging die je tijdens de verdediging kunt uitleggen:
//   - prepared statements (parameterized queries) -> beschermt tegen SQL-injectie
//   - user_id komt uit de PHP-SESSIE, niet uit de client -> niemand kan een
//     score op naam van iemand anders posten
//   - server-side validatie -> we vertrouwen de gegevens van de client nooit
//   - correcte HTTP-statuscodes en -methodes (dat maakt het "RESTful")

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

// routeren op basis van de HTTP-methode
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        opslaanScore($pdo);
        break;
    case 'GET':
        leesScorebord($pdo);
        break;
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['fout' => 'Methode niet toegestaan']);
}

/* =====================================================================
   POST: een score opslaan
   ===================================================================== */
function opslaanScore(PDO $pdo): void
{
    // 1) ingelogd? De sessie wordt gezet door de login (deel van Student B).
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['fout' => 'Niet ingelogd']);
        return;
    }
    $userId = (int) $_SESSION['user_id'];

    // 2) de JSON-body inlezen die de game via fetch() meestuurt
    $body = json_decode(file_get_contents('php://input'), true);

    // 3) server-side validatie — alles wat van de client komt is verdacht
    $toegestaneLevels = ['beginner', 'intermediate', 'expert'];
    $level    = $body['level']    ?? '';
    $tijd     = $body['tijd']     ?? null;
    $gewonnen = $body['gewonnen'] ?? null;

    if (!in_array($level, $toegestaneLevels, true)
        || !is_int($tijd) || $tijd < 0 || $tijd > 9999
        || !is_bool($gewonnen)) {
        http_response_code(422); // Unprocessable Entity
        echo json_encode(['fout' => 'Ongeldige scoregegevens']);
        return;
    }

    // 4) wegschrijven met een prepared statement — NOOIT met string-concatenatie
    $stmt = $pdo->prepare(
        'INSERT INTO scores (user_id, level, tijd, gewonnen)
         VALUES (:user_id, :level, :tijd, :gewonnen)'
    );
    $stmt->execute([
        ':user_id'  => $userId,
        ':level'    => $level,
        ':tijd'     => $tijd,
        ':gewonnen' => $gewonnen ? 1 : 0,
    ]);

    http_response_code(201); // Created
    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
}

/* =====================================================================
   GET: het scorebord uitlezen
     api/score.php?level=beginner            -> algemeen: snelste winst per speler
     api/score.php?level=beginner&scope=mij  -> enkel jouw eigen winsten
   ===================================================================== */
function leesScorebord(PDO $pdo): void
{
    $toegestaneLevels = ['beginner', 'intermediate', 'expert'];
    $level = $_GET['level'] ?? 'beginner';
    if (!in_array($level, $toegestaneLevels, true)) {
        http_response_code(422);
        echo json_encode(['fout' => 'Ongeldig level']);
        return;
    }

    $scope = $_GET['scope'] ?? 'alle';

    if ($scope === 'mij') {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['fout' => 'Niet ingelogd']);
            return;
        }
        // al jouw winsten op dit level, snelste eerst
        $stmt = $pdo->prepare(
            'SELECT u.gebruikersnaam, s.tijd, s.gespeeld
               FROM scores s
               JOIN users u ON u.id = s.user_id
              WHERE s.level = :level AND s.gewonnen = 1
                AND s.user_id = :user_id
           ORDER BY s.tijd ASC
              LIMIT 10'
        );
        $stmt->execute([':level' => $level, ':user_id' => (int) $_SESSION['user_id']]);
    } else {
        // algemeen bord: per speler enkel zijn beste (snelste) winst
        $stmt = $pdo->prepare(
            'SELECT u.gebruikersnaam, MIN(s.tijd) AS tijd
               FROM scores s
               JOIN users u ON u.id = s.user_id
              WHERE s.level = :level AND s.gewonnen = 1
           GROUP BY u.id
           ORDER BY tijd ASC
              LIMIT 10'
        );
        $stmt->execute([':level' => $level]);
    }

    echo json_encode([
        'level'  => $level,
        'scope'  => $scope,
        'scores' => $stmt->fetchAll(),
    ]);
}
