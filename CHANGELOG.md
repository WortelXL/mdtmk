# Changelog — MDT

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
