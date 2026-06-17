<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Achievements — Minesweeper</title>
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
  .telling{color:var(--muted); font-size:.85rem; margin-bottom:6px;}
  a.back{color:var(--muted); font-size:.8rem; text-decoration:none; margin-bottom:20px;}
  a.back:hover{color:var(--flag);}

  .grid{display:grid; grid-template-columns:repeat(2,1fr); gap:12px; width:100%; max-width:540px;}
  .badge{background:var(--panel); border:1px solid var(--line); border-radius:12px;
         padding:16px; display:flex; gap:13px; align-items:flex-start;}
  .badge .icoon{font-size:2rem; line-height:1; flex-shrink:0;}
  .badge .naam{font-weight:700; font-size:.95rem;}
  .badge .besch{color:var(--muted); font-size:.8rem; margin-top:2px;}

  /* ontgrendeld vs vergrendeld */
  .badge.uit{opacity:.5;}
  .badge.uit .icoon{filter:grayscale(1);}
  .badge.aan{border-color:var(--accent);}
  .badge.aan .naam{color:var(--accent);}
  .slot{margin-left:auto; font-size:.85rem;}
  .badge.aan .slot{color:var(--accent);}
  .badge.uit .slot{color:var(--muted);}

  /* voortgangsbalk */
  .voortgang{margin-top:8px;}
  .balk{height:6px; background:var(--shell); border-radius:3px; overflow:hidden;}
  .balk > i{display:block; height:100%; background:var(--flag); border-radius:3px;}
  .vtekst{font-size:.7rem; color:var(--muted); margin-top:3px; font-family:"JetBrains Mono",monospace;}

  .melding{color:var(--muted); padding:40px; text-align:center;}
  .melding a{color:var(--accent); text-decoration:none; font-weight:700;}
  @media(max-width:480px){ .grid{grid-template-columns:1fr;} }
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
    <a href="mijn-scores.php">Mijn scores</a>
    <a href="achievements.php" class="actief">Achievements</a>
    <a href="account.php" class="login">Inloggen / Account</a>
  </nav>
  <h1>Achievements</h1>
  <p class="telling" id="telling">&nbsp;</p>
  <a class="back" href="index.php">&larr; terug naar het spel</a>

  <div class="grid" id="grid"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
/* =====================================================================
   ACHIEVEMENTS — haalt de lijst op met jQuery + Ajax en tekent de badges
   ===================================================================== */

// Demo-gegevens: ENKEL voor preview zonder server. Verwijder op je server.
const DEMO = { achievements: [
  {id:"eerste_winst", icoon:"🎉", naam:"Eerste overwinning", beschrijving:"Win je eerste spel", ontgrendeld:true},
  {id:"doorzetter",   icoon:"🎮", naam:"Doorzetter", beschrijving:"Speel 10 spellen", ontgrendeld:false, voortgang:{huidig:6, doel:10}},
  {id:"veteraan",     icoon:"🏅", naam:"Veteraan", beschrijving:"Speel 50 spellen", ontgrendeld:false, voortgang:{huidig:6, doel:50}},
  {id:"kampioen",     icoon:"🏆", naam:"Kampioen", beschrijving:"Win 10 spellen", ontgrendeld:false, voortgang:{huidig:4, doel:10}},
  {id:"gevorderde",   icoon:"🧠", naam:"Gevorderde", beschrijving:"Win een Gevorderd-spel", ontgrendeld:true},
  {id:"expertstatus", icoon:"💀", naam:"Expertstatus", beschrijving:"Win een Expert-spel", ontgrendeld:true},
  {id:"alleskunner",  icoon:"🌈", naam:"Alleskunner", beschrijving:"Win op elk niveau", ontgrendeld:true},
  {id:"snelheidsduivel", icoon:"⚡", naam:"Snelheidsduivel", beschrijving:"Win Beginner onder 10 seconden", ontgrendeld:false},
  {id:"bliksemsnel",  icoon:"🚀", naam:"Bliksemsnel", beschrijving:"Win Gevorderd onder 60 seconden", ontgrendeld:false},
]};

function laad(){
  $.ajax({ url: "api/achievements.php", method: "GET", dataType: "json" })
   .done(function(resp){ toon(resp.achievements || []); })
   .fail(function(xhr){
     if (xhr.status === 401){
       $("#grid").html('<p class="melding">Je moet ingelogd zijn om je achievements te zien.' +
                       '<br><br><a href="account.php">Naar inloggen &rarr;</a></p>');
       $("#telling").html("&nbsp;");
     } else {
       toon(DEMO.achievements);   // DEMO-fallback (verwijderen op je server)
     }
   });
}

function toon(lijst){
  const ontgrendeld = lijst.filter(a => a.ontgrendeld).length;
  $("#telling").text(ontgrendeld + " / " + lijst.length + " ontgrendeld");

  const $grid = $("#grid").empty();
  lijst.forEach(function(a){
    const $b = $('<div class="badge">').addClass(a.ontgrendeld ? "aan" : "uit");

    const $tekst = $("<div>").append(
      $('<div class="naam">').text(a.naam),         // .text() -> veilig
      $('<div class="besch">').text(a.beschrijving)
    );

    // voortgangsbalk voor nog-vergrendelde achievements met een doel
    if (!a.ontgrendeld && a.voortgang){
      const pct = Math.round(a.voortgang.huidig / a.voortgang.doel * 100);
      $tekst.append(
        $('<div class="voortgang">').append(
          $('<div class="balk">').append($("<i>").css("width", pct + "%")),
          $('<div class="vtekst">').text(a.voortgang.huidig + " / " + a.voortgang.doel)
        )
      );
    }

    $b.append(
      $('<div class="icoon">').text(a.icoon),
      $tekst,
      $('<div class="slot">').text(a.ontgrendeld ? "✓" : "🔒")
    );
    $grid.append($b);
  });
}

laad();
</script>
</body>
</html>
