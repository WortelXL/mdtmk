# MDT — Mobiel Data Terminal

Losse, mobiel-vriendelijke app voor crew op terrein tijdens een evenement.
MDT is het tweede systeem naast **MKAPP** (het meldkamersysteem zelf) en
**MK-Intranet**: een eigen Docker-app die verbindt met **dezelfde**
MariaDB-database als MKAPP, maar dan vanaf de telefoon van de crew.

Status: **fase M1 + M2 + M3 + M6 + M7 + M4 + M5 — logboek,
eenheidsstatus (nu per rol), Teams, los MDT-gebruikersbeheer, een
crew-lijst met bellen, statusbeheer + plotbord, foto's uploaden/delen,
en push-berichten via Teams. Alle geplande fasen (M1 t/m M5) zijn nu
gebouwd.** Zie `voorstel_mdt_fasering.md` en
`voorstel_mdt_gebruikersbeheer.md` in het MKAPP-project (Cowork) voor de
volledige fasering. Zie `CHANGELOG.md` voor de versiehistorie per
wijziging.

## Wat MDT tot nu toe doet

- Inloggen met een bestaand MKAPP-account (of een account dat alleen
  voor MDT is aangemaakt) — vereist een actieve rij bij Beheer →
  MDT-gebruikers in MKAPP (sinds MKAPP V2.0.2.2, fase M6).
- Een lijst van je eigen, actief toegewezen meldingen — rechtstreeks
  (`toegewezen_aan_gebruiker_id`) of via een team dat aan je account
  gekoppeld is (`toegewezen_aan_team_id`, Beheer → Teams in MKAPP,
  sinds MKAPP V2.0.2.1). Heeft je account "Alle meldingen" aanstaan
  (fase M6), dan kun je wisselen naar een volledige lijst — optioneel
  beperkt tot de classificatie van een gekoppelde rol.
- De details van 1 melding, inclusief het bestaande logboek.
- Zelf een logboekregel toevoegen (fase M2) — tenzij je account op
  alleen-lezen staat (fase M6). Regels vanuit MDT zijn in MKAPP
  herkenbaar met een "MDT"-label.
- Met 1 tik je eenheidsstatus doorgeven (fase M2) — komt automatisch
  als logboekregel op elke actieve toegewezen melding te staan. Kan per
  account uitgezet zijn (fase M6). Sinds fase M7 hoort elke status bij
  een rol (bv. EHBO of Bouwploeg, zelf te beheren via Beheer >
  Eenheidsstatussen in MKAPP) — je ziet alleen de statussen van je
  eigen gekoppelde rol (Beheer > MDT-gebruikers in MKAPP); zonder
  gekoppelde rol zie je geen statusknoppen.
- Een crew-lijst met belknop (fase M3, nieuwe navigatietab "Crew") —
  de bestaande crew (contactpersonen zonder account) samen met je
  collega's die een MDT-account én telefoonnummer hebben, in 1
  gesorteerde lijst.
- (In MKAPP zelf, fase M7) Een melding met een toegewezen team of
  MDT-gebruiker toont voortaan diens actuele eenheidsstatus, en een
  nieuw "Plotbord" (bij Meldingen in MKAPP) toont alle teams en losse
  MDT-gebruikers met hun status in 1 overzicht.
- Foto's toevoegen bij een melding (fase M4) — vanaf de
  melddetailpagina, naast het logboek, meerdere foto's per melding
  (camera of galerij). Elke toevoeging komt ook als logboekregel te
  staan; de foto zelf staat op MDT's eigen schijf/volume (niet in de
  database), en is ook in MKAPP's eigen melding-pagina te zien. Kan
  net als het logboek niet gebruikt worden als je account op
  alleen-lezen staat (fase M6).
- Pushmeldingen (fase M5, nieuw paneel "Pushmeldingen" op Mijn
  meldingen) — wordt een melding aan een team toegewezen, dan krijgt de
  MDT-gebruiker die op dat moment aan dat team gekoppeld is een
  pushbericht op elk toestel waarop hij dit heeft aangezet; een tik
  erop opent de melding direct. Werkt alleen via een beveiligde
  verbinding (HTTPS) — zie "Pushmeldingen" hieronder voor de volledige
  uitleg en de vereiste instellingen.

