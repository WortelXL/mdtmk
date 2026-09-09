# MDT — Mobiel Data Terminal

Losse, mobiel-vriendelijke app voor crew op terrein tijdens een evenement.
MDT is het tweede systeem naast **MKAPP** (het meldkamersysteem zelf) en
**MK-Intranet**: een eigen Docker-app die verbindt met **dezelfde**
MariaDB-database als MKAPP, maar dan vanaf de telefoon van de crew.

Status: **fase M1 + M2 + M3 + M6 + M7 + M5 — logboek, eenheidsstatus (nu
per rol), Teams, los MDT-gebruikersbeheer, een crew-lijst met bellen,
statusbeheer + plotbord, en pushmeldingen bij toewijzing.** Zie
`voorstel_mdt_fasering.md` en `voorstel_mdt_gebruikersbeheer.md` in
het MKAPP-project (Cowork) voor de volledige fasering. Zie
`CHANGELOG.md` voor de versiehistorie per wijziging.

Fase M4 (foto's uploaden/delen) is gebouwd geweest, maar op verzoek van
Max weer volledig verwijderd — zie `CHANGELOG.md` voor de versie
waarin dat gebeurde.

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
  1 of meerdere rollen (bv. EHBO of Bouwploeg, zelf te beheren via
  Beheer > Eenheidsstatussen in MKAPP — sinds MKAPP V2.0.2.28 kan dat
  meerdere rollen tegelijk zijn) — je ziet de statussen van elke rol
  die aan jou gekoppeld is (Beheer > MDT-gebruikers in MKAPP); zonder
  gekoppelde rol zie je geen statusknoppen.
- Een crew-lijst met belknop (fase M3, nieuwe navigatietab "Crew") —
  de bestaande crew (contactpersonen zonder account) samen met je
  collega's die een MDT-account én telefoonnummer hebben, in 1
  gesorteerde lijst.
- (In MKAPP zelf, fase M7) Een melding met een toegewezen team of
  MDT-gebruiker toont voortaan diens actuele eenheidsstatus, en een
  nieuw "Plotbord" (bij Meldingen in MKAPP) toont alle teams en losse
  MDT-gebruikers met hun status in 1 overzicht.
- Een echte browser-pushmelding ontvangen zodra een melding aan jou (of
  je team) wordt toegewezen (fase M5) — zie hieronder.

## Belangrijk: MDT heeft GEEN eigen database

MDT heeft bewust geen eigen `db`-service in `docker-compose.yml` — het
verbindt met de **bestaande** MKAPP-database. Zorg dus dat:

1. MariaDB van MKAPP bereikbaar is vanaf de server waar MDT draait
   (poort 3306, netwerk/firewall op orde — zie de "Openstaande punten"
   in het voorstel als MDT op een andere server komt te staan dan
   MKAPP).
2. MKAPP zelf minimaal op **V2.0.2.12** staat (V2.0.2.2 voegt de tabel
   `mdt_gebruikers` toe — zonder die kan niemand meer inloggen op MDT,
   want MDT-toegang wordt sinds fase M6 daar bepaald, niet meer via de
   oude `gebruikers.mag_inloggen_mdt`-kolom; V2.0.2.3 voegt daar het
   telefoonnummer-veld aan toe dat de crew-lijst gebruikt; V2.0.2.4
   voegt een rol-koppeling toe aan `eenheidsstatussen` — zonder die
   koppeling ziet niemand meer statusknoppen in MDT, geen nieuwe
   GRANT-regel nodig, `eenheidsstatussen` en `rollen` waren al leesbaar
   sinds fase M2/M6; V2.0.2.12 voegt de tabel `push_abonnementen` toe
   en zet `gekoppelde_gebruiker_id` in de payload van de
   `melding_toegewezen`-webhook (fase M5) — zonder die 2 dingen blijft
   het pushmeldingen-paneel in MDT gewoon verborgen, de rest van MDT
   werkt onveranderd door; V2.0.2.28 voegt de koppeltabel
   `eenheidsstatus_rollen` toe (een status kan voortaan bij meerdere
   rollen horen i.p.v. precies 1) — **vereist een nieuwe GRANT**
   (zie hieronder), zonder die GRANT geeft MDT een fatale fout zodra
   iemand een statusknop probeert te zien of te zetten).

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
GRANT SELECT ON mkapp.push_abonnementen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.melding_koppelingen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.team_leden TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.melding_toewijzingen TO 'mdt_user'@'%';
GRANT SELECT ON mkapp.eenheidsstatus_rollen TO 'mdt_user'@'%';

-- Schrijven (fase M2: logboek terugschrijven + eenheidsstatus doorgeven;
-- fase M5: pushabonnementen opslaan/verwijderen)
GRANT INSERT ON mkapp.melding_notities TO 'mdt_user'@'%';
GRANT UPDATE (huidige_eenheidsstatus_id) ON mkapp.gebruikers TO 'mdt_user'@'%';
GRANT INSERT, UPDATE, DELETE ON mkapp.push_abonnementen TO 'mdt_user'@'%';

FLUSH PRIVILEGES;
```

Had je dit account al vóór fase M5 aangemaakt? Dan is het genoeg om
alleen de 2 nieuwe regels hierboven (`push_abonnementen` lezen +
schrijven/verwijderen) opnieuw uit te voeren — de rest heb je al. Had
je het al vóór fase M3, dan geldt ook nog de regel uit die fase
(`crew`). Had je het al vóór fase M6, dan gelden ook nog de 2 regels
uit die fase (`mdt_gebruikers` en `rollen`). Had je het al vóór fase
M2, dan gelden ook nog de 4 regels uit die fase (`teams`,
`eenheidsstatussen`, het INSERT-recht en de kolom-update). **Had je
ooit de fase M4-regels (`melding_bijlagen` lezen + schrijven) al
gedraaid?** Die zijn niet meer nodig sinds de foto-functie is
verwijderd. **Let op: MariaDB verwijdert een tabel-privilege niet
automatisch als de tabel zelf verwijderd wordt** (getest: het blijft
gewoon in `mysql.tables_priv` staan) — draai daarom, ná de migratie op
MKAPP die de tabel `melding_bijlagen` verwijdert, ook zelf nog handmatig
(als root, `mdt_user` heeft zelf geen `GRANT OPTION`):

```sql
REVOKE SELECT, INSERT ON mkapp.melding_bijlagen FROM 'mdt_user'@'%';
FLUSH PRIVILEGES;
```

Dit mag ook vóór de migratie (een REVOKE op een tabel die nog bestaat
werkt gewoon), en is puur opruimwerk — zonder deze stap blijft het
account technisch een overbodig recht houden op een tabel die niet
meer bestaat, wat verder geen functioneel probleem geeft.

### Pushmeldingen bij toewijzing (fase M5)

MDT kan een echte browser-pushmelding sturen zodra een melding aan jou
(of je team) wordt toegewezen — zonder externe library (composer) en
zonder een omweg via een 3e-partij-dienst: de versleuteling (RFC 8291)
en het VAPID-JWT (RFC 8292) zijn met PHP's eigen `openssl`-extensie
geïmplementeerd, zie `includes/webpush.php`. Optioneel: staat er geen
VAPID-sleutelpaar ingesteld, dan blijft het pushmeldingen-paneel op
"Mijn meldingen" gewoon verborgen en werkt de rest van MDT onveranderd.

1. Genereer 1x een sleutelpaar:
   ```bash
   docker compose exec mdt php genereer_vapid_sleutels.php
   ```
   Zet de 2 geprinte regels (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`) als
   omgevingsvariabelen in `docker-compose.yml`, samen met `VAPID_SUBJECT`
   (een `mailto:`-adres, mag algemeen/fictief zijn) en een eigen, lang
   willekeurig `WEBHOOK_TOKEN`. Herstart MDT daarna.
