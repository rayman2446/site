# Minesweeper — webproject

> Een online Minesweeper met accounts, een persoonlijk en algemeen scorebord,
> en een smartphone die via REST als afstandsbediening/sensor dient.

**Groep:** `1EAIa`
**Studenten:** `Ryan Kandichy` · `Ollivier Joachims`
**Repository:** `https://github.com/rayman2446/site`

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
database. Een algemeen scorebord toont de snelste spelers; op je eigen
profiel zie je je persoonlijke geschiedenis. Als extra kun je het spel
besturen met je smartphone, die zijn kantelsensor over REST naar de
spelpagina stuurt.

## Technologie

| Onderdeel        | Gebruikt |
|------------------|----------|
| Structuur/opmaak | HTML, CSS |
| Client-side      | JavaScript (vanilla), jQuery, Ajax |
| Server-side      | PHP (PDO) |
| Database         | MySQL / MariaDB |
| Samenwerking     | Git + GitHub |
| Extern toestel   | Smartphone via REST (kantelsensor) |

## Mappenstructuur

```
minesweeper/
├── README.md
├── index.php            # de minesweeper-pagina (Ollivier)
├── scorebord.php        # algemeen scorebord, jQuery + Ajax (Olliver)
├── controller.php       # bedieningspagina voor de smartphone (Ollivier)
├── account.php          # registreren / inloggen / profiel (Ryan)
├── mijn-scores.php      # persoonlijke scores + grafiek (Ryan)
├── api/
│   ├── score.php        # REST: score opslaan (POST) en uitlezen (GET)
│   └── controller.php   # REST: commando's tussen gsm en spelpagina
├── includes/
│   └── db.php           # herbruikbare databaseverbinding (PDO)
├── js/
│   └── game-controller.js   # koppelt de smartphone-controller aan de game
└── css/
    └── style.css        # gedeelde opmaak
```

## Installatie

