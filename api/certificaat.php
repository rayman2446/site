<?php
// api/certificaat.php — genereert een certificaat-PDF (Student A)
// PHP-functionaliteit voorbij lecture 5: PDF-generatie met de library FPDF.
//
// Aanroepen vanaf de game na een winst, bv.:
//   api/certificaat.php?level=expert&tijd=161
//
// Installatie: download FPDF van fpdf.org en plaats fpdf.php in lib/fpdf/.

session_start();
require __DIR__ . '/../lib/fpdf/fpdf.php';

// enkel voor ingelogde gebruikers
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Niet ingelogd');
}
$naam = $_SESSION['gebruikersnaam'] ?? 'Speler';

// parameters valideren (vertrouw de client nooit)
$levels = ['beginner' => 'Beginner', 'intermediate' => 'Gevorderd', 'expert' => 'Expert'];
$level  = $_GET['level'] ?? '';
$tijd   = isset($_GET['tijd']) ? (int) $_GET['tijd'] : -1;
if (!isset($levels[$level]) || $tijd < 0 || $tijd > 9999) {
    http_response_code(422);
    exit('Ongeldige gegevens');
}

// FPDF gebruikt Latin-1 -> dynamische tekst omzetten zodat accenten kloppen
function txt(string $s): string { return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'); }

$tijdStr = floor($tijd / 60) . ':' . str_pad($tijd % 60, 2, '0', STR_PAD_LEFT);

$pdf = new FPDF('L', 'mm', 'A4'); // landschap
$pdf->AddPage();

// dubbele groene rand
$pdf->SetDrawColor(62, 207, 142);
$pdf->SetLineWidth(1.5); $pdf->Rect(10, 10, 277, 190);
$pdf->SetLineWidth(0.3); $pdf->Rect(14, 14, 269, 182);

$pdf->SetY(45);
$pdf->SetFont('Arial', 'B', 46); $pdf->SetTextColor(27, 31, 42);
$pdf->Cell(0, 20, txt('CERTIFICAAT'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 16); $pdf->SetTextColor(90, 100, 115);
$pdf->Cell(0, 12, txt('Dit certificaat bevestigt dat'), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 30); $pdf->SetTextColor(62, 170, 120);
$pdf->Cell(0, 20, txt($naam), 0, 1, 'C');

$pdf->SetFont('Arial', '', 16); $pdf->SetTextColor(90, 100, 115);
$pdf->Cell(0, 12, txt("Minesweeper heeft uitgespeeld op niveau {$levels[$level]}"), 0, 1, 'C');
$pdf->Cell(0, 10, txt("in een tijd van $tijdStr"), 0, 1, 'C');

$pdf->Ln(18);
$pdf->SetFont('Arial', 'I', 12); $pdf->SetTextColor(130, 140, 155);
$pdf->Cell(0, 10, txt('Behaald op ' . date('d/m/Y')), 0, 1, 'C');

// 'I' = toon in de browser; gebruik 'D' om te downloaden
$pdf->Output('I', 'certificaat.pdf');
