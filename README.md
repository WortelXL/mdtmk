# MDT — Mobiel Data Terminal

Losse, mobiel-vriendelijke app voor crew op terrein tijdens een evenement.
MDT is het tweede systeem naast **MKAPP** (het meldkamersysteem zelf) en
**MK-Intranet**: een eigen Docker-app die verbindt met **dezelfde**
MariaDB-database als MKAPP, maar dan vanaf de telefoon van de crew.

Status: **fase M2 + M6 — logboek, eenheidsstatus, Teams en los
MDT-gebruikersbeheer.** Zie `voorstel_mdt_fasering.md` en
`voorstel_mdt_gebruikersbeheer.md` in het MKAPP-project (Cowork) voor
de volledige fasering. Zie `CHANGELOG.md` voor de versiehistorie per
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
- Met 1 tik je eenheidsstatus doorgeven (OW · TP · IR · BS · PS · OP,
  fase M2) — komt automatisch als logboekregel op elke actieve
  toegewezen melding te staan. Kan per account uitgezet zijn (fase M6).

Een foto uploaden/delen en de crew-lijst met bellen komen in fase
M3/M4.

## Belangrijk: MDT heeft GEEN eigen database

MDT heeft bewust geen eigen `db`-service in `docker-compose.yml` — het
verbindt met de **bestaande** MKAPP-database. Zorg dus dat:

1. MariaDB van MKAPP bereikbaar is vanaf de server waar MDT draait
   (poort 3306, netwerk/firewall op orde — zie de "Openstaande punten"
   in het voorstel als MDT op een andere server komt te staan dan
   MKAPP).
2. MKAPP zelf minimaal op **V2.0.2.2** staat (die versie voegt de
   tabel `mdt_gebruikers` toe — zonder die kan niemand meer inloggen op
   MDT, want MDT-toegang wordt sinds fase M6 daar bepaald, niet meer
   via de oude `gebruikers.mag_inloggen_mdt`-kolom).

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

-- Schrijven (fase M2: logboek terugschrijven + eenheidsstatus doorgeven)
GRANT INSERT ON mkapp.melding_notities TO 'mdt_user'@'%';
GRANT UPDATE (huidige_eenheidsstatus_id) ON mkapp.gebruikers TO 'mdt_user'@'%';

FLUSH PRIVILEGES;
```

Had je dit account al vóór fase M6 aangemaakt? Dan is het genoeg om
alleen de 2 nieuwe regels hierboven (`mdt_gebruikers` en `rollen`)
opnieuw uit te voeren — de rest heb je al. Had je het al vóór fase M2,
dan gelden ook nog de 4 regels uit die fase (`teams`,
`eenheidsstatussen`, het INSERT-recht en de kolom-update).

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
├── melding.php?id=           melddetail + logboek (lezen + toevoegen)
├── status.php                 POST-only: eenheidsstatus zetten
├── Dockerfile
├── docker-compose.yml
└── CHANGELOG.md            versiegeschiedenis (los van de MKAPP-fasering M1-M5)
```
