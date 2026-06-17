<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scorebord — Minesweeper</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --shell:#1b1f2a; --panel:#252b38; --panel-2:#2f3749;
    --line:#333c4d; --text:#e8edf5; --muted:#8b96a8;
    --flag:#f5a524; --gold:#f5c518; --silver:#c7ccd4; --bronze:#cd7f32;
    --accent:#3ecf8e;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; background:var(--shell); color:var(--text);
    font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
    display:flex; flex-direction:column; align-items:center; padding:24px 12px 48px;
  }
  h1{
    font-family:"JetBrains Mono",monospace; font-weight:700;
    letter-spacing:.18em; text-transform:uppercase; font-size:1.25rem; margin:0 0 4px;
  }
  .subtitle{color:var(--muted); font-size:.85rem; margin-bottom:20px;}
  a.back{color:var(--muted); font-size:.8rem; text-decoration:none; margin-bottom:18px;}
  a.back:hover{color:var(--flag);}

  .panel{
    width:100%; max-width:440px; background:var(--panel);
    border:1px solid var(--line); border-radius:12px; overflow:hidden;
  }

  /* tabs per moeilijkheidsgraad */
  .tabs{display:flex; border-bottom:1px solid var(--line);}
  .tabs button{
    flex:1; background:transparent; color:var(--muted); border:none;
    padding:12px 8px; cursor:pointer; font-family:"JetBrains Mono",monospace;
    font-size:.8rem; border-bottom:2px solid transparent; transition:color .15s,border-color .15s;
  }
  .tabs button:hover{color:var(--text);}
  .tabs button.active{color:var(--flag); border-bottom-color:var(--flag);}

  table{width:100%; border-collapse:collapse;}
  th,td{padding:11px 14px; text-align:left; font-size:.9rem;}
  th{color:var(--muted); font-weight:500; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em;}
  tr{border-top:1px solid var(--line);}
  td.rank{width:46px; font-family:"JetBrains Mono",monospace; color:var(--muted);}
  td.tijd{text-align:right; font-family:"JetBrains Mono",monospace; font-weight:700;}
  tr.top1 td.rank, tr.top2 td.rank, tr.top3 td.rank{font-size:1.1rem;}
  tr.top1{color:var(--gold);} tr.top2{color:var(--silver);} tr.top3{color:var(--bronze);}

  .meta{
    display:flex; justify-content:space-between; align-items:center;
    padding:10px 14px; font-size:.74rem; color:var(--muted);
    border-top:1px solid var(--line); background:var(--panel-2);
  }
  .meta button{
    background:transparent; color:var(--muted); border:1px solid var(--line);
    border-radius:6px; padding:5px 10px; cursor:pointer; font-size:.74rem;
    font-family:"JetBrains Mono",monospace;
  }
  .meta button:hover{color:var(--text); border-color:var(--muted);}
  .empty{padding:28px 14px; text-align:center; color:var(--muted); font-size:.9rem;}
  .dot{display:inline-block; width:7px; height:7px; border-radius:50%;
       background:var(--accent); margin-right:6px; vertical-align:middle;}
  @media(prefers-reduced-motion:reduce){*{transition:none!important;}}
