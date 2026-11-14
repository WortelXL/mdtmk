# Changelog — MDT

## V0.0.22 (15-11-2026)

- **Verversknop**: een icoontje (cirkelpijl) in de tabbalk, direct links van
  het tandwiel. Een tik ververst meteen. Op Meldingen en een geopende melding
  gebeurt dat "zacht" (alleen de lijst/status wordt bijgewerkt, een half
  getypte notitie blijft staan en het geluidsalarm loopt door); op Crew en
  Instellingen wordt de pagina opnieuw geladen. Het icoontje draait tijdens het
  verversen.
- **Automatisch verversen instelbaar**: onder het tandwiel, nieuw blok
  "Verversen": aan/uit (standaard aan) en hoe vaak (15 s, 30 s = standaard,
  1 min, 2 min, 5 min). De keuze geldt per apparaat (localStorage, nieuw bestand
  `assets/mdt-verversen.js`). Het automatisch verversen blijft een ronde
  overslaan zolang je typt of het scherm niet zichtbaar is.
- Door MKAPP V2.0.2.49 wordt iemand bij een nieuwe toewijzing automatisch op
  status **MV (Melding Verstrekt)** gezet; die status verschijnt vanzelf bij de
  statusknoppen en wordt dankzij het automatisch verversen zonder handwerk
  zichtbaar. Voor MDT zelf is daar geen codewijziging voor nodig.
- Geen nieuwe databaserechten nodig.

## V0.0.21 (14-11-2026)