2. Stel in MKAPP bij **Beheer > Connectiviteit** een webhook in:
   - URL: `https://<mdt-domein>/webhook_ontvangen.php?token=<jouw WEBHOOK_TOKEN>`
   - Events: `melding_toegewezen`
   - Platform: generiek
3. Elke gebruiker zet pushmeldingen zelf aan/uit via de schakelaar op
   "Mijn meldingen" (verschijnt alleen als de browser het ondersteunt
   én er een VAPID-sleutelpaar is ingesteld). Vereist HTTPS — een
   browser laat `pushManager.subscribe()` niet toe op gewoon `http://`
   (behalve `localhost` tijdens testen); zonder HTTPS blijft het paneel
   verborgen, de rest van MDT blijft gewoon werken op een lokaal
   netwerkadres.
4. Een verlopen/ingetrokken abonnement (bv. browserdata gewist) ruimt
   `webhook_ontvangen.php` vanzelf op zodra de pushdienst dat meldt
   (HTTP 404/410) — hier is verder geen beheer voor nodig.

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
├── config.php              DB-verbinding + VAPID/webhook-instellingen (via env-variabelen)
├── includes/
│   ├── db.php               get_pdo()
│   ├── functions.php        inloggen, meldingen/logboek lezen+schrijven, eenheidsstatus, pushabonnementen
│   ├── webpush.php           VAPID + payload-encryptie (RFC 8291/8292), geen externe library (fase M5)
│   ├── header.php / footer.php
├── assets/style.css
├── login.php
├── logout.php
├── index.php                 "Mijn meldingen" + eenheidsstatus-knoppen + pushmeldingen-schakelaar
├── melding.php?id=           melddetail + logboek (lezen + toevoegen)
├── status.php                 POST-only: eenheidsstatus zetten
├── crew.php                   Crew + collega's, gecombineerde bellijst (fase M3)
├── push_abonneren.php         POST-only (JSON): pushabonnement aan-/afmelden (fase M5)
├── webhook_ontvangen.php      Publiek, token-beveiligd: ontvangt MKAPP's webhook, stuurt de pushmelding (fase M5)
├── sw.js                      Service worker voor pushmeldingen (fase M5)
├── genereer_vapid_sleutels.php  CLI: eenmalig een VAPID-sleutelpaar genereren (fase M5)
├── Dockerfile
├── docker-compose.yml
└── CHANGELOG.md            versiegeschiedenis (los van de MKAPP-fasering M1-M5)
```
