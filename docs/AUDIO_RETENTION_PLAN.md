# Gewenst audiobeleid voor centrale Backyard-opslag

Status 2026-11-04: centrale Backyard-implementatie gepusht op
`codex/audio-retention`, commit `e676ff3`; nog NIET op de Pi geactiveerd.
Uitvoering/activering: [Backyard AUDIO_RETENTION.md](https://github.com/CPVB86/Backyard/blob/codex/audio-retention/docs/AUDIO_RETENTION.md).
Onderstaande tekst is de oorspronkelijke ontwerpbasis. De aparte Go-bronbuffer-
quota van 2 GiB/48 uur is niet geïmplementeerd; native Go-retentie blijft staan.
Het uitgewerkte werkvoorstel met API-impact, wijzigingsplekken, budgetvoorstellen,
uitrol en acceptatietests staat in [AUDIO_RETENTION_IMPLEMENTATION.md](AUDIO_RETENTION_IMPLEMENTATION.md).
De centrale backend staat in de afzonderlijke Backyard-repository. De WP-plugin
mag geen eigen audiobestanden verwijderen; zij volgt beschikbaarheid uit de API.

## Vastgelegd

- Waarnemingen, aantallen, tijdstippen, reviewbesluiten en soortcorrecties blijven
  bewaard wanneer hun audio verdwijnt.
- Per bevestigde soort één bruikbare opname, bij voorkeur de laatste. Een nieuwe
  opname moet geldig en veilig opgeslagen zijn vóór de vorige vervalt.
- Per nog te beoordelen soort één reviewopname, maximaal 14 dagen. Dit is
  expliciet door de gebruiker gekozen.
- Afgewezen audio direct uit de actieve selectie en naar trash.
- Een verlopen opname wordt als audio-verlopen getoond, nooit automatisch als
  afgewezen waarneming.

## Uitwerking die bij implementatie moet worden bewaakt

- Groepeer op domein en effectieve wetenschappelijke soortnaam, inclusief een
  eventuele menselijke soortcorrectie. Vogels/vleermuizen blijven gescheiden.
- Houd menselijke bevestiging en automatische acceptatie herkenbaar. Voorgesteld:
  een nieuwe automatische acceptatie vervangt geen handmatig bevestigd voorbeeld.
- Voorgestelde trash-grace: 24 uur; dit is nog geen geïmplementeerde instelling.
- Een gekozen soortvoorbeeld moet vindbaar zijn zonder te suggereren dat oudere
  waarnemingen hun eigen originele audio nog hebben.
- Een nieuwe soortcorrectie moet de voorbeeldselectie voor oude en nieuwe soort
  consistent bijwerken. De oorspronkelijke metadata en reviewhistorie blijven.
- Cleanup moet databaseverwijzingen/statussen bijwerken en veilig hervatbaar zijn.
  Geen losse rm-opdrachten op actieve audiomappen.
- Retrys/import mogen verlopen of vervangen audio niet opnieuw laten groeien,
  en mogen niet blijvend blokkeren op een bewust verwijderd bewijsbestand.
- Voer cleanup periodiek uit met begrensde batches en opslagrapportage. Kies
  expliciete omvang/vrije-ruimtegrenzen vóór activering; die zijn nog niet gekozen.
- Neem de aparte originele BirdNET-Go-clips mee in de opslagbegroting. Verwijder
  geen nog te importeren bronclip uitsluitend omdat Backyard één soortvoorbeeld
  heeft. Behoud 384000-Hz-bewijs voor bat-audio; geen lossy conversie invoeren.

## Bevinding bestaande code, alleen lokaal geïnspecteerd

`app/modules/observations/service.py` bewaart onbeoordeelde audio onbeperkt.
Een gewone afwijzing plant cleanup na policy.review_days (nu 14 dagen).
Versiecorrecties hebben andere behoudregels. De huidige cleanup verwijdert
alleen verlopen expliciete afwijzingen en stale promotiekopieën; permanente
audio, onbeoordeelde audio en legacy Birds-audio worden niet begrensd.
`operations/inventory.py` kan de database en audiogroepen read-only inventariseren.

## Vereiste verificatie

Test vervanging pas na succesvolle opslag, verlopen reviewaudio, trashcleanup,
gelijktijdige review/cleanup, soortcorrecties, behouden detectietotalen en
idempotente import zonder herupload van vervallen audio. Controleer daarna op
de Pi zowel bestandsvolume als API/WP-weergave en een nieuwe succesvolle import.
