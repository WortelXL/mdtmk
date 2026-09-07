# Changelog — MDT

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