## Belangrijk: MDT heeft GEEN eigen database

MDT heeft bewust geen eigen `db`-service in `docker-compose.yml` — het
verbindt met de **bestaande** MKAPP-database. Zorg dus dat:

1. MariaDB van MKAPP bereikbaar is vanaf de server waar MDT draait
   (poort 3306, netwerk/firewall op orde — zie de "Openstaande punten"
   in het voorstel als MDT op een andere server komt te staan dan
   MKAPP).
2. MKAPP zelf minimaal op **V2.0.2.6** staat (V2.0.2.2 voegt de tabel
   `mdt_gebruikers` toe — zonder die kan niemand meer inloggen op MDT,
   want MDT-toegang wordt sinds fase M6 daar bepaald, niet meer via de
   oude `gebruikers.mag_inloggen_mdt`-kolom; V2.0.2.3 voegt daar het
   telefoonnummer-veld aan toe dat de crew-lijst gebruikt; V2.0.2.4
   voegt een rol-koppeling toe aan `eenheidsstatussen` — zonder die
   koppeling ziet niemand meer statusknoppen in MDT, geen nieuwe
   GRANT-regel nodig, `eenheidsstatussen` en `rollen` waren al leesbaar
   sinds fase M2/M6; V2.0.2.5 voegt de tabel `melding_bijlagen` toe —
   zonder die kan MDT geen foto's meer wegschrijven en toont MKAPP's
   melding-pagina geen "Foto's"-sectie; V2.0.2.6 voegt de tabel
   `push_abonnementen` toe en geeft de bestaande webhook "Melding
   toegewezen aan team" er een gebruikers-ID bij — zonder die twee kan
   MDT geen pushberichten versturen).
3. `APP_BASE_URL` (zie hieronder) op MDT zelf goed staat, anders wijst
   een geuploade foto naar een adres dat niemand buiten MDT kan
   bereiken.
4. Voor pushmeldingen (fase M5): MDT bereikbaar is via een geldig
   HTTPS-adres, en Beheer > Connectiviteit in MKAPP een webhook heeft
   die naar MDT's `webhook_ontvangen.php` wijst — zie "Pushmeldingen"
   hieronder.

### Een beperkt databaseaccount voor MDT

Gebruik NIET het bestaande `phpserver`-account van MKAPP. Maak een
eigen, beperkt account aan dat alleen mag wat MDT nodig heeft. Draai
dit op de MariaDB-server van MKAPP (vervang `<mdt-wachtwoord>` en,
indien MDT op een andere server staat, `'%'` door het specifieke
IP-adres van de MDT-server):

```sql
CREATE USER 'mdt_user'@'%' IDENTIFIED BY '<mdt-wachtwoord>';

-- Lezen
GRANT SELECT ON mkapp.gebruikers TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.meldingen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.melding_notities TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.statussen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.hoofdclassificaties TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.subclassificaties TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.teams TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.eenheidsstatussen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.mdt_gebruikers TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.rollen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.crew TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.melding_bijlagen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.push_abonnementen TO 'mdt_user'@'%';

-- Schrijven (fase M2: logboek terugschrijven + eenheidsstatus doorgeven;
-- fase M4: foto-metadata wegschrijven; fase M5: push-abonnementen beheren)
GRANT INSERT ON mkapp.melding_notities TO 'mdt_user'@'%';
GRANT UPDATE (huidige_eenheidsstatus_id) ON mkapp.gebruikers TO 'mdt_user'@'%';
GRANT INSERT ON mkapp.melding_bijlagen TO 'mdt_user'@'%';
GRANT INSERT, UPDATE, DELETE ON mkapp.push_abonnementen TO 'mdt_user'@'%';

FLUSH PRIVILEGES;
```

