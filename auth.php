<?php
// api/auth.php — RESTful endpoint voor accounts
//   POST {action:"register"} -> nieuw account aanmaken (en meteen inloggen)
//   POST {action:"login"}    -> inloggen, zet de sessie
//   POST {action:"logout"}   -> uitloggen
//   GET                      -> "wie ben ik?" (huidige sessie)
//
// Beveiliging om te verdedigen:
//   - wachtwoorden via password_hash() / password_verify() (bcrypt) — nooit
//     in platte tekst opgeslagen
//   - prepared statements tegen SQL-injectie
//   - session_regenerate_id() na login -> beschermt tegen session fixation
//   - login geeft dezelfde fout of de naam nu bestaat of niet -> lekt niet
//     welke gebruikersnamen bestaan

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    wieBenIk();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $actie = $body['action'] ?? '';

    switch ($actie) {
        case 'register': registreer($pdo, $body); break;
        case 'login':    login($pdo, $body);      break;
        case 'logout':   logout();                break;
        default:
            http_response_code(422);
            echo json_encode(['fout' => 'Onbekende actie']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['fout' => 'Methode niet toegestaan']);


/* ---------------- registreren ---------------- */
function registreer(PDO $pdo, array $body): void
{
    $naam = trim($body['gebruikersnaam'] ?? '');
    $ww   = $body['wachtwoord'] ?? '';

    // server-side validatie
    if (mb_strlen($naam) < 3 || mb_strlen($naam) > 40) {
        http_response_code(422);
        echo json_encode(['fout' => 'Gebruikersnaam moet 3 tot 40 tekens zijn']);
        return;
    }
    if (strlen($ww) < 6) {
        http_response_code(422);
        echo json_encode(['fout' => 'Wachtwoord moet minstens 6 tekens zijn']);
        return;
    }

    // bestaat de naam al?
    $check = $pdo->prepare('SELECT id FROM users WHERE gebruikersnaam = :naam');
    $check->execute([':naam' => $naam]);
    if ($check->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(['fout' => 'Die gebruikersnaam is al in gebruik']);
        return;
    }

    // wachtwoord hashen en het account opslaan
    $hash = password_hash($ww, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (gebruikersnaam, wachtwoord_hash) VALUES (:naam, :hash)'
    );
    $stmt->execute([':naam' => $naam, ':hash' => $hash]);

    // meteen ingelogd na registratie
    session_regenerate_id(true);
    $_SESSION['user_id']        = (int) $pdo->lastInsertId();
    $_SESSION['gebruikersnaam'] = $naam;

    http_response_code(201); // Created
    echo json_encode(['ok' => true, 'gebruikersnaam' => $naam]);
}

/* ---------------- inloggen ---------------- */
function login(PDO $pdo, array $body): void
{
    $naam = trim($body['gebruikersnaam'] ?? '');
    $ww   = $body['wachtwoord'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT id, gebruikersnaam, wachtwoord_hash FROM users WHERE gebruikersnaam = :naam'
    );
    $stmt->execute([':naam' => $naam]);
    $user = $stmt->fetch();

    // bewust één algemene foutmelding (geen hint of de naam bestaat)
    if (!$user || !password_verify($ww, $user['wachtwoord_hash'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['fout' => 'Verkeerde gebruikersnaam of wachtwoord']);
        return;
    }

    session_regenerate_id(true); // nieuw sessie-id na login
    $_SESSION['user_id']        = (int) $user['id'];
    $_SESSION['gebruikersnaam'] = $user['gebruikersnaam'];

    echo json_encode(['ok' => true, 'gebruikersnaam' => $user['gebruikersnaam']]);
}

/* ---------------- uitloggen ---------------- */
function logout(): void
{
    $_SESSION = [];
    session_destroy();
    echo json_encode(['ok' => true]);
}

/* ---------------- huidige sessie ---------------- */
function wieBenIk(): void
{
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'ingelogd'       => true,
            'gebruikersnaam' => $_SESSION['gebruikersnaam'] ?? '',
        ]);
    } else {
        echo json_encode(['ingelogd' => false]);
    }
}
