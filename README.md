# MDT — Mobiel Data Terminal

Losse, mobiel-vriendelijke app voor crew op terrein tijdens een evenement.
MDT is het tweede systeem naast **MKAPP** (het meldkamersysteem zelf) en
**MK-Intranet**: een eigen Docker-app die verbindt met **dezelfde**
MariaDB-database als MKAPP, maar dan vanaf de telefoon van de crew.

Status: **fase M1 + M2 + M3 + M6 + M7 + M4 — logboek, eenheidsstatus (nu
per rol), Teams, los MDT-gebruikersbeheer, een crew-lijst met bellen,
statusbeheer + plotbord, en foto's uploaden/delen.** Zie
`voorstel_mdt_fasering.md` en `voorstel_mdt_gebruikersbeheer.md` in
het MKAPP-project (Cowork) voor de volledige fasering. Zie
`CHANGELOG.md` voor de versiehistorie per wijziging.

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

Een push-bericht ontvangen vanuit MKAPP komt in fase M5.

## Belangrijk: MDT heeft GEEN eigen database

MDT heeft bewust geen eigen `db`-service in `docker-compose.yml` — het
verbindt met de **bestaande** MKAPP-database. Zorg dus dat:

1. MariaDB van MKAPP bereikbaar is vanaf de server waar MDT draait
   (poort 3306, netwerk/firewall op orde — zie de "Openstaande punten"
   in het voorstel als MDT op een andere server komt te staan dan
   MKAPP).
2. MKAPP zelf minimaal op **V2.0.2.5** staat (V2.0.2.2 voegt de tabel
   `mdt_gebruikers` toe — zonder die kan niemand meer inloggen op MDT,
   want MDT-toegang wordt sinds fase M6 daar bepaald, niet meer via de
   oude `gebruikers.mag_inloggen_mdt`-kolom; V2.0.2.3 voegt daar het
   telefoonnummer-veld aan toe dat de crew-lijst gebruikt; V2.0.2.4
   voegt een rol-koppeling toe aan `eenheidsstatussen` — zonder die
   koppeling ziet niemand meer statusknoppen in MDT, geen nieuwe
   GRANT-regel nodig, `eenheidsstatussen` en `rollen` waren al leesbaar
   sinds fase M2/M6; V2.0.2.5 voegt de tabel `melding_bijlagen` toe —
   zonder die kan MDT geen foto's meer wegschrijven en toont MKAPP's
   melding-pagina geen "Foto's"-sectie).
3. `APP_BASE_URL` (zie hieronder) op MDT zelf goed staat, anders wijst
   een geuploade foto naar een adres dat niemand buiten MDT kan
   bereiken.

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

-- Schrijven (fase M2: logboek terugschrijven + eenheidsstatus doorgeven;
-- fase M4: foto-metadata wegschrijven)
GRANT INSERT ON mkapp.melding_notities TO 'mdt_user'@'%';
GRANT UPDATE (huidige_eenheidsstatus_id) ON mkapp.gebruikers TO 'mdt_user'@'%';
GRANT INSERT ON mkapp.melding_bijlagen TO 'mdt_user'@'%';

FLUSH PRIVILEGES;
```

Had je dit account al vóór fase M4 aangemaakt? Dan is het genoeg om
alleen de 2 nieuwe regels hierboven (`melding_bijlagen` lezen +
schrijven) opnieuw uit te voeren — de rest heb je al. Had je het al
vóór fase M3, dan geldt ook nog de regel uit die fase (`crew`). Had je
het al vóór fase M6, dan gelden ook nog de 2 regels uit die fase
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
├── config.php              DB-verbinding (via env-variabelen)
├── includes/
│   ├── db.php               get_pdo()
│   ├── functions.php        inloggen, meldingen/logboek lezen+schrijven, eenheidsstatus
│   ├── header.php / footer.php
├── assets/style.css
├── login.php
├── logout.php
├── index.php                 "Mijn meldingen" + eenheidsstatus-knoppen
├── melding.php?id=           melddetail + logboek + foto's (lezen + toevoegen)
├── status.php                 POST-only: eenheidsstatus zetten
├── crew.php                   Crew + collega's, gecombineerde bellijst (fase M3)
├── uploads/                   geuploade foto's (fase M4, niet in git -- via volume)
├── Dockerfile
├── docker-compose.yml
└── CHANGELOG.md            versiegeschiedenis (los van de MKAPP-fasering M1-M5)
```
