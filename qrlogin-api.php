<?php
// api/qrlogin.php — RESTful endpoint voor inloggen via QR-code
//   POST {action:"create"}          -> laptop: maakt een nieuw token (wachtend)
//   POST {action:"approve", token}  -> gsm: koppelt het token aan de ingelogde gsm-gebruiker
//   GET  ?token=...                  -> laptop: vraagt de status op (pollen)
//   POST {action:"claim", token}    -> laptop: logt zichzelf in zodra goedgekeurd
//
// Beveiliging om te verdedigen:
//   - token is willekeurig en niet te raden (random_bytes) en is eenmalig
//   - een token is maar 5 minuten geldig (anders verlopen)
//   - enkel een INGELOGDE gsm kan goedkeuren -> de goedkeurder is geauthenticeerd
//   - prepared statements overal

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

const QR_GELDIG_SEC = 300; // 5 minuten

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    status($pdo);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $actie = $body['action'] ?? '';
    switch ($actie) {
        case 'create':  maakToken($pdo);         break;
        case 'approve': keurGoed($pdo, $body);   break;
        case 'claim':   claim($pdo, $body);      break;
        default:
            http_response_code(422);
            echo json_encode(['fout' => 'Onbekende actie']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['fout' => 'Methode niet toegestaan']);


/* ---------------- laptop: nieuw token ---------------- */
function maakToken(PDO $pdo): void
{
    $token = bin2hex(random_bytes(16)); // 32 hex-tekens, niet te raden
    $stmt = $pdo->prepare('INSERT INTO qr_login (token, status) VALUES (:t, "wachtend")');
    $stmt->execute([':t' => $token]);
    echo json_encode(['token' => $token]);
}

/* ---------------- laptop: status pollen ---------------- */
function status(PDO $pdo): void
{
    $token = $_GET['token'] ?? '';
    if (!geldigToken($token)) { http_response_code(422); echo json_encode(['fout' => 'Ongeldig token']); return; }

    // QR_GELDIG_SEC is een eigen int-constante (geen invoer) -> veilig in de query
    $stmt = $pdo->prepare(
        'SELECT status FROM qr_login
          WHERE token = :t AND aangemaakt > (NOW() - INTERVAL ' . QR_GELDIG_SEC . ' SECOND)'
    );
    $stmt->execute([':t' => $token]);
    $row = $stmt->fetch();

    echo json_encode(['status' => $row ? $row['status'] : 'verlopen']);
}

/* ---------------- gsm: token goedkeuren ---------------- */
function keurGoed(PDO $pdo, array $body): void
{
    // alleen een ingelogde gsm-gebruiker mag goedkeuren
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['fout' => 'Log eerst in op je gsm']);
        return;
    }
    $token = $body['token'] ?? '';
    if (!geldigToken($token)) { http_response_code(422); echo json_encode(['fout' => 'Ongeldig token']); return; }

    // enkel een wachtend, niet-verlopen token koppelen aan deze gebruiker
    $stmt = $pdo->prepare(
        'UPDATE qr_login
            SET user_id = :uid, status = "goedgekeurd"
          WHERE token = :t AND status = "wachtend"
            AND aangemaakt > (NOW() - INTERVAL ' . QR_GELDIG_SEC . ' SECOND)'
    );
    $stmt->execute([':uid' => (int) $_SESSION['user_id'], ':t' => $token]);

    if ($stmt->rowCount() === 0) {
        http_response_code(409);
        echo json_encode(['fout' => 'Token onbekend, al gebruikt of verlopen']);
        return;
    }
    echo json_encode(['ok' => true]);
}

/* ---------------- laptop: token claimen (= inloggen) ---------------- */
function claim(PDO $pdo, array $body): void
{
    $token = $body['token'] ?? '';
    if (!geldigToken($token)) { http_response_code(422); echo json_encode(['fout' => 'Ongeldig token']); return; }

    // de gekoppelde gebruiker ophalen
    $stmt = $pdo->prepare(
        'SELECT q.user_id, u.gebruikersnaam
           FROM qr_login q JOIN users u ON u.id = q.user_id
          WHERE q.token = :t AND q.status = "goedgekeurd"
            AND q.aangemaakt > (NOW() - INTERVAL ' . QR_GELDIG_SEC . ' SECOND)'
    );
    $stmt->execute([':t' => $token]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(409); echo json_encode(['fout' => 'Nog niet goedgekeurd of verlopen']); return; }

    // eenmalig maken: enkel claimen als het nog "goedgekeurd" staat (voorkomt dubbel gebruik)
    $upd = $pdo->prepare('UPDATE qr_login SET status = "geclaimd" WHERE token = :t AND status = "goedgekeurd"');
    $upd->execute([':t' => $token]);
    if ($upd->rowCount() === 0) { http_response_code(409); echo json_encode(['fout' => 'Al geclaimd']); return; }

    // de laptop inloggen
    session_regenerate_id(true);
    $_SESSION['user_id']        = (int) $row['user_id'];
    $_SESSION['gebruikersnaam'] = $row['gebruikersnaam'];
    echo json_encode(['ok' => true, 'gebruikersnaam' => $row['gebruikersnaam']]);
}

function geldigToken($t): bool
{
    return is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t);
}
