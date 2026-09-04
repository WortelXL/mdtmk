# MDT — Mobiel Data Terminal

Losse, mobiel-vriendelijke app voor crew op terrein tijdens een evenement.
MDT is het tweede systeem naast **MKAPP** (het meldkamersysteem zelf) en
**MK-Intranet**: een eigen Docker-app die verbindt met **dezelfde**
MariaDB-database als MKAPP, maar dan vanaf de telefoon van de crew.

Status: **fase M2 — logboek, eenheidsstatus en Teams.** Zie
`voorstel_mdt_fasering.md` in het MKAPP-project (Cowork) voor de
volledige fasering (M1 t/m M5). Zie `CHANGELOG.md` voor de
versiehistorie per wijziging.

## Wat MDT tot nu toe doet

- Inloggen met een bestaand MKAPP-account (of een account dat alleen
  voor MDT is aangemaakt) — vereist dat `mag_inloggen_mdt` aan staat
  voor dat account (Beheer → Gebruikers in MKAPP, sinds MKAPP V2.0.2.0).
- Een lijst van je eigen, actief toegewezen meldingen — rechtstreeks
  (`toegewezen_aan_gebruiker_id`) of via een team dat aan je account
  gekoppeld is (`toegewezen_aan_team_id`, Beheer → Teams in MKAPP,
  sinds MKAPP V2.0.2.1).
- De details van 1 melding, inclusief het bestaande logboek.
- Zelf een logboekregel toevoegen (fase M2).
- Met 1 tik je eenheidsstatus doorgeven (OW · TP · IR · BS · PS · OP,
  fase M2) — komt automatisch als logboekregel op elke actieve
  toegewezen melding te staan.

Een foto uploaden/delen en de crew-lijst met bellen komen in fase
M3/M4.

## Belangrijk: MDT heeft GEEN eigen database

MDT heeft bewust geen eigen `db`-service in `docker-compose.yml` — het
verbindt met de **bestaande** MKAPP-database. Zorg dus dat:

1. MariaDB van MKAPP bereikbaar is vanaf de server waar MDT draait
   (poort 3306, netwerk/firewall op orde — zie de "Openstaande punten"
   in het voorstel als MDT op een andere server komt te staan dan
   MKAPP).
2. MKAPP zelf minimaal op **V2.0.2.1** staat (die versie voegt de
   kolommen `mag_inloggen_mdt`, `huidige_eenheidsstatus_id` en de
   tabellen `teams`/`eenheidsstatussen` toe — zonder die kan niemand
   inloggen op MDT en werken de eenheidsstatus/Teams-functies niet).

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

-- Schrijven (fase M2: logboek terugschrijven + eenheidsstatus doorgeven)
GRANT INSERT ON mkapp.melding_notities TO 'mdt_user'@'%';
GRANT UPDATE (huidige_eenheidsstatus_id) ON mkapp.gebruikers TO 'mdt_user'@'%';

FLUSH PRIVILEGES;
```

Had je dit account al vóór fase M2 aangemaakt? Dan is het genoeg om
alleen de 4 nieuwe `GRANT`-regels hierboven (de laatste 4) opnieuw uit
te voeren — de rest heb je al.

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
