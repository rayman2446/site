<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mijn scores — Minesweeper</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root{ --shell:#1b1f2a; --panel:#252b38; --panel-2:#2f3749; --line:#333c4d;
         --text:#e8edf5; --muted:#8b96a8; --flag:#f5a524; --accent:#3ecf8e; }
  *{box-sizing:border-box;}
  body{margin:0; min-height:100vh; background:var(--shell); color:var(--text);
       font-family:system-ui,sans-serif; display:flex; flex-direction:column;
       align-items:center; padding:24px 12px 48px;}
  h1{font-family:"JetBrains Mono",monospace; font-weight:700; letter-spacing:.18em;
     text-transform:uppercase; font-size:1.25rem; margin:0 0 4px;}
  .subtitle{color:var(--muted); font-size:.85rem; margin-bottom:18px;}
  a.back{color:var(--muted); font-size:.8rem; text-decoration:none; margin-bottom:18px;}
  a.back:hover{color:var(--flag);}

  .panel{width:100%; max-width:480px; background:var(--panel);
         border:1px solid var(--line); border-radius:12px; overflow:hidden;}
  .tabs{display:flex; border-bottom:1px solid var(--line);}
  .tabs button{flex:1; background:transparent; color:var(--muted); border:none;
       padding:12px 8px; cursor:pointer; font-family:"JetBrains Mono",monospace;
       font-size:.8rem; border-bottom:2px solid transparent; transition:color .15s,border-color .15s;}
  .tabs button:hover{color:var(--text);}
  .tabs button.active{color:var(--flag); border-bottom-color:var(--flag);}

  /* statistieken */
  .stats{display:grid; grid-template-columns:repeat(4,1fr); border-bottom:1px solid var(--line);}
  .stat{padding:14px 8px; text-align:center; border-right:1px solid var(--line);}
  .stat:last-child{border-right:none;}
  .stat .val{font-family:"JetBrains Mono",monospace; font-weight:700; font-size:1.2rem; color:var(--accent);}
  .stat .lab{font-size:.66rem; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-top:3px;}

  .chartbox{padding:18px;}
  .note{padding:0 18px 16px; font-size:.74rem; color:var(--muted); text-align:center; min-height:1em;}
  .leeg{padding:40px 18px; text-align:center; color:var(--muted);}
  .login-prompt{padding:40px 18px; text-align:center;}
  .login-prompt a{color:var(--accent); text-decoration:none; font-weight:700;}
  @media(max-width:460px){ .stat .val{font-size:1rem;} }
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
    <a href="scorebord.php">Scorebord</a>
    <a href="mijn-scores.php" class="actief">Mijn scores</a>
    <a href="achievements.php">Achievements</a>
    <a href="account.php" class="login">Inloggen / Account</a>
  </nav>
  <h1>Mijn scores</h1>
  <p class="subtitle">Jouw tijden en statistieken</p>
  <a class="back" href="index.php">&larr; terug naar het spel</a>
  <a class="back" href="api/rapport.php" target="_blank" style="color:#3ecf8e">📄 Download rapport (PDF)</a>

  <div class="panel">
    <div class="tabs" id="tabs">
      <button data-level="beginner" class="active">Beginner</button>
      <button data-level="intermediate">Gevorderd</button>
      <button data-level="expert">Expert</button>
    </div>

    <div id="inhoud">
      <div class="stats">
        <div class="stat"><div class="val" id="sGespeeld">–</div><div class="lab">Gespeeld</div></div>
        <div class="stat"><div class="val" id="sGewonnen">–</div><div class="lab">Gewonnen</div></div>
        <div class="stat"><div class="val" id="sRatio">–</div><div class="lab">Winratio</div></div>
        <div class="stat"><div class="val" id="sBeste">–</div><div class="lab">Beste tijd</div></div>
      </div>
      <div class="chartbox"><canvas id="grafiek" height="200"></canvas></div>
      <p class="note" id="note"></p>
    </div>
  </div>

<!-- libraries: jQuery + Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
/* =====================================================================
   MIJN SCORES
   - haalt je geschiedenis op met jQuery + Ajax (api/score.php?scope=mij)
   - berekent statistieken en tekent je tijden met Chart.js
   ===================================================================== */

let huidigLevel = "beginner";
let grafiek = null;

// Demo-gegevens: ENKEL voor preview zonder server. Verwijder dit blok op je
// eigen server — dan komt alles uit de database.
const DEMO = {
  beginner: [
    {tijd:48, gewonnen:0, gespeeld:"2026-05-01"}, {tijd:31, gewonnen:1, gespeeld:"2026-05-03"},
    {tijd:27, gewonnen:1, gespeeld:"2026-05-06"}, {tijd:55, gewonnen:0, gespeeld:"2026-05-08"},
    {tijd:22, gewonnen:1, gespeeld:"2026-05-11"}, {tijd:19, gewonnen:1, gespeeld:"2026-05-15"},
    {tijd:18, gewonnen:1, gespeeld:"2026-05-20"}
  ],
  intermediate: [
    {tijd:120, gewonnen:0, gespeeld:"2026-05-04"}, {tijd:96, gewonnen:1, gespeeld:"2026-05-09"},
    {tijd:88, gewonnen:1, gespeeld:"2026-05-14"}, {tijd:74, gewonnen:1, gespeeld:"2026-05-19"}
  ],
  expert: [
    {tijd:300, gewonnen:0, gespeeld:"2026-05-10"}, {tijd:241, gewonnen:1, gespeeld:"2026-05-18"}
  ],
};

/* --------------------- ophalen via Ajax (jQuery) --------------------- */
function laad(level){
  $("#note").text("");
  $.ajax({
    url: "api/score.php",
    method: "GET",
    data: { level: level, scope: "mij" },   // -> ?level=...&scope=mij
    dataType: "json"
  })
  .done(function(resp){
    toon(resp.scores || []);
  })
  .fail(function(xhr){
    if (xhr.status === 401){
      toonLoginNodig();           // niet ingelogd -> vraag om in te loggen
    } else {
      toon(DEMO[level] || []);    // DEMO-fallback (verwijderen op je server)
      $("#note").text("(demo-gegevens — geen server bereikbaar)");
    }
  });
}

/* --------------------- statistieken + grafiek --------------------- */
function toon(scores){
  herstelInhoud();

  if (scores.length === 0){
    $("#inhoud").html('<p class="leeg">Nog geen spellen op dit niveau — speel er een!</p>');
    if (grafiek){ grafiek.destroy(); grafiek = null; }
    return;
  }

  const gespeeld = scores.length;
  const winsten  = scores.filter(s => Number(s.gewonnen) === 1);
  const gewonnen = winsten.length;
  const ratio    = Math.round((gewonnen / gespeeld) * 100);
  const beste    = gewonnen ? Math.min(...winsten.map(s => s.tijd)) : null;

  $("#sGespeeld").text(gespeeld);
  $("#sGewonnen").text(gewonnen);
  $("#sRatio").text(ratio + "%");
  $("#sBeste").text(beste !== null ? formatTijd(beste) : "—");

  // grafiek: je wintijden in volgorde van spelen
  const labels = winsten.map(s => formatDatum(s.gespeeld));
  const tijden = winsten.map(s => s.tijd);
  tekenGrafiek(labels, tijden);
}

function tekenGrafiek(labels, tijden){
  const ctx = document.getElementById("grafiek");
  if (grafiek) grafiek.destroy();   // oude grafiek opruimen voor een nieuwe

  grafiek = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [{
        label: "Tijd",
        data: tijden,
        borderColor: "#3ecf8e",
        backgroundColor: "rgba(62,207,142,.15)",
        tension: .3, fill: true, pointRadius: 4, pointBackgroundColor: "#3ecf8e"
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (c) => "Tijd: " + formatTijd(c.parsed.y) } }
      },
      scales: {
        x: { ticks: { color: "#8b96a8" }, grid: { color: "#333c4d" } },
        y: { beginAtZero: true, ticks: { color: "#8b96a8" }, grid: { color: "#333c4d" },
             title: { display: true, text: "seconden", color: "#8b96a8" } }
      }
    }
  });
}

