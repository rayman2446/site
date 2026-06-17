<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Controller — Minesweeper</title>
<style>
  :root{ --shell:#1b1f2a; --panel:#252b38; --panel-2:#2f3749; --line:#333c4d;
         --text:#e8edf5; --muted:#8b96a8; --flag:#f5a524; --accent:#3ecf8e; --danger:#e5484d; }
  *{box-sizing:border-box; -webkit-tap-highlight-color:transparent;}
  body{margin:0; min-height:100vh; background:var(--shell); color:var(--text);
       font-family:system-ui,sans-serif; display:flex; flex-direction:column;
       align-items:center; padding:20px 16px; user-select:none;}
  h1{font-size:1rem; letter-spacing:.12em; text-transform:uppercase; margin:0 0 14px;}

  .status{font-size:.8rem; color:var(--muted); margin-bottom:16px; min-height:1.2em;}
  .status .live{color:var(--accent);}
  .status .off{color:var(--danger);}

  .code-row{display:flex; gap:8px; align-items:center; margin-bottom:20px;}
  .code-row label{font-size:.8rem; color:var(--muted);}
  .code-row input{width:90px; background:var(--panel); border:1px solid var(--line);
       color:var(--text); border-radius:8px; padding:8px; font:700 1.1rem monospace;
       text-align:center; letter-spacing:.15em;}

  /* d-pad */
  .dpad{display:grid; grid-template-columns:repeat(3,70px); grid-template-rows:repeat(3,70px);
        gap:8px; margin-bottom:20px;}
  .dpad button{background:var(--panel); border:1px solid var(--line); color:var(--text);
       border-radius:12px; font-size:1.6rem; cursor:pointer;}
  .dpad button:active{background:var(--panel-2);}
  .up{grid-column:2; grid-row:1;} .left{grid-column:1; grid-row:2;}
  .right{grid-column:3; grid-row:2;} .down{grid-column:2; grid-row:3;}
  .dpad .mid{grid-column:2; grid-row:2; background:transparent; border:none;}

  .actions{display:flex; gap:12px; margin-bottom:24px;}
  .actions button{padding:16px 26px; border-radius:12px; border:none; font-size:1rem;
       font-weight:700; cursor:pointer;}
  .open{background:var(--accent); color:#10131a;}
  .flag{background:var(--flag); color:#10131a;}

  .tilt{width:100%; max-width:300px; background:var(--panel); border:1px solid var(--line);
        border-radius:12px; padding:14px;}
  .tilt-head{display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;}
  .tilt-head span{font-size:.85rem;}
  .toggle{background:var(--panel-2); border:1px solid var(--line); color:var(--text);
       border-radius:8px; padding:8px 12px; font-size:.8rem; cursor:pointer;}
  .toggle.on{border-color:var(--accent); color:var(--accent);}
  .readout{font:1rem monospace; color:var(--muted);}
</style>
</head>
<body>
  <h1>Controller</h1>
  <div class="status" id="status">Niet verbonden</div>

  <div class="code-row">
    <label for="code">Koppelcode</label>
    <input id="code" inputmode="numeric" maxlength="8" placeholder="0000">
  </div>

  <div class="dpad">
    <button class="up"    data-cmd="up">▲</button>
    <button class="left"  data-cmd="left">◀</button>
    <button class="mid" disabled></button>
    <button class="right" data-cmd="right">▶</button>
    <button class="down"  data-cmd="down">▼</button>
  </div>

  <div class="actions">
    <button class="open" data-cmd="open">Openen</button>
    <button class="flag" data-cmd="flag">Vlag 🚩</button>
  </div>

  <div class="tilt">
    <div class="tilt-head">
      <span>Kantelbesturing</span>
      <button class="toggle" id="tiltToggle">Uit</button>
    </div>
    <div class="readout" id="readout">beta: — &nbsp; gamma: —</div>
  </div>

<script>
/* =====================================================================
   CONTROLLER (smartphone)
   - stuurt commando's via REST POST naar api/controller.php
   - leest optioneel de kantelsensor (deviceorientation) en zet kanteling
     om naar richtingscommando's -> het toestel werkt zo als sensor
   ===================================================================== */

const statusEl  = document.getElementById("status");
const codeEl     = document.getElementById("code");
const readoutEl  = document.getElementById("readout");
const tiltToggle = document.getElementById("tiltToggle");

// koppelcode uit de URL halen (de spelpagina linkt naar ?code=1234)
const params = new URLSearchParams(location.search);
if (params.get("code")) codeEl.value = params.get("code");

/* --------------------- commando versturen (REST POST) --------------------- */
async function stuur(command){
  const code = codeEl.value.trim();
  if (!/^[0-9]{4,8}$/.test(code)){ setStatus("Vul eerst de koppelcode in", "off"); return; }

  try {
    const res = await fetch("api/controller.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ code: code, command: command })
    });
    if (res.ok) setStatus("Verbonden — laatste: " + command, "live");
    else        setStatus("Server gaf fout " + res.status, "off");
  } catch (err) {
    setStatus("Geen verbinding met de server", "off");
  }
}

function setStatus(tekst, klasse){
  statusEl.innerHTML = '<span class="' + (klasse || "") + '">●</span> ' + tekst;
}

// alle knoppen (d-pad + acties) koppelen
document.querySelectorAll("[data-cmd]").forEach(function(btn){
  btn.addEventListener("click", function(){ stuur(btn.dataset.cmd); });
});

/* --------------------- kantelsensor --------------------- */
let tiltAan = false;
let neutraalBeta = null, neutraalGamma = null;  // nulstand bij het inschakelen
let laatsteStuur = 0;                            // throttle-tijdstip

tiltToggle.addEventListener("click", async function(){
  if (!tiltAan){
    // iOS 13+ vraagt expliciet toestemming (vereist HTTPS + knopdruk)
    if (typeof DeviceOrientationEvent !== "undefined" &&
        typeof DeviceOrientationEvent.requestPermission === "function"){
      const res = await DeviceOrientationEvent.requestPermission();
      if (res !== "granted"){ setStatus("Sensortoestemming geweigerd", "off"); return; }
    }
    neutraalBeta = neutraalGamma = null;   // herkalibreren
    window.addEventListener("deviceorientation", onTilt);
    tiltAan = true; tiltToggle.textContent = "Aan"; tiltToggle.classList.add("on");
  } else {
    window.removeEventListener("deviceorientation", onTilt);
    tiltAan = false; tiltToggle.textContent = "Uit"; tiltToggle.classList.remove("on");
  }
});

function onTilt(e){
  const beta  = e.beta  || 0;
  const gamma = e.gamma || 0;
  readoutEl.textContent = "beta: " + beta.toFixed(0) + "  gamma: " + gamma.toFixed(0);

  // eerste meting = nulstand vastleggen
  if (neutraalBeta === null){ neutraalBeta = beta; neutraalGamma = gamma; return; }

  // hoogstens één richtingscommando per 300 ms, zodat de cursor stapt
  const nu = Date.now();
  if (nu - laatsteStuur < 300) return;

  const dB = beta - neutraalBeta, dG = gamma - neutraalGamma;
  const drempel = 18;            // hoe ver je moet kantelen
  let cmd = null;
  if      (dG < -drempel) cmd = "left";
  else if (dG >  drempel) cmd = "right";
  else if (dB < -drempel) cmd = "up";
  else if (dB >  drempel) cmd = "down";

  if (cmd){ laatsteStuur = nu; stuur(cmd); }
}
</script>
</body>
</html>