</style>
  <style>
    .nav{display:flex; gap:6px; flex-wrap:wrap; justify-content:center;
         margin-bottom:18px; font-family:"JetBrains Mono",monospace; font-size:.8rem;}
    .nav a{color:#8b96a8; text-decoration:none; padding:6px 11px; border-radius:7px;
           border:1px solid transparent;}
    .nav a:hover{color:#e8edf5; background:#252b38;}
    .nav a.actief{color:#f5a524; border-color:#333c4d;}
    .nav a.login{color:#3ecf8e; margin-left:auto;}
  </style>
</head>
<body>
  <nav class="nav">
    <a href="index.php">Spelen</a>
    <a href="scorebord.php" class="actief">Scorebord</a>
    <a href="mijn-scores.php">Mijn scores</a>
    <a href="achievements.php">Achievements</a>
    <a href="account.php" class="login">Inloggen / Account</a>
  </nav>
  <h1>Scorebord</h1>
  <p class="subtitle">Snelste winst per speler</p>
  <a class="back" href="index.php">&larr; terug naar het spel</a>

  <div class="panel">
    <div class="tabs" id="tabs">
      <button data-level="beginner" class="active">Beginner</button>
      <button data-level="intermediate">Gevorderd</button>
      <button data-level="expert">Expert</button>
    </div>

    <table>
      <thead>
        <tr><th class="rank">#</th><th>Speler</th><th style="text-align:right">Tijd</th></tr>
      </thead>
      <tbody id="scoreBody"></tbody>
    </table>

    <div class="meta">
      <span id="status"><span class="dot"></span>Laden…</span>
      <button id="refresh">Ververs</button>
    </div>
  </div>

<!-- jQuery via CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
/* =====================================================================
   SCOREBORD — jQuery + Ajax
   - haalt de scores op bij het REST-endpoint api/score.php (GET)
   - tabs wisselen tussen moeilijkheidsgraden
   - ververst automatisch elke 5 seconden (live scorebord)
   ===================================================================== */

let huidigLevel = "beginner";
let autoTimer = null;

// Demo-gegevens: ENKEL voor preview zonder server. Verwijder dit blok
// wanneer je echt op XAMPP/LAMP draait — dan komt alles uit de database.
const DEMO = {
  beginner:     [{gebruikersnaam:"luca",  tijd:12},{gebruikersnaam:"sara", tijd:18},
                 {gebruikersnaam:"noah",  tijd:23},{gebruikersnaam:"emma", tijd:31}],
  intermediate: [{gebruikersnaam:"sara",  tijd:74},{gebruikersnaam:"luca", tijd:96},
                 {gebruikersnaam:"finn",  tijd:120}],
  expert:       [{gebruikersnaam:"noah",  tijd:241}],
};

/* --------------------- scores ophalen via Ajax --------------------- */
function laadScorebord(level){
  $("#status").html('<span class="dot"></span>Laden…');

  $.ajax({
    url: "api/score.php",     // je RESTful endpoint
    method: "GET",
    data: { level: level },   // wordt ?level=... in de URL
    dataType: "json"
  })
  .done(function(resp){
    // server bereikt: toon de echte scores
    toonScores(resp.scores || []);
    markeerBijgewerkt(false);
  })
  .fail(function(){
    // ---- DEMO-fallback (verwijderen op je eigen server) ----
    toonScores(DEMO[level] || []);
    markeerBijgewerkt(true);
  });
}

/* --------------------- de tabel vullen --------------------- */
function toonScores(scores){
  const $body = $("#scoreBody").empty();

  if (scores.length === 0){
    $body.append(
      '<tr><td colspan="3" class="empty">Nog geen scores — speel een spel!</td></tr>'
    );
    return;
  }

  const medailles = ["🥇","🥈","🥉"];
  scores.forEach(function(s, i){
    const rang = medailles[i] || (i + 1);
    const klasse = i < 3 ? "top" + (i + 1) : "";
    $body.append(
      $("<tr>").addClass(klasse).append(
        $("<td>").addClass("rank").text(rang),
        $("<td>").text(s.gebruikersnaam),     // .text() -> veilig tegen HTML-injectie
        $("<td>").addClass("tijd").text(formatTijd(s.tijd))
      )
    );
  });
}

// 42 -> "0:42", 120 -> "2:00"
function formatTijd(sec){
  const m = Math.floor(sec / 60), s = sec % 60;
  return m + ":" + String(s).padStart(2, "0");
}

function markeerBijgewerkt(demo){
  const tijd = new Date().toLocaleTimeString("nl-BE");
  $("#status").html('<span class="dot"></span>' +
    (demo ? "demo-gegevens — geen server " : "Bijgewerkt om ") + (demo ? "" : tijd));
}

/* --------------------- knoppen & auto-refresh --------------------- */
$("#tabs").on("click", "button", function(){
  $("#tabs button").removeClass("active");
  $(this).addClass("active");
  huidigLevel = $(this).data("level");
  laadScorebord(huidigLevel);
});

$("#refresh").on("click", function(){ laadScorebord(huidigLevel); });

// live verversen: elke 5 seconden opnieuw ophalen
function startAutoRefresh(){
  if (autoTimer) clearInterval(autoTimer);
  autoTimer = setInterval(function(){ laadScorebord(huidigLevel); }, 5000);
}

// start
laadScorebord(huidigLevel);
startAutoRefresh();
</script>
</body>
</html>
