<?php
// api/controller.php — RESTful endpoint tussen smartphone en spelpagina
//   POST -> de gsm stuurt een commando (up/down/left/right/open/flag)
//   GET  -> de spelpagina haalt het laatste commando op
//
// Omdat gsm en laptop VERSCHILLENDE toestellen zijn (geen gedeelde sessie),
// gebruiken we een koppelcode als sleutel in de tabel controller_state.
//
// Te verdedigen: dit is het toestel-naar-toestel-contact over REST. De gsm
// fungeert als sensor (kantelsensor) en stuurt zijn metingen hierheen.

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        ontvangCommando($pdo);
        break;
    case 'GET':
        leesCommando($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['fout' => 'Methode niet toegestaan']);
}

/* ---------------- POST: commando van de gsm opslaan ---------------- */
function ontvangCommando(PDO $pdo): void
{
    $body    = json_decode(file_get_contents('php://input'), true);
    $code    = $body['code']    ?? '';
    $command = $body['command'] ?? '';

    // server-side validatie: code = 4-8 cijfers, commando uit vaste lijst
    $toegestaan = ['up', 'down', 'left', 'right', 'open', 'flag'];
    if (!preg_match('/^[0-9]{4,8}$/', $code) || !in_array($command, $toegestaan, true)) {
        http_response_code(422);
        echo json_encode(['fout' => 'Ongeldig commando']);
        return;
    }

    // upsert: rij aanmaken of bijwerken, en het volgnummer verhogen.
    // Het volgnummer laat de spelpagina zien dat er een NIEUW commando is,
    // zodat eenzelfde commando niet per ongeluk twee keer wordt uitgevoerd.
    $stmt = $pdo->prepare(
        'INSERT INTO controller_state (code, command, seq)
         VALUES (:code, :command, 1)
         ON DUPLICATE KEY UPDATE command = :command2, seq = seq + 1'
    );
    $stmt->execute([
        ':code'     => $code,
        ':command'  => $command,
        ':command2' => $command,
    ]);

    echo json_encode(['ok' => true]);
}

/* ---------------- GET: laatste commando uitlezen ---------------- */
function leesCommando(PDO $pdo): void
{
    $code = $_GET['code'] ?? '';
    if (!preg_match('/^[0-9]{4,8}$/', $code)) {
        http_response_code(422);
        echo json_encode(['fout' => 'Ongeldige code']);
        return;
    }

    $stmt = $pdo->prepare('SELECT command, seq FROM controller_state WHERE code = :code');
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch();

    // nog geen commando? geef een lege beginstaat terug
    echo json_encode($row ?: ['command' => '', 'seq' => 0]);
}
