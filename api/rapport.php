<?php
// api/rapport.php — genereert een statistiekenrapport-PDF (Student B)
// PHP-functionaliteit voorbij lecture 5: PDF-generatie met FPDF, gevoed door
// een SQL-aggregatie van je eigen scores.
//
// Installatie: download FPDF van fpdf.org en plaats fpdf.php in lib/fpdf/.

session_start();
require_once __DIR__ . '/../includes/db.php';
require __DIR__ . '/../lib/fpdf/fpdf.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Niet ingelogd');
}
$userId = (int) $_SESSION['user_id'];
$naam   = $_SESSION['gebruikersnaam'] ?? 'Speler';

// statistieken per niveau ophalen (aggregatie in SQL)
$stmt = $pdo->prepare(
    'SELECT level,
            COUNT(*)                                  AS gespeeld,
            SUM(gewonnen)                             AS gewonnen,
            MIN(CASE WHEN gewonnen = 1 THEN tijd END) AS beste
       FROM scores
      WHERE user_id = :uid
      GROUP BY level'
);
$stmt->execute([':uid' => $userId]);

$perLevel = [];
foreach ($stmt->fetchAll() as $r) $perLevel[$r['level']] = $r;

function txt(string $s): string { return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'); }
function tijdStr($s): string {
    if ($s === null) return '-';
    return floor($s / 60) . ':' . str_pad($s % 60, 2, '0', STR_PAD_LEFT);
}
$labels = ['beginner' => 'Beginner', 'intermediate' => 'Gevorderd', 'expert' => 'Expert'];

$pdf = new FPDF('P', 'mm', 'A4'); // portret
$pdf->AddPage();

// kop
$pdf->SetFont('Arial', 'B', 22); $pdf->SetTextColor(27, 31, 42);
$pdf->Cell(0, 12, txt('Mijn scores - rapport'), 0, 1);
$pdf->SetFont('Arial', '', 12); $pdf->SetTextColor(90, 100, 115);
$pdf->Cell(0, 8, txt("Speler: $naam"), 0, 1);
$pdf->Cell(0, 8, txt('Gegenereerd op ' . date('d/m/Y')), 0, 1);
$pdf->Ln(6);

// tabelkop
$pdf->SetFont('Arial', 'B', 11); $pdf->SetFillColor(62, 207, 142); $pdf->SetTextColor(255, 255, 255);
$pdf->Cell(50, 10, txt('Niveau'),    1, 0, 'L', true);
$pdf->Cell(35, 10, txt('Gespeeld'),  1, 0, 'C', true);
$pdf->Cell(35, 10, txt('Gewonnen'),  1, 0, 'C', true);
$pdf->Cell(35, 10, txt('Winratio'),  1, 0, 'C', true);
$pdf->Cell(35, 10, txt('Beste tijd'),1, 1, 'C', true);

// rijen in vaste volgorde
$pdf->SetFont('Arial', '', 11); $pdf->SetTextColor(27, 31, 42);
foreach (['beginner', 'intermediate', 'expert'] as $lvl) {
    $r        = $perLevel[$lvl] ?? ['gespeeld' => 0, 'gewonnen' => 0, 'beste' => null];
    $gespeeld = (int) $r['gespeeld'];
    $gewonnen = (int) $r['gewonnen'];
    $ratio    = $gespeeld ? round($gewonnen / $gespeeld * 100) . '%' : '-';

    $pdf->Cell(50, 9, txt($labels[$lvl]),       1, 0, 'L');
    $pdf->Cell(35, 9, $gespeeld,                1, 0, 'C');
    $pdf->Cell(35, 9, $gewonnen,                1, 0, 'C');
    $pdf->Cell(35, 9, txt($ratio),              1, 0, 'C');
    $pdf->Cell(35, 9, txt(tijdStr($r['beste'])),1, 1, 'C');
}

$pdf->Output('I', 'rapport.pdf');