Had je dit account al vóór fase M5 aangemaakt? Dan is het genoeg om
alleen de 2 nieuwe regels hierboven (`push_abonnementen` lezen +
schrijven/bijwerken/verwijderen) opnieuw uit te voeren — de rest heb je
al. Had je het al vóór fase M4, dan gelden ook nog de 2 regels uit die
fase (`melding_bijlagen` lezen + schrijven). Had je het al vóór fase
M3, dan geldt ook nog de regel uit die fase (`crew`). Had je het al
vóór fase M6, dan gelden ook nog de 2 regels uit die fase
(`mdt_gebruikers` en `rollen`). Had je het al vóór fase M2, dan gelden
ook nog de 4 regels uit die fase (`teams`, `eenheidsstatussen`, het
INSERT-recht en de kolom-update).

### Foto's: eigen opslag + APP_BASE_URL (fase M4)

Foto's die vanuit MDT geupload worden, komen NIET in de database
terecht — ze staan als gewone bestanden op MDT's eigen schijf/volume
(`/uploads`, zie `docker-compose.yml`), alleen de bestandsnaam + een
volledige URL komen in de gedeelde tabel `melding_bijlagen` te staan.
Die URL wordt opgebouwd uit de omgevingsvariabele `APP_BASE_URL` — zet
die dus op het adres waarop MDT voor de kijker (dus ook iemand die
MKAPP gebruikt, niet alleen de crew op straat) daadwerkelijk bereikbaar
is. Lokaal/tijdens testen is de standaardwaarde
(`http://localhost:8081`) prima; zodra MDT een eigen domein heeft, pas
je dit aan in `docker-compose.yml`. Vergeet de foto's zelf niet mee te
nemen in je eigen back-upstrategie — deze staan niet in de MariaDB-
database en dus ook niet in een eventuele databasebackup.

### Pushmeldingen (fase M5)