/* --------------------- weergaven --------------------- */
function toonLoginNodig(){
  if (grafiek){ grafiek.destroy(); grafiek = null; }
  $("#inhoud").html(
    '<div class="login-prompt">Je moet ingelogd zijn om je scores te zien.<br><br>' +
    '<a href="account.php">Naar inloggen &rarr;</a></div>'
  );
}

// herbouwt het stats+grafiek-skelet (na een leeg/login-bericht)
function herstelInhoud(){
  if ($("#sGespeeld").length) return; // skelet staat er nog
  $("#inhoud").html(
    '<div class="stats">' +
      '<div class="stat"><div class="val" id="sGespeeld">–</div><div class="lab">Gespeeld</div></div>' +
      '<div class="stat"><div class="val" id="sGewonnen">–</div><div class="lab">Gewonnen</div></div>' +
      '<div class="stat"><div class="val" id="sRatio">–</div><div class="lab">Winratio</div></div>' +
      '<div class="stat"><div class="val" id="sBeste">–</div><div class="lab">Beste tijd</div></div>' +
    '</div>' +
    '<div class="chartbox"><canvas id="grafiek" height="200"></canvas></div>' +
    '<p class="note" id="note"></p>'
  );
}

/* --------------------- hulp --------------------- */
function formatTijd(sec){ const m = Math.floor(sec/60), s = sec%60; return m + ":" + String(s).padStart(2,"0"); }
function formatDatum(d){ const dt = new Date(d); return dt.getDate() + "/" + (dt.getMonth()+1); }

/* --------------------- tabs --------------------- */
$("#tabs").on("click", "button", function(){
  $("#tabs button").removeClass("active");
  $(this).addClass("active");
  huidigLevel = $(this).data("level");
  laad(huidigLevel);
});

// start
laad(huidigLevel);
</script>
</body>
</html>