- Een eenheidsstatus kan iemand **losmaken van zijn meldingen**. In MKAPP staat
  daarvoor bij Beheer > Eenheidsstatussen per status het vinkje "Koppelt los
  van meldingen" (standaard aan voor "Op de post"). Kies je in MDT zo'n status,
  dan verdwijnt je **persoonlijke** toewijzing van al je actieve meldingen. Een
  toewijzing via een team blijft staan (je collega's houden die melding), en
  afgehandelde meldingen blijven ongemoeid.
- Op elke losgekoppelde melding komt de kladblokregel "Status: Op de post (OP) —
  losgekoppeld van deze melding"; op de overige actieve meldingen komt de
  gewone statusregel, zoals voorheen.
- Zat je net op een melding waarvan je bent losgekoppeld, dan ga je terug naar
  "Mijn meldingen" met een korte melding bovenaan ("Je bent losgekoppeld van
  MK-0214.").
- Ook de oude kolom `meldingen.toegewezen_aan_gebruiker_id` wordt bijgewerkt
  (laatst overgebleven persoonlijke toewijzing, of leeg), omdat het plotbord en
  het meldingscherm van MKAPP die nog lezen.
- Vereist **MKAPP V2.0.2.48** (kolom `eenheidsstatussen.ontkoppelt_melding`) en
  **2 extra databaserechten** voor `mdt_user` (zie README):
  `GRANT DELETE ON mkapp.melding_toewijzingen` en
  `GRANT UPDATE (toegewezen_aan_gebruiker_id) ON mkapp.meldingen`. Ontbreken die,
  dan wordt de status nog steeds gezet, blijft de melding aan je hangen en krijg
  je een foutmelding; er verandert dan niets aan de toewijzing.

## V0.0.20 (14-11-2026)

- De statusknoppen (OW/TP/IR/BS/PS/OP) staan nu op de **melding zelf**
  (`melding.php`, blok "Mijn status", dicht- en uitklapbaar zoals voorheen) in
  plaats van op het beginscherm. Na een tik kom je terug op dezelfde melding.
- Het beginscherm "Mijn meldingen" toont alleen nog een korte regel met je
  huidige status ("Jouw status: Ter plaatse (TP)").
- De status blijft **1 status per persoon** (zoals in MKAPP): een tik werkt
  hem bij en zet, zoals voorheen, een logboekregel "Status: …" op al je actieve
  meldingen. Er is dus geen database- of MKAPP-wijziging nodig.
- Let op: zonder actieve melding is er nu nergens een knop om je status te
  zetten (bijvoorbeeld "Beschikbaar" of "Op de post").
- De detailpagina van een melding ververst nu zacht (alleen kop, statusknoppen
  en kladblok) zodat een lopend kritiek alarm niet wordt onderbroken.
- Gedeelde markup staat in `includes/status_paneel.php`; `status.php` stuurt
  terug naar de melding waar je op tikte (alleen een getal, geen vrije redirect).

## V0.0.19 (13-11-2026)

- Nieuwe tab met een tandwiel (naast Meldingen en Crew): **Instellingen**.
  Het pushpaneel dat bovenaan "Mijn meldingen" stond is daarheen verhuisd.
- Pushmeldingen aan/uit per apparaat, met twee keuzes die per apparaat in
  de database worden bewaard: **Melding bij toewijzing** (standaard aan) en
  **Kritieke meldingen voor iedereen** (standaard uit). Met de laatste krijg je
  bij elke nieuwe melding met prioriteit kritiek een push met "🔴 KRITIEK" in de
  titel, ook als de melding niet aan jou is toegewezen. Een kritieke melding die
  aan jou wordt toegewezen krijgt dezelfde kop. Kritieke pushes worden met
  `Urgency: high` verstuurd (direct afleveren) en blijven op Android/desktop staan
  tot je ze wegtikt.
- Knoppen **Test** en **Test kritiek** sturen direct een testmelding naar het
  apparaat waarop je zit.
- **Geluid in de app** (per apparaat, localStorage): aan/uit, toon (Alarm,
  Piep, Bel) met afspeelknop, volume, en "Kritiek: herhalend alarm" dat blijft
  klinken tot je de melding opent of op *Stop alarm* tikt (maximaal 10 minuten).
  Bij een kritieke melding verschijnt onderin een rode balk. Het geluid werkt
  alleen zolang MDT open staat; een gesloten app gebruikt het geluid van de
  telefoon. Browsers laten geluid pas toe na een tik op de pagina.
- "Mijn meldingen" ververst nu alleen de lijst en de statusknoppen (i.p.v. de
  hele pagina) zodat een lopend alarm niet wordt onderbroken. Lukt dat niet,
  dan wordt zoals voorheen volledig herladen.
- `manifest.webmanifest`: `display` is nu `standalone`. iPhone/iPad sturen
  alleen pushmeldingen naar een webapp die op het beginscherm staat; zo'n app
  heeft geen adresbalk meer.
- Apple staat voor webapps geen "Kritieke meldingen" toe die de stilteknop of
  Niet storen doorbreken, en geen eigen pushgeluid; dat kan alleen met een
  echte App Store-app. Dit staat ook op de Instellingen-pagina.
- Vereist **MKAPP V2.0.2.47** (migratie `migratie_push_voorkeuren.sql`: kolommen
  `toewijzing` en `kritiek_alle` op `push_abonnementen`; geen nieuwe GRANT nodig).
  Zonder die migratie blijft MDT werken zoals voorheen en meldt de
  Instellingen-pagina dat de keuzes nog niet kunnen worden opgeslagen.
- Voor kritieke push voor iedereen moet in MKAPP bij Beheer > Connectiviteit
  bij de MDT-webhook ook het event `melding_aangemaakt` worden aangevinkt.

## V0.0.18 (10-11-2026)

- Nieuw MDT-logo toegepast, met de hele app in de kleuren van het logo
  (donkerblauw, magenta, geel, lichtblauw) in plaats van het eerdere
  donkere/amberkleurige thema. Het logo staat in de kop (i.p.v. het
  oranje bolletje) en groot op de inlogpagina.
- Logo ook als favicon, als icoon op het beginscherm van de telefoon
  (apple-touch-icon + `manifest.webmanifest`) en als icoon in
  pushmeldingen (`sw.js`).
- Prioriteit "hoog" heeft nu een gele rand; "kritiek" blijft rood zodat
  het niet te verwarren is met het merkmagenta. De actieve tab heeft een
  gele streep. Tekst in magenta gebruikt een lichtere tint
  (`--amber-text`) voor voldoende contrast op de donkere achtergrond.
- Hoogte van de kop staat nu vast (`--topbar-h`), zodat de plakkende
  tabbalk er altijd netjes onder blijft.
- Geen database- of GRANT-wijzigingen; geen MKAPP-aanpassing nodig.

## V0.0.17 (9-09-2026)

- MKAPP kan een eenheidsstatus sinds V2.0.2.28 aan meerdere rollen tegelijk
  koppelen (i.p.v. precies 1) — bijvoorbeeld zowel EHBO als
  EHBO-teamleider dezelfde status ("Ter plaatse") laten doorgeven. MDT
  herkent dit nu correct: je ziet de statusknoppen van elke rol die aan
  jou gekoppeld is, en het echt zetten van een status controleert
  hetzelfde server-side (niet alleen het verbergen van knoppen).
  Voorheen keek MDT alleen naar de oude, inmiddels niet meer bijgewerkte
  kolom `eenheidsstatussen.rol_id` (precies 1 rol) — dat leverde na de
  MKAPP-update een verkeerd (te beperkt) beeld op zodra een status aan
  een 2e rol gekoppeld werd. Vereist MKAPP V2.0.2.28 (gedeelde
  database, nieuwe tabel `eenheidsstatus_rollen`) **en een nieuwe GRANT
  op productie**: `mdt_user` moet ook lezen mogen op
  `mkapp.eenheidsstatus_rollen` (zie README.md) — zonder deze GRANT
  geeft elke pagina met statusknoppen een fatale fout.

## V0.0.16 (8-09-2026)

- MKAPP kan een melding sinds V2.0.2.24 aan meerdere crew-contacten/MDT-
  gebruikers/teams tegelijk toewijzen (i.p.v. hooguit 1). "Mijn
  meldingen" en de melding-pagina in MDT herkennen dit nu correct --
  een melding verschijnt zodra je er rechtstreeks aan toegewezen bent
  óf via een team waar je lid van bent, ook als de melding daarnaast
  nog aan anderen toegewezen is. Vereist MKAPP V2.0.2.24 (gedeelde
  database, nieuwe tabel `melding_toewijzingen`) **en een nieuwe GRANT
  op productie**: `mdt_user` moet ook lezen mogen op
  `mkapp.melding_toewijzingen` (zie README.md) -- zonder deze GRANT
  geeft de meldingenlijst een fatale fout.

## V0.0.15 (8-09-2026)

- Een team kan nu meerdere collega's als lid hebben (was: hooguit 1) --
  je ziet nu de status van elk teamlid apart, en iedereen in het team
  krijgt een pushmelding bij een nieuwe toewijzing. Je kunt ook zelf
  lid zijn van meerdere teams tegelijk. Vereist MKAPP V2.0.2.22
  (gedeelde database, nieuwe tabel `team_leden`) **en een nieuwe GRANT
  op productie**: `mdt_user` moet ook lezen mogen op
  `mkapp.team_leden` (zie README.md).

## V0.0.14 (8-09-2026)

- Het kladblok op de melding-pagina toont nu ook de regels van
  gekoppelde meldingen (ook indirect, via een keten van koppelingen) --
  elke regel heeft een klein 🔗 MK-xxxx-label als hij niet van de
  melding zelf komt, met een teller erboven. Zelfde aanpak als MKAPP
  V2.0.2.21. Vereist MKAPP V2.0.2.21 (gedeelde database, geen
  schemawijziging nodig in MDT zelf), **en vereist een nieuwe GRANT
  op productie**: `mdt_user` moet ook lezen mogen op
  `mkapp.melding_koppelingen` (zie README.md) -- zonder deze GRANT
  geeft de melding-pagina een fatale fout.

## V0.0.13 (8-09-2026)

- "Mijn status" op de meldingenlijst verschijnt nu alleen nog als er
  ook echt een rol aan je gekoppeld is (dus als je statusrechten hebt).
  Geen rol gekoppeld? Dan is het hele blok weg, in plaats van dat het
  een "vraag een beheerder"-melding toonde.

## V0.0.12 (8-09-2026)

- De Crew-pagina (bellijst) toont nu alleen nog wie op Beheer > Crew
  (in MKAPP) als "Zichtbaar in MDT" staat aangevinkt -- zo houdt de
  centralist deze lijst overzichtelijk zonder iemand te hoeven
  verwijderen of deactiveren. Staat standaard aan voor iedereen die er
  al stond, dus er verandert niets vanzelf. Vereist MKAPP V2.0.2.20.

## V0.0.11 (8-09-2026)

- "Mijn status" op de meldingenlijst is nu in-/uitklapbaar. Standaard
  ingeklapt: alleen compacte knopjes met de afkorting (bv. "TP"),
  scheelt ruimte op het scherm. Uitgeklapt (tik op de kop of het
  pijltje) zie je precies het vertrouwde grid met volledige namen erbij.
  Beide standen zijn gewoon de echte statusknoppen — schakelt direct je
  status, ongeacht welke stand je ziet. Zelfde in-/uitklaptechniek
  (checkbox + CSS, geen JavaScript) als elders in MKAPP.
- "Logboek" heet overal "Kladblok" (kopjes, knoppen, placeholder-tekst,
  lege-staat-tekst) -- puur een naamswijziging op het scherm, de
  onderliggende functienamen/databasekolommen blijven ongewijzigd.
  Vereist MKAPP V2.0.2.15 voor dezelfde naamswijziging daar.

## V0.0.10 (7-09-2026)

- Foto's uploaden/delen (fase M4) is op verzoek weer volledig
  verwijderd -- werd als niet-toegevoegde-waarde beoordeeld. Het "Foto
  toevoegen"-formulier en de foto-grid op de melddetailpagina zijn weg,
  net als de upload-/opslaglogica, de bijbehorende Docker-instellingen
  (uploadlimieten teruggezet naar de waarden van vóór fase M4, de
  aparte `/uploads`-map en het bijbehorende volume zijn vervallen) en
  `APP_BASE_URL` (die alleen voor de foto-URL bestond). MKAPP toont
  hierdoor ook geen "Foto's"-sectie meer op de melding-pagina; de
  gedeelde tabel `melding_bijlagen` vervalt (zie MKAPP V2.0.2.14).
  **Let op voor bestaande installaties**: `mdt_user`'s GRANT-rechten op
  `melding_bijlagen` verdwijnen niet vanzelf bij het verwijderen van de
  tabel -- zie README.md voor het REVOKE-commando.

## V0.0.9 (7-09-2026)

- Auto-refresh: "Mijn meldingen" en een melddetailscherm verversen
  voortaan vanzelf elke 30 seconden, zodat een wijziging van iemand
  anders (nieuwe toewijzing, logboekregel, statuswijziging, foto)
  zichtbaar wordt zonder dat je zelf op ververs hoeft te tikken. Pauzeert
  automatisch zodra je iets aan het invullen bent -- een logboekregel
  aan het typen bent, een tekstveld focus heeft, foto's hebt klaargezet
  die nog niet verstuurd zijn, of een formulier nog aan het versturen
  is -- of terwijl het tabblad niet zichtbaar is, zodat je nooit
  halverwege ingevulde invoer kwijtraakt. Het "Niet gevonden"-scherm van
  een melding ververst niet mee.

## V0.0.8 (7-09-2026)

- Pushmeldingen bij toewijzing: zet je op "Mijn meldingen" een schakelaar
  aan, dan krijg je voortaan een echte browser-melding op dat apparaat
  zodra een melding aan jou (of je team) wordt toegewezen -- ook als MDT
  niet open staat. Volledig zelf gebouwd (RFC 8291/8292: VAPID +
  end-to-end-versleuteling met alleen PHP's ingebouwde `openssl`), dus
  geen externe library en geen omweg via een 3e-partij-dienst. Vereist
  eenmalig een VAPID-sleutelpaar (`genereer_vapid_sleutels.php`) en een
  webhook in MKAPP naar `webhook_ontvangen.php` -- zie README.md.
  Werkt alleen via HTTPS; zonder HTTPS of op een verouderde browser
  blijft het schakelpaneel gewoon verborgen en werkt de rest van MDT
  onveranderd door. Vereist MKAPP V2.0.2.12.

## V0.0.7 (7-09-2026)

- Telefoonvriendelijker gemaakt op de bestaande schermen:
  - Foto's kiezen heeft nu een echte, grote kies-knop met miniatuur-
    voorbeelden van de gekozen foto's en een kruisje om er 1 weer weg
    te halen vóór het versturen -- in plaats van de kale, nauwelijks
    aan te tikken "Choose Files"-knop van de browser zelf. Je kunt ook
    meerdere keren achter elkaar kiezen (bv. na elke camera-opname),
    ze stapelen op.
  - Zijn de gekozen foto's samen te groot om te versturen, dan zie je
    nu een duidelijke foutmelding in plaats van dat er stilzwijgend
    niets gebeurt.
  - De upload-limiet in Docker is verhoogd (10MB -> 25MB per foto,
    100MB totaal per keer) -- een moderne telefooncamera maakt al snel
    grotere foto's dan de oude limiet toeliet.
  - Een submit-knop wordt na de eerste tik uitgeschakeld ("Bezig..."),
    zodat een 2e tik op een wisselende verbinding niet nog een keer
    hetzelfde formulier verstuurt.
  - Kleinere aanraakgebieden (zoals de Crew/Meldingen-tabbladen) zijn
    vergroot naar minimaal 44px.
  - Pinch-to-zoom staat weer aan (was uitgeschakeld via de
    viewport-instelling).

## V0.0.6 (5-09-2026)

- Foto's toevoegen bij een melding (fase M4) — vanaf de melddetailpagina,
  naast het logboek. Meerdere foto's per melding toegestaan (camera of
  galerij). Elke toevoeging komt ook als logboekregel te staan, en de
  foto's zelf zijn terug te zien op de melding, ook in MKAPP zelf.
  Vereist MKAPP V2.0.2.5.

## V0.0.5 (4-09-2026)

- Eenheidsstatussen horen voortaan bij een rol (bv. EHBO of Bouwploeg,
  te beheren op Beheer > Eenheidsstatussen in MKAPP) — je ziet in "Mijn
  status" alleen de statussen van je eigen gekoppelde rol (Beheer >
  MDT-gebruikers in MKAPP). Zonder gekoppelde rol zie je geen
  statusknoppen. Vereist MKAPP V2.0.2.4.

## V0.0.4 (4-09-2026)

- Nieuwe pagina "Crew" (nieuwe navigatietab bovenin) — een gecombineerde
  bellijst met de bestaande crew (contactpersonen zonder account) en
  collega's met een MDT-account, elk met een grote belknop (`tel:`).
  Telefoonnummers van accounts zijn per persoon in te stellen bij
  Beheer > MDT-gebruikers in MKAPP. Vereist MKAPP V2.0.2.3.

## V0.0.3 (4-09-2026)

- Inloggen bepaald door een losse MDT-gebruikerslijst in MKAPP
  (Beheer > MDT-gebruikers), niet meer door de oude "Mag inloggen op
  MDT"-schakelaar bij Beheer > Gebruikers. Vereist MKAPP V2.0.2.2.
- Per account in te stellen (door een beheerder, in MKAPP): het
  statusoverzicht (eenheidsstatus-knoppen) tonen of niet.
- Per account een schakelaar "Toegewezen" / "Alle meldingen" — kan
  ook een melding zien die niet aan jou of je team is toegewezen,
  optioneel beperkt tot 1 classificatie (bv. alleen Medisch). Werkt
  dan ook voor logboek/status op die meldingen, niet alleen bekijken.
- Per account een alleen-lezen-stand: geen logboekregel meer kunnen
  toevoegen. Staat los van het statusoverzicht.
- Logboekregels vanuit MDT zijn in MKAPP nu herkenbaar aan een klein
  "MDT"-label.

## V0.0.2 (4-09-2026)

- Logboekregel toevoegen vanaf een melding (vrije tekst).
- Eenheidsstatus doorgeven met 1 tik (OW · TP · IR · BS · PS · OP) —
  komt automatisch als logboekregel op elke actieve toegewezen melding
  te staan.
- "Mijn meldingen" telt nu ook meldingen mee die aan je team zijn
  toegewezen (naast rechtstreekse individuele toewijzing).
- Vereist MKAPP V2.0.2.1 (Teams + eenheidsstatussen in de gedeelde
  database).

## V0.0.1 (4-09-2026)

- Inloggen met een bestaand MKAPP-account (vereist `mag_inloggen_mdt`,
  zie MKAPP V2.0.2.0).
- "Mijn meldingen" — lijst van je eigen, actief toegewezen meldingen.
- Melddetail met het bestaande logboek — alleen-lezen.
