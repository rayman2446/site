# Minesweeper — webproject

> Een online Minesweeper met accounts, een persoonlijk en algemeen scorebord,
> achievements, PDF-export, en een smartphone die via REST als afstandsbediening
> (kantelsensor) dient.

**Groep:** `[1EAIa]`
**Studenten:** `[Ollivier Joachims]` · `[Rayan Kandichy]`
**Repository:** `[https://github.com/rayman2446/site ]`

---

## Inhoud

1. [Wat het project doet](#wat-het-project-doet)
2. [Technologie](#technologie)
3. [Mappenstructuur](#mappenstructuur)
4. [Installatie](#installatie)
5. [Database](#database)
6. [Architectuur](#architectuur)
7. [Documentatie per pagina](#documentatie-per-pagina)
8. [Taakverdeling](#taakverdeling)
9. [Eisenchecklist](#eisenchecklist)
10. [Bekende beperkingen & mogelijke uitbreidingen](#bekende-beperkingen--mogelijke-uitbreidingen)

---

## Wat het project doet

Een speelbare Minesweeper in de browser. Je maakt een account aan, speelt
spellen op drie moeilijkheidsgraden, en je tijden worden bewaard in een
database. Een algemeen scorebord toont de snelste spelers; op je eigen profiel
zie je je geschiedenis met een grafiek en je behaalde achievements. Bij winst
kun je een certificaat als PDF downloaden, en je statistieken als rapport. Je
kunt het spel ook besturen door je smartphone te kantelen (via REST).

Alle pagina's zijn met elkaar verbonden via een navigatiebalk bovenaan.

## Technologie

| Onderdeel        | Gebruikt |
|------------------|----------|
| Structuur/opmaak | HTML, CSS |
| Client-side      | JavaScript (vanilla), jQuery, Ajax |
| JS-libraries     | Student A: jQuery + canvas-confetti · Student B: jQuery + Chart.js |
| Server-side      | PHP (PDO), FPDF voor PDF-generatie |
| Database         | MySQL / MariaDB |
| Samenwerking     | Git + GitHub |
| Extern toestel   | Smartphone via REST (kantelsensor) |

## Mappenstructuur

```
minesweeper/
├── README.md
├── index.php            # de minesweeper-pagina (Student A)
├── scorebord.php        # algemeen scorebord, jQuery + Ajax (Student A)
├── controller.php       # bedieningspagina voor de smartphone (Student A)
├── account.php          # registreren / inloggen / profiel (Student B)
├── mijn-scores.php      # persoonlijke scores + Chart.js-grafiek (Student B)
├── achievements.php     # badge-overzicht (extra)
├── api/
│   ├── score.php        # REST: score opslaan (POST) en uitlezen (GET)
│   ├── controller.php   # REST: commando's tussen gsm en spelpagina
│   ├── auth.php         # REST: registreren, inloggen, uitloggen
│   ├── certificaat.php  # PDF-certificaat bij winst (FPDF)
│   ├── rapport.php      # PDF-statistiekenrapport (FPDF)
│   └── achievements.php # REST: achievementlijst met status
├── includes/
│   ├── db.php           # herbruikbare databaseverbinding (PDO)
│   └── achievements.php # achievement-definities + evaluatie
├── js/
│   └── game-controller.js   # koppelt de smartphone-controller aan de game
└── lib/
    └── fpdf/fpdf.php    # FPDF-library (download van fpdf.org)
```

## Installatie

1. Installeer **XAMPP** (of een andere LAMP-stack) en start Apache + MySQL.
2. Plaats de projectmap in `htdocs/` (XAMPP) of `www/`.
3. Maak in **phpMyAdmin** een database `minesweeper` en voer alle `CREATE TABLE`'s
   uit de sectie [Database](#database) uit (4 tabellen).
4. Download **FPDF** van fpdf.org en plaats `fpdf.php` in `lib/fpdf/`.
5. Pas in `includes/db.php` de gebruikersnaam/wachtwoord aan (bij XAMPP standaard
   `root` zonder wachtwoord).
6. Open het project in je browser:
   - lokaal testen: `http://localhost/minesweeper/index.php`
     (de login staat op `account.php`, bereikbaar via de navigatiebalk)
   - **voor de kantel-controller:** open via het lokale IP van je laptop,
     bv. `http://192.168.0.42/minesweeper/index.php`, zodat je gsm de pagina kan
     bereiken. Beide toestellen op hetzelfde wifi-netwerk.

## Database

```sql
CREATE TABLE users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  gebruikersnaam  VARCHAR(40) UNIQUE NOT NULL,
  wachtwoord_hash VARCHAR(255) NOT NULL,
  aangemaakt      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE scores (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id   INT NOT NULL,
  level     ENUM('beginner','intermediate','expert') NOT NULL,
  tijd      INT NOT NULL,                       -- duur in seconden
  gewonnen  BOOLEAN NOT NULL,
  gespeeld  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE controller_state (
  code     VARCHAR(8) PRIMARY KEY,              -- koppelcode gsm <-> laptop
  command  VARCHAR(16) NOT NULL DEFAULT '',
  seq      INT NOT NULL DEFAULT 0,              -- volgnummer per nieuw commando
  updated  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE user_achievements (
  user_id        INT NOT NULL,
  achievement_id VARCHAR(40) NOT NULL,
  behaald_op     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, achievement_id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

- **users** — accountgegevens; het wachtwoord wordt enkel als bcrypt-hash bewaard.
- **scores** — één rij per gespeeld spel; voedt het scorebord, de grafiek,
  de achievements en de PDF's.
- **controller_state** — de gedeelde "brievenbus" voor de kantel-controller.
- **user_achievements** — welke achievements een speler al behaald heeft.

## Architectuur

### Score opslaan en uitlezen
De game stuurt na elk spel een score via `fetch` naar `api/score.php`
(REST POST). Het scorebord en de persoonlijke pagina lezen scores uit via
`api/score.php` (REST GET, met jQuery + Ajax). Alle databasetoegang gebeurt met
**prepared statements** (PDO) tegen SQL-injectie. Het `user_id` komt steeds uit
de PHP-sessie, nooit uit de client.

### Authenticatie
`api/auth.php` regelt registreren, inloggen en uitloggen. Wachtwoorden via
`password_hash()` / `password_verify()`. Na login wordt `$_SESSION['user_id']`
gezet — exact wat de andere endpoints uitlezen.

### Smartphone-controller (REST + kantelsensor)
Gsm en laptop delen geen sessie, dus ze vinden elkaar via een **koppelcode** die
de spelpagina toont. De gsm leest `deviceorientation` en stuurt richtingen via
REST POST; de spelpagina haalt ze op via REST GET en beweegt een cursor. Het
veld `seq` zorgt dat eenzelfde commando niet twee keer wordt uitgevoerd.

### Achievements
De achievements worden **server-side afgeleid** uit de scores-tabel (de client
kan niets claimen). `includes/achievements.php` bevat de definities en een
evaluatiefunctie die je statistieken berekent, de condities controleert en
nieuw behaalde achievements opslaat in `user_achievements`.

### PDF-generatie
`api/certificaat.php` (Student A) en `api/rapport.php` (Student B) bouwen
server-side een PDF met **FPDF**. Het rapport wordt gevoed door één
SQL-aggregatie (`COUNT`, `SUM`, `MIN(CASE ...)`, `GROUP BY`).

## Documentatie per pagina

### `index.php` — Minesweeper (Student A)
De game in vanilla JavaScript. Kernmechanismen: **flood fill**, **first-click
safety** en **win-detectie**. Bij winst speelt een **canvas-confetti**-viering die
meeschaalt met de moeilijkheidsgraad, wordt de score verstuurd, en verschijnt
een link naar het PDF-certificaat.

### `scorebord.php` — Algemeen scorebord (Student A)
Toont per niveau de snelste spelers (jQuery + Ajax, auto-refresh). Per speler
enkel de beste tijd (`MIN(tijd)` + `GROUP BY`). Namen via `.text()` tegen XSS.

### `controller.php` — Smartphone-bediening (Student A)
D-pad + actieknoppen die commando's via REST sturen; kantelbesturing leest
`deviceorientation`. (Tilt vereist HTTPS op iOS; de knoppen werken altijd.)

### `account.php` — Account (Student B)
Registreren, inloggen, profiel. Client-side validatie + Ajax naar `api/auth.php`.
Wachtwoorden gehasht; login geeft één algemene fout (geen user enumeration);
`session_regenerate_id()` na login.

### `mijn-scores.php` — Persoonlijke scores (Student B)
Statistieken en een **Chart.js**-grafiek van je wintijden, opgehaald via
`api/score.php?scope=mij`. Knop voor het PDF-rapport.

### `achievements.php` — Achievements (extra)
Badge-raster met ontgrendelde (in kleur) en vergrendelde (gedimd) achievements,
en voortgangsbalken. Data uit `api/achievements.php`.

## Taakverdeling

Elke student dekt de **volledige** basislijst op zijn eigen pagina's.

| | Ollivier Joachims | Rayan Kandichy |
|---|---|---|
| Pagina's | `index.php`, `scorebord.php` | `account.php`, `mijn-scores.php` |
| PHP | scorevalidatie, ranking | authenticatie, sessies |
| SQL | scores opslaan/uitlezen | users + scores |
| JS | spel-logica, scorebord | validatie, grafiek |
| 2 JS-libraries | jQuery + canvas-confetti | jQuery + Chart.js |
| REST + sensor | kantel-controller | `[OPEN — zie checklist]` |
| PHP voorbij les 5 | PDF-certificaat | PDF-rapport |

`[VUL IN: pas deze tabel aan jullie echte verdeling aan. Spreek af wie het
gedeelde achievementsysteem op zijn naam neemt als extra.]`

## Eisenchecklist

### Basis (50%)
| Eis | Ollivier Joachims | Rayan Kandichy |
|---|---|---|
| Git version control | ✅ | ✅ |
| GitHub voor samenwerking | ✅ | ✅ |
| README met documentatie | ✅ | ✅ |
| ≥ 2 niet-triviale pagina's | ✅ | ✅ |
| Server-side in PHP | ✅ | ✅ |
| Client-side in JS | ✅ | ✅ |
| HTML/CSS structuur | ✅ | ✅ |
| SQL: opslaan én uitlezen | ✅ | ✅ |
| RESTful API naar extern toestel | ✅ controller | 🔧 **OPEN** (QR verwijderd) |

### Geavanceerd (30%)
| Eis | Ollivier Joachims | Rayan Kandichy |
|---|---|---|
| Atomic commits & branches | 🔧 `[toon in git-historiek]` | 🔧 `[toon in git-historiek]` |
| PHP voorbij lecture 5 | ✅ PDF-certificaat | ✅ PDF-rapport |
| jQuery + Ajax | ✅ scorebord | ✅ mijn-scores |
| ≥ 2 JS-libraries | ✅ jQuery + canvas-confetti | ✅ jQuery + Chart.js |
| Extern toestel als sensor | ✅ kantelsensor | 🔧 **OPEN** (QR verwijderd) |

> **Let op:** door het verwijderen van de QR-login mist Student B nu zowel de
> basisvereiste "RESTful API naar een extern toestel" (50%) als de geavanceerde
> "extern toestel werkt als sensor" (30%). Student B heeft hiervoor nog een
> (eenvoudigere) feature met een extern toestel nodig.

### Extra (20%)
| Functionaliteit | Status |
|---|---|
| Achievementsysteem (server-side afgeleid uit scores) | ✅ |

## Bekende beperkingen & mogelijke uitbreidingen

- **Controller, laatste commando wint.** `controller_state` bewaart enkel het
  meest recente commando; een commando-wachtrij zou dit oplossen.
- **Kantelsensor op iOS vereist HTTPS.** De d-pad-knoppen werken altijd, ook
  zonder sensor — een betrouwbaar vangnet voor de demo.
- **Pollen i.p.v. push.** Controller en scorebord pollen op intervallen;
  WebSockets zouden efficiënter zijn.