1. Installeer **XAMPP** (of een andere LAMP-stack) en start Apache + MySQL.
2. Plaats de projectmap in `htdocs/` (XAMPP) of `www/`.
3. Maak in **phpMyAdmin** een database met de naam `minesweeper`.
4. Voer het SQL-script uit de sectie [Database](#database) uit.
5. Pas in `includes/db.php` desnoods de gebruikersnaam/wachtwoord aan
   (bij XAMPP standaard `root` zonder wachtwoord).
6. Open het project in je browser:
   - lokaal testen: `http://localhost/minesweeper/index.php`
   - **voor de smartphone-controller:** open via het lokale IP van je laptop,
     bv. `http://192.168.0.42/minesweeper/index.php`, zodat je gsm de pagina
     kan bereiken. Beide toestellen moeten op hetzelfde wifi-netwerk zitten.

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
```

- **users** — accountgegevens. Het wachtwoord wordt nooit als platte tekst
  bewaard, enkel als hash (`password_hash()` in PHP).
- **scores** — één rij per gespeeld spel. Het algemene scorebord leest hieruit;
  de persoonlijke pagina filtert op `user_id`.
- **controller_state** — de gedeelde "brievenbus" tussen smartphone en laptop
  (zie [Architectuur](#architectuur)).

## Architectuur

### Score opslaan en uitlezen
De game (JavaScript) stuurt na elk spel een score via `fetch` naar
`api/score.php` (REST `POST`). Het scorebord vraagt scores op via
`api/score.php` (REST `GET`) met jQuery + Ajax, en ververst automatisch.
Alle databasetoegang gebeurt met **prepared statements** (PDO), wat
SQL-injectie tegengaat.

### Smartphone-controller (REST + sensor)
Gsm en laptop zijn aparte toestellen en delen dus geen PHP-sessie. Ze vinden
elkaar via een **koppelcode** die de spelpagina toont:

```
   SMARTPHONE                  SERVER (PHP + MySQL)              LAPTOP
  controller.php               api/controller.php             index.php
 ─────────────────            ───────────────────           ──────────────
 leest kantelsensor   POST →   schrijft commando
 (deviceorientation)           in controller_state
                                       │
                                       ▼
                               leest laatste     ← GET   pollt elke 200 ms,
                               commando uit               beweegt de cursor
```

Het veld `seq` (volgnummer) stijgt bij elk nieuw commando. De spelpagina
onthoudt het laatst verwerkte volgnummer en handelt enkel bij een hoger
nummer, zodat hetzelfde commando niet herhaaldelijk wordt uitgevoerd.

## Documentatie per pagina

### `index.php` — Minesweeper
De game zelf in vanilla JavaScript. Belangrijkste mechanismen:
- **flood fill** — een leeg vakje (0 buren) opent recursief al zijn buren;
- **first-click safety** — mijnen worden pas ná de eerste klik geplaatst,
  weg van het aangeklikte vakje;
- **win-detectie** — gewonnen zodra alle niet-mijn-vakjes open staan.
Bij het einde van een spel wordt de score naar `api/score.php` gestuurd.

### `scorebord.php` — Algemeen scorebord
Toont per moeilijkheidsgraad de snelste spelers, opgehaald met **jQuery + Ajax**
en automatisch ververst (live scorebord). Per speler wordt enkel zijn beste
tijd getoond (`MIN(tijd)` + `GROUP BY`). Gebruikersnamen worden met `.text()`
ingevuld, niet `.html()`, ter bescherming tegen XSS.

### `controller.php` — Smartphone-bediening
Bedieningspagina die je op je gsm opent. Een d-pad en actieknoppen sturen
commando's via REST; de kantelbesturing leest `deviceorientation` en zet
kanteling om naar richtingscommando's. `[Let op: tilt vereist HTTPS op iOS.]`

### `account.php` — Account
Registreren, inloggen en profiel beheren. Schrijft naar **users** (registratie,
wachtwoord wijzigen) en leest eruit (login). Wachtwoorden via `password_hash()`,
sessies via `session_start()`.
`[Geplande REST + extern toestel: QR-login of avatarfoto-upload vanaf de gsm.]`

### `mijn-scores.php` — Persoonlijke scores
Toont de eigen scoregeschiedenis uit **scores**, gefilterd op `user_id`, met
een grafiek van je tijden.
`[Geplande 2e JS-library: Chart.js. Geplande PHP-extra: PDF-rapport van je statistieken.]`

## Taakverdeling

Elke student dekt de **volledige** basislijst op zijn eigen pagina's.

| | Ollivier | Ryan |
|---|---|---|
| Pagina's | `index.php`, `scorebord.php` | `account.php`, `mijn-scores.php` |
| PHP | scorevalidatie, ranking | authenticatie, sessies, profiel |
| SQL | scores opslaan/uitlezen | users opslaan/uitlezen |
| JS | spel-logica, scorebord | validatie, grafiek |
| REST + sensor | gsm als kantel-controller | QR-login |

## Eisenchecklist

Status: ✅ klaar · 🔧 nog te doen

### Basis (50%)
| Eis | Ollivier | Ryan |
|---|---|---|
| Git version control | ✅ | ✅ |
| GitHub voor samenwerking | ✅ | ✅ |
| README met documentatie | ✅ (dit bestand) | ✅ |
| ≥ 2 niet-triviale pagina's | ✅ game + scorebord | 🔧 account + mijn-scores |
| Server-side in PHP | ✅ `api/score.php` | 🔧 |
| Client-side in JS | ✅ spel-logica | 🔧 |
| HTML/CSS structuur | ✅ | 🔧 |
| SQL: opslaan én uitlezen | ✅ scores | 🔧 users |
| RESTful API naar extern toestel | ✅ gsm-controller | 🔧 |

### Geavanceerd (30%)
| Eis | Ollivier | Ollivier |
|---|---|---|
| Atomic commits & branches | 🔧  | 🔧 |
| PHP voorbij lecture 5 | 🔧 ` | 🔧  |
| jQuery + Ajax | ✅ scorebord | 🔧 |
| ≥ 2 JS-libraries | 🔧 jQuery + Canvas-confetti | 🔧 jQuery + Chart.js |
| Extern toestel als sensor | ✅ kantelsensor | 🔧 camera |

### Extra (20%)
| Idee | Status |
|---|---|
| 🔧 | 🔧 |

## Bekende beperkingen & mogelijke uitbreidingen

- **Controller, laatste commando wint.** `controller_state` bewaart enkel het
  meest recente commando. Stuurt de gsm twee commando's tussen twee polls in,
  dan gaat het eerste verloren. Een commando-wachtrij zou dit oplossen.
- **Kantelsensor op iOS vereist HTTPS** en een toestemmingsvraag. De d-pad-knoppen
  werken altijd, ook zonder sensor — een betrouwbaar vangnet voor de demo.
- **Pollen i.p.v. push.** De spelpagina vraagt elke 200 ms naar nieuwe commando's.
  Met WebSockets zou dit efficiënter (en sneller) kunnen.

---

<img width="690" height="786" alt="image" src="https://github.com/user-attachments/assets/1cadcbe3-748a-457e-9208-1b2d9856abc3" />

