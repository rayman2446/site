<?php
// qr-approve.php — opent op de gsm na het scannen van de QR-code.
// Leest server-side de sessie uit om te weten of de gsm ingelogd is, en
// laat de gebruiker het inloggen van de laptop bevestigen.

session_start();
$token    = $_GET['token'] ?? '';
$ingelogd = isset($_SESSION['user_id']);
$naam     = $_SESSION['gebruikersnaam'] ?? '';
$tokenOk  = (bool) preg_match('/^[a-f0-9]{32}$/', $token);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laptop inloggen — Minesweeper</title>
<style>
  :root{ --shell:#1b1f2a; --panel:#252b38; --line:#333c4d; --text:#e8edf5;
         --muted:#8b96a8; --flag:#f5a524; --accent:#3ecf8e; --danger:#e5484d; }
  *{box-sizing:border-box;}
  body{margin:0; min-height:100vh; background:var(--shell); color:var(--text);
       font-family:system-ui,sans-serif; display:flex; flex-direction:column;
       align-items:center; justify-content:center; padding:24px; text-align:center;}
  .card{background:var(--panel); border:1px solid var(--line); border-radius:14px;
        padding:28px 24px; max-width:340px; width:100%;}
  h1{font-size:1.05rem; margin:0 0 8px;}
  p{color:var(--muted); font-size:.9rem;}
  .naam{color:var(--flag); font-weight:700;}
  button{background:var(--accent); color:#10131a; border:none; border-radius:9px;
         padding:14px; width:100%; font-size:1rem; font-weight:700; cursor:pointer; margin-top:14px;}
  a{color:var(--accent); text-decoration:none; font-weight:700;}
  .msg{min-height:1.2em; margin-top:12px; font-size:.9rem;}
  .msg.ok{color:var(--accent);} .msg.err{color:var(--danger);}
</style>
</head>
<body>
  <div class="card">
  <?php if (!$tokenOk): ?>
    <h1>Ongeldige code</h1>
    <p>Deze QR-code is niet geldig. Probeer opnieuw te scannen.</p>
  <?php elseif (!$ingelogd): ?>
    <h1>Eerst inloggen</h1>
    <p>Log op je gsm in en scan daarna de code opnieuw.</p>
    <p><a href="account.php">Naar inloggen &rarr;</a></p>
  <?php else: ?>
    <h1>Laptop inloggen?</h1>
    <p>Je laptop wordt ingelogd als <span class="naam"><?= htmlspecialchars($naam) ?></span>.</p>
    <button id="goedkeuren">Goedkeuren</button>
    <div class="msg" id="msg"></div>

    <script>
      // het token komt veilig uit PHP (htmlspecialchars voorkomt injectie)
      const token = <?= json_encode($token) ?>;

      document.getElementById("goedkeuren").addEventListener("click", async function(){
        const btn = this, msg = document.getElementById("msg");
        btn.disabled = true;
        try {
          const res = await fetch("api/qrlogin.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ action: "approve", token: token })
          });
          const data = await res.json();
          if (res.ok){
            msg.className = "msg ok";
            msg.textContent = "Goedgekeurd! Je laptop logt nu in.";
          } else {
            msg.className = "msg err";
            msg.textContent = data.fout || "Goedkeuren mislukt";
            btn.disabled = false;
          }
        } catch (err) {
          msg.className = "msg err";
          msg.textContent = "Geen verbinding met de server";
          btn.disabled = false;
        }
      });
    </script>
  <?php endif; ?>
  </div>
</body>
</html>