Web Push (de browserstandaard die dit gebruikt) vereist een **geldig
HTTPS-certificaat** — een self-signed certificaat of gewoon
`http://` werkt niet. MDT blijft daarnaast gewoon bereikbaar via het
lokale netwerk (bv. voor foto's/logboek) — pushmeldingen zelf werken
dan alleen niet op dat lokale adres; het "Pushmeldingen"-paneel op Mijn
meldingen verbergt zichzelf automatisch (met een uitleg) als de
verbinding niet beveiligd is, de rest van MDT blijft gewoon werken.

Om pushmeldingen aan te zetten:

1. **Eigen VAPID-sleutelpaar genereren** (uniek per installatie, nooit
   hergebruiken/delen): na de eerste `docker compose up -d --build`
   (ook nodig om de composer-library binnen te halen, zie hieronder):
   ```bash
   docker compose exec app php genereer_vapid_sleutels.php
   ```
   Zet de 2 uitgevoerde sleutels in `docker-compose.yml` bij
   `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY`, en `VAPID_SUBJECT` op een
   eigen `mailto:`-adres. Herstart daarna MDT
   (`docker compose up -d --build`).
2. **Een eigen, onraadbaar `WEBHOOK_TOKEN` kiezen** in
   `docker-compose.yml` (vervangt de placeholder
   `wijzig_dit_token`) — dit is het simpele gedeelde geheim waarmee
   `webhook_ontvangen.php` controleert dat een binnenkomend verzoek
   echt van MKAPP komt.
3. **Bij Beheer > Connectiviteit in MKAPP** een nieuwe webhook
   aanmaken: platform "Generiek (JSON)", gebeurtenis "Melding
   toegewezen aan team", en als URL:
   `https://<MDT-domein>/webhook_ontvangen.php?token=<jouw WEBHOOK_TOKEN>`
   (dus met hetzelfde token als stap 2, en het echte HTTPS-adres van
   MDT — niet het lokale-netwerkadres).
4. **Bij Beheer > Teams in MKAPP** een MDT-gebruiker aan een team
   koppelen** — een pushbericht gaat naar wie op het moment van
   toewijzen aan dat team gekoppeld staat.
5. Elke MDT-gebruiker zet zelf pushmeldingen aan via het
   "Pushmeldingen"-paneel op Mijn meldingen (1 tik, vraagt eenmalig
   toestemming van de browser) — dit moet dus vanaf het echte,
   beveiligde MDT-adres gebeuren, niet via het lokale-netwerkadres.

Web Push zelf (de versleuteling + ondertekening van elk bericht) gaat
via de beproefde, veelgebruikte `minishlink/web-push`-library
(composer) — bewust een uitzondering op de rest van dit project (dat
verder bewust dependency-vrij is): bij deze ene, foutgevoelige
cryptografie weegt een bekende, onderhouden library zwaarder dan
consistentie met de rest van de codebase. `composer install` gebeurt
automatisch tijdens `docker compose up -d --build` (zie Dockerfile) —
je hoeft zelf niets met composer te doen.

**iOS/Safari-kanttekening**: op een iPhone werkt Web Push alleen als
MDT eerst is toegevoegd aan het beginscherm ("Zet op beginscherm" in
Safari, iOS 16.4+) — rechtstreeks in de Safari-browser zelf werkt het
niet. Android/Chrome werkt gewoon direct in de browser.

## Lokaal draaien

```bash
cp docker-compose.yml docker-compose.override.yml   # optioneel, voor eigen lokale env-waarden
docker compose up -d --build
```

Pas in `docker-compose.yml` de environment-variabelen aan:

- `DB_HOST` / `DB_PORT` — waar MKAPP's MariaDB bereikbaar is
- `DB_NAME` — meestal `mkapp`
- `DB_USER` / `DB_PASS` — het beperkte MDT-account hierboven
- Poort: standaard `8081:80` (MKAPP zelf gebruikt al poort 80) — komt
  MDT op een andere server te staan, dan kan dit gewoon `80:80` worden.
- `APP_BASE_URL` (fase M4) — zie hierboven; hoort bij dezelfde poort/
  domein als je hierboven instelt.
- `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` /
  `WEBHOOK_TOKEN` (fase M5) — zie "Pushmeldingen" hierboven. Leeg
  laten (VAPID) betekent: geen pushmeldingen versturen, de rest van
  MDT blijft gewoon werken.

De `volumes`-regel in `docker-compose.yml` (`./data/uploads:/var/www/html/uploads`)
zorgt dat geuploade foto's bewaard blijven bij een herstart/rebuild —
niet weghalen.

## Technische stack

Zelfde stack als MKAPP, voor eenvoudig onderhoud door dezelfde
persoon/team: PHP 8.3 + Apache in Docker, geen framework, eigen
dark-theme CSS (bewust dezelfde kleuren als MKAPP, zie
`assets/style.css`) — maar dan mobiel-eerst: grote knoppen, 1 kolom,
minimale schermen.

```
mdtmk/
├── config.php              DB-verbinding + VAPID/webhook-instellingen (via env-variabelen)
├── includes/
│   ├── db.php               get_pdo()
│   ├── functions.php        inloggen, meldingen/logboek lezen+schrijven, eenheidsstatus, push
│   ├── header.php / footer.php
├── assets/style.css
├── login.php
├── logout.php
├── index.php                 "Mijn meldingen" + eenheidsstatus-knoppen + pushmeldingen-paneel
├── melding.php?id=           melddetail + logboek + foto's (lezen + toevoegen)
├── status.php                 POST-only: eenheidsstatus zetten
├── crew.php                   Crew + collega's, gecombineerde bellijst (fase M3)
├── uploads/                   geuploade foto's (fase M4, niet in git -- via volume)
├── sw.js                       service worker: ontvangt pushberichten (fase M5)
├── push_abonneren.php         POST-only: push-abonnement opslaan/verwijderen (fase M5)
├── webhook_ontvangen.php     ontvangt MKAPP's webhook, stuurt het pushbericht (fase M5)
├── genereer_vapid_sleutels.php   eenmalig CLI-hulpscript (fase M5)
├── composer.json              minishlink/web-push (fase M5, niet in git: vendor/)
├── Dockerfile
├── docker-compose.yml
└── CHANGELOG.md            versiegeschiedenis (los van de MKAPP-fasering M1-M5)
```
