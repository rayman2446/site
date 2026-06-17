<?php
// includes/achievements.php — definities + server-side evaluatie
//
// De achievements worden AFGELEID uit je echte scores (de client kan niets
// claimen). Nieuw behaalde achievements worden opgeslagen in user_achievements,
// zodat we ze maar één keer als "nieuw" tonen.
//
// Vereist PHP 7.4+ (arrow functions). Gebruikt door:
//   - api/achievements.php  (de pagina vraagt de volledige lijst op)
//   - api/score.php         (na elk spel: zijn er nieuwe achievements?)

/* ---------- de definitielijst ----------
   Elke achievement heeft een check() die true geeft als hij behaald is,
   en optioneel een voortgang() die [huidig, doel] teruggeeft voor een balk. */
function achievementDefinities(): array
{
    return [
        ['id' => 'eerste_winst', 'icoon' => '🎉', 'naam' => 'Eerste overwinning',
         'beschrijving' => 'Win je eerste spel',
         'check' => fn($s) => $s['gewonnen'] >= 1],

        ['id' => 'doorzetter', 'icoon' => '🎮', 'naam' => 'Doorzetter',
         'beschrijving' => 'Speel 10 spellen',
         'check'     => fn($s) => $s['gespeeld'] >= 10,
         'voortgang' => fn($s) => [$s['gespeeld'], 10]],

        ['id' => 'veteraan', 'icoon' => '🏅', 'naam' => 'Veteraan',
         'beschrijving' => 'Speel 50 spellen',
         'check'     => fn($s) => $s['gespeeld'] >= 50,
         'voortgang' => fn($s) => [$s['gespeeld'], 50]],

        ['id' => 'kampioen', 'icoon' => '🏆', 'naam' => 'Kampioen',
         'beschrijving' => 'Win 10 spellen',
         'check'     => fn($s) => $s['gewonnen'] >= 10,
         'voortgang' => fn($s) => [$s['gewonnen'], 10]],

        ['id' => 'gevorderde', 'icoon' => '🧠', 'naam' => 'Gevorderde',
         'beschrijving' => 'Win een Gevorderd-spel',
         'check' => fn($s) => $s['winInter'] >= 1],

        ['id' => 'expertstatus', 'icoon' => '💀', 'naam' => 'Expertstatus',
         'beschrijving' => 'Win een Expert-spel',
         'check' => fn($s) => $s['winExpert'] >= 1],

        ['id' => 'alleskunner', 'icoon' => '🌈', 'naam' => 'Alleskunner',
         'beschrijving' => 'Win op elk niveau',
         'check' => fn($s) => $s['winBeginner'] >= 1 && $s['winInter'] >= 1 && $s['winExpert'] >= 1],

        ['id' => 'snelheidsduivel', 'icoon' => '⚡', 'naam' => 'Snelheidsduivel',
         'beschrijving' => 'Win Beginner onder 10 seconden',
         'check' => fn($s) => $s['besteBeginner'] !== null && $s['besteBeginner'] <= 10],

        ['id' => 'bliksemsnel', 'icoon' => '🚀', 'naam' => 'Bliksemsnel',
         'beschrijving' => 'Win Gevorderd onder 60 seconden',
         'check' => fn($s) => $s['besteInter'] !== null && $s['besteInter'] <= 60],
    ];
}

/* ---------- statistieken berekenen uit de scores-tabel ---------- */
function berekenStats(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT level,
                COUNT(*)                                  AS gespeeld,
                COALESCE(SUM(gewonnen), 0)                AS gewonnen,
                MIN(CASE WHEN gewonnen = 1 THEN tijd END) AS beste
           FROM scores
          WHERE user_id = :uid
          GROUP BY level'
    );
    $stmt->execute([':uid' => $userId]);

    $per = [];
    $totGespeeld = 0; $totGewonnen = 0;
    foreach ($stmt->fetchAll() as $r) {
        $per[$r['level']] = $r;
        $totGespeeld += (int) $r['gespeeld'];
        $totGewonnen += (int) $r['gewonnen'];
    }

    return [
        'gespeeld'      => $totGespeeld,
        'gewonnen'      => $totGewonnen,
        'winBeginner'   => (int) ($per['beginner']['gewonnen'] ?? 0),
        'winInter'      => (int) ($per['intermediate']['gewonnen'] ?? 0),
        'winExpert'     => (int) ($per['expert']['gewonnen'] ?? 0),
        'besteBeginner' => isset($per['beginner']['beste'])     ? (int) $per['beginner']['beste']     : null,
        'besteInter'    => isset($per['intermediate']['beste']) ? (int) $per['intermediate']['beste'] : null,
        'besteExpert'   => isset($per['expert']['beste'])       ? (int) $per['expert']['beste']       : null,
    ];
}

/* ---------- evalueren + nieuw behaalde opslaan ----------
   Geeft de volledige lijst terug (met ontgrendeld-status en voortgang),
   plus de achievements die NU pas voor het eerst behaald werden. */
function evalueerAchievements(PDO $pdo, int $userId): array
{
    $stats = berekenStats($pdo, $userId);
    $defs  = achievementDefinities();

    // reeds opgeslagen achievements ophalen
    $stmt = $pdo->prepare('SELECT achievement_id FROM user_achievements WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    $behaald = array_flip(array_column($stmt->fetchAll(), 'achievement_id'));

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (:uid, :aid)'
    );

    $lijst = [];
    $nieuw = [];
    foreach ($defs as $d) {
        $ontgrendeld = ($d['check'])($stats);

        // nieuw behaald? -> opslaan en als "nieuw" markeren
        if ($ontgrendeld && !isset($behaald[$d['id']])) {
            $insert->execute([':uid' => $userId, ':aid' => $d['id']]);
            $nieuw[] = ['icoon' => $d['icoon'], 'naam' => $d['naam']];
        }

        $rij = [
            'id'           => $d['id'],
            'icoon'        => $d['icoon'],
            'naam'         => $d['naam'],
            'beschrijving' => $d['beschrijving'],
            'ontgrendeld'  => $ontgrendeld || isset($behaald[$d['id']]),
        ];
        if (isset($d['voortgang'])) {
            [$huidig, $doel] = ($d['voortgang'])($stats);
            $rij['voortgang'] = ['huidig' => min($huidig, $doel), 'doel' => $doel];
        }
        $lijst[] = $rij;
    }

    return ['achievements' => $lijst, 'nieuw' => $nieuw];
}
