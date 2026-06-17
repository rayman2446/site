/* =====================================================================
   game-controller.js — koppelt de smartphone-controller aan de game
   Plaats dit script NA de game-code (dan bestaan grid, rows, cols,
   reveal, toggleFlag, ... al). Voeg in minesweeper.html toe, vlak voor </body>:
       <script src="js/game-controller.js"></script>

   Wat het doet:
     - genereert een koppelcode van 4 cijfers en toont die + een link
     - tekent een cursor (geel kader) op het bord
     - pollt elke 200 ms api/controller.php (REST GET) voor nieuwe commando's
     - voert up/down/left/right/open/flag uit op het bord
   ===================================================================== */
(function(){

  // 1) koppelcode + paneel tonen ------------------------------------------
  const code = String(Math.floor(1000 + Math.random() * 9000)); // 4 cijfers
  const controllerUrl = new URL("controller.php?code=" + code, location.href).href;

  // wat CSS voor de cursor (geen aanpassing aan je CSS-bestand nodig)
  const stijl = document.createElement("style");
  stijl.textContent =
    ".cell.cursor{outline:3px solid var(--flag); outline-offset:-3px; z-index:1;}";
  document.head.appendChild(stijl);

  const paneel = document.createElement("div");
  paneel.style.cssText =
    "margin-top:18px; padding:12px 16px; background:#252b38; border:1px solid #333c4d;" +
    "border-radius:10px; font-family:monospace; font-size:.85rem; text-align:center; color:#e8edf5;";
  paneel.innerHTML =
    "Bestuur met je gsm &mdash; koppelcode <b style='color:#f5a524; font-size:1.2rem'>" +
    code + "</b><br><a href='" + controllerUrl +
    "' style='color:#8b96a8; font-size:.75rem'>" + controllerUrl + "</a>";
  document.body.appendChild(paneel);

  // 2) cursor --------------------------------------------------------------
  let curR = 0, curC = 0;

  function tekenCursor(){
    // bestaande cursor weghalen
    document.querySelectorAll(".cell.cursor").forEach(el => el.classList.remove("cursor"));
    // binnen het huidige bord houden (rows/cols kan wijzigen bij nieuw spel)
    curR = Math.max(0, Math.min(rows - 1, curR));
    curC = Math.max(0, Math.min(cols - 1, curC));
    if (grid[curR] && grid[curR][curC]) grid[curR][curC].el.classList.add("cursor");
  }

  function beweeg(dr, dc){ curR += dr; curC += dc; tekenCursor(); }

  // openen gedraagt zich als een linkerklik (plaatst mijnen bij 1e klik)
  function openHier(){
    if (gameOver) return;
    const cell = grid[curR][curC];
    if (cell.revealed || cell.flagged) return;
    if (!minesPlaced){ placeMines(curR, curC); startTimer(); }
    reveal(curR, curC);
  }

  function voerUit(cmd){
    switch (cmd){
      case "up":    beweeg(-1, 0); break;
      case "down":  beweeg( 1, 0); break;
      case "left":  beweeg( 0,-1); break;
      case "right": beweeg( 0, 1); break;
      case "open":  openHier(); break;
      case "flag":  toggleFlag(curR, curC); break;
    }
  }

  // 3) pollen (REST GET) ---------------------------------------------------
  let laatsteSeq = 0;

  async function poll(){
    try {
      const res = await fetch("api/controller.php?code=" + code);
      const data = await res.json();
      // enkel handelen bij een NIEUW commando (hoger volgnummer)
      if (data.seq > laatsteSeq){
        laatsteSeq = data.seq;
        if (data.command) voerUit(data.command);
      }
    } catch (err) {
      // server even onbereikbaar -> stil overslaan, volgende poll probeert opnieuw
    }
    tekenCursor(); // ook na een nieuw spel blijft de cursor zichtbaar
  }

  setInterval(poll, 200);
  tekenCursor();

})();
