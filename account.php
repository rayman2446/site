<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account — Minesweeper</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root{ --shell:#1b1f2a; --panel:#252b38; --panel-2:#2f3749; --line:#333c4d;
         --text:#e8edf5; --muted:#8b96a8; --flag:#f5a524; --accent:#3ecf8e; --danger:#e5484d; }
  *{box-sizing:border-box;}
  body{margin:0; min-height:100vh; background:var(--shell); color:var(--text);
       font-family:system-ui,sans-serif; display:flex; flex-direction:column;
       align-items:center; padding:32px 16px;}
  h1{font-family:"JetBrains Mono",monospace; font-weight:700; letter-spacing:.18em;
     text-transform:uppercase; font-size:1.25rem; margin:0 0 22px;}

  .card{width:100%; max-width:380px; background:var(--panel);
        border:1px solid var(--line); border-radius:12px; overflow:hidden;}

  .tabs{display:flex; border-bottom:1px solid var(--line);}
  .tabs button{flex:1; background:transparent; color:var(--muted); border:none;
       padding:14px; cursor:pointer; font-family:"JetBrains Mono",monospace;
       font-size:.85rem; border-bottom:2px solid transparent; transition:color .15s,border-color .15s;}
  .tabs button:hover{color:var(--text);}
  .tabs button.active{color:var(--flag); border-bottom-color:var(--flag);}

  form{padding:22px; display:flex; flex-direction:column; gap:14px;}
  label{font-size:.78rem; color:var(--muted); margin-bottom:-8px;}
  input{background:var(--shell); border:1px solid var(--line); color:var(--text);
        border-radius:8px; padding:11px 12px; font-size:.95rem;}
  input:focus{outline:2px solid var(--flag); outline-offset:-1px;}
  .submit{background:var(--accent); color:#10131a; border:none; border-radius:8px;
          padding:12px; font-weight:700; font-size:.95rem; cursor:pointer; margin-top:4px;}
  .submit:hover{filter:brightness(1.07);}
  .msg{font-size:.82rem; min-height:1.1em; text-align:center;}
  .msg.error{color:var(--danger);} .msg.ok{color:var(--accent);}
  .hidden{display:none;}

  /* profielweergave na inloggen */
  .profiel{padding:26px; text-align:center;}
  .profiel .naam{font-family:"JetBrains Mono",monospace; font-size:1.3rem; color:var(--flag); margin:6px 0 18px;}
  .profiel a{display:block; background:var(--panel-2); color:var(--text); text-decoration:none;
       border:1px solid var(--line); border-radius:8px; padding:11px; margin-bottom:10px; font-size:.9rem;}
  .profiel a:hover{border-color:var(--muted);}
  .logout{background:transparent; color:var(--muted); border:1px solid var(--line);
       border-radius:8px; padding:10px; width:100%; cursor:pointer; font-size:.85rem; margin-top:6px;}
  .logout:hover{color:var(--danger); border-color:var(--danger);}
</style>
</head>
<body>
  <h1>Account</h1>

  <div class="card" id="card">
    <!-- AUTH (inloggen / registreren) -->
    <div id="authBlok">
      <div class="tabs">
        <button data-tab="login" class="active">Inloggen</button>
        <button data-tab="register">Registreren</button>
      </div>

      <!-- inlogformulier -->
      <form id="loginForm">
        <label for="l_naam">Gebruikersnaam</label>
        <input id="l_naam" autocomplete="username">
        <label for="l_ww">Wachtwoord</label>
        <input id="l_ww" type="password" autocomplete="current-password">
        <button type="submit" class="submit">Inloggen</button>
        <p class="msg" id="loginMsg"></p>
      </form>

      <!-- registratieformulier -->
      <form id="registerForm" class="hidden">
        <label for="r_naam">Gebruikersnaam (3-40 tekens)</label>
        <input id="r_naam" autocomplete="username">
        <label for="r_ww">Wachtwoord (min. 6 tekens)</label>
        <input id="r_ww" type="password" autocomplete="new-password">
        <label for="r_ww2">Wachtwoord herhalen</label>
        <input id="r_ww2" type="password" autocomplete="new-password">
        <button type="submit" class="submit">Account aanmaken</button>
        <p class="msg" id="registerMsg"></p>
      </form>
    </div>

    <!-- PROFIEL (na inloggen) -->
    <div id="profielBlok" class="profiel hidden">
      <p>Ingelogd als</p>
      <p class="naam" id="profielNaam">—</p>
      <a href="index.php">Spelen</a>
      <a href="mijn-scores.php">Mijn scores</a>
      <button class="logout" id="logoutBtn">Uitloggen</button>
    </div>
  </div>

<script>
/* =====================================================================
   ACCOUNT — client-side
   - tabs wisselen tussen inloggen en registreren
   - client-side validatie (gebruiksgemak; de echte controle staat in PHP)
   - Ajax naar api/auth.php; toont het profiel zodra je ingelogd bent
   ===================================================================== */

const $ = (id) => document.getElementById(id);

/* tabs -------------------------------------------------------------- */
document.querySelectorAll(".tabs button").forEach(function(btn){
  btn.addEventListener("click", function(){
    document.querySelectorAll(".tabs button").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    const tab = btn.dataset.tab;
    $("loginForm").classList.toggle("hidden", tab !== "login");
    $("registerForm").classList.toggle("hidden", tab !== "register");
  });
});

/* hulpfunctie: een POST naar het auth-endpoint ---------------------- */
async function authRequest(payload){
  const res = await fetch("api/auth.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  return { ok: res.ok, data };
}

/* inloggen ---------------------------------------------------------- */
$("loginForm").addEventListener("submit", async function(e){
  e.preventDefault();
  const msg = $("loginMsg"); msg.className = "msg"; msg.textContent = "";
  const naam = $("l_naam").value.trim(), ww = $("l_ww").value;

  if (!naam || !ww){ toon(msg, "Vul beide velden in", false); return; }

  try {
    const { ok, data } = await authRequest({ action:"login", gebruikersnaam:naam, wachtwoord:ww });
    if (ok) toonProfiel(data.gebruikersnaam);
    else    toon(msg, data.fout || "Inloggen mislukt", false);
  } catch (err) {
    toon(msg, "Geen verbinding met de server", false);
  }
});

/* registreren ------------------------------------------------------- */
$("registerForm").addEventListener("submit", async function(e){
  e.preventDefault();
  const msg = $("registerMsg"); msg.className = "msg"; msg.textContent = "";
  const naam = $("r_naam").value.trim(), ww = $("r_ww").value, ww2 = $("r_ww2").value;

  // client-side validatie
  if (naam.length < 3 || naam.length > 40){ toon(msg, "Gebruikersnaam moet 3-40 tekens zijn", false); return; }
  if (ww.length < 6){ toon(msg, "Wachtwoord moet minstens 6 tekens zijn", false); return; }
  if (ww !== ww2){ toon(msg, "De wachtwoorden komen niet overeen", false); return; }

  try {
    const { ok, data } = await authRequest({ action:"register", gebruikersnaam:naam, wachtwoord:ww });
    if (ok) toonProfiel(data.gebruikersnaam);
    else    toon(msg, data.fout || "Registreren mislukt", false);
  } catch (err) {
    toon(msg, "Geen verbinding met de server", false);
  }
});

/* uitloggen --------------------------------------------------------- */
$("logoutBtn").addEventListener("click", async function(){
  try { await authRequest({ action:"logout" }); } catch (err) {}
  $("profielBlok").classList.add("hidden");
  $("authBlok").classList.remove("hidden");
});

/* hulpfuncties ------------------------------------------------------ */
function toon(el, tekst, ok){ el.textContent = tekst; el.className = "msg " + (ok ? "ok" : "error"); }

function toonProfiel(naam){
  $("authBlok").classList.add("hidden");
  $("profielBlok").classList.remove("hidden");
  $("profielNaam").textContent = naam;       // .textContent -> veilig tegen XSS
}

/* bij het laden: ben ik al ingelogd? -------------------------------- */
(async function(){
  try {
    const res = await fetch("api/auth.php");   // GET -> wie ben ik?
    const data = await res.json();
    if (data.ingelogd) toonProfiel(data.gebruikersnaam);
  } catch (err) { /* geen server in preview -> toon gewoon de formulieren */ }
})();
</script>
</body>
</html>
