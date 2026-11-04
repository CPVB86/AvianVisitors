# Implementatievoorstel audiobewaring

Status 4 november 2026: gereed als werkopdracht; nog NIET geïmplementeerd.
De codewijzigingen horen in de afzonderlijke Backyard-backendrepository.
WordPress en Avian volgen de backend; zij verwijderen zelf geen audiobestanden.

## Gedrag

Waarnemingen, aantallen, modeluitkomsten, tijdstippen, reviewbesluiten en
soortcorrecties blijven bewaard. Audio krijgt een eigen levenscyclus.
Bevestiging van één waarneming bevestigt nooit alle events van dezelfde soort.

| Groep | Actieve audio | Bewaarregel |
|---|---|---|
| Bevestigd: auto_accepted / human_confirmed | Eén soortvoorbeeld | Nieuwste geldige opname binnen dezelfde prioriteit; menselijke bevestiging vóór automatische acceptatie |
| Te beoordelen: pending_review / review_recommended | Eén reviewvoorbeeld per soort | Nieuwste geldige opname; maximaal 14 dagen vanaf de opnametijd |
| Afgewezen: human_rejected | Geen actieve audio | Meteen logische trash; voorstel maximaal 24 uur herstelruimte |
| Vervangen voorbeeld | Geen blijvende extra kopie | Opruimen na veilige publicatie vervanger; niet telkens 24 uur in trash stapelen |
| Verlopen reviewaudio | Geen | Event blijft onbeoordeeld; expliciete reden audio-expired |

De gebruiker heeft één reviewopname per soort en 14 dagen expliciet gekozen.
Trashduur en onderstaande opslagbudgetten zijn concrete voorstellen.

Selectie gebeurt op domein + effectieve wetenschappelijke soortnaam, inclusief
menselijke soortcorrecties. Birds en Bats blijven gescheiden. Maximaal één
bevestigd EN één reviewvoorbeeld per soort is mogelijk. Een vertraagde import
mag een nieuwere opname niet verdringen: vergelijk observation-starttijd,
niet importtijd. Bij gelijke tijden een stabiele tie-breaker gebruiken.

"Bruikbaar" vereist geldig WAV, juiste samplefrequentie, volledige bytes, goede
hash en kloppende event/clip-timing. Geen lossy conversie van ultrasone audio.
De nieuwe opname moet veilig staan voordat de vorige vervalt. Een menselijke
bevestiging mag een ouder maar geldig voorbeeld boven een automatisch voorbeeld
plaatsen. Correctie naar een andere soort herberekent beide betrokken selecties.

## Grenzen en opruiming

Voorgestelde beginwaarden: 3 GiB voor alle Backyard-audio samen, inclusief
review, trash en tijdelijke publicatiekopieën; daarbinnen trash maximaal
256 MiB of 24 uur, wat het eerst bereikt wordt. Cleanup elke vijf minuten,
plus directe afhandeling bij vervanging/afwijzing. Een trash-map maakt op
zichzelf geen ruimte vrij. Deze getallen zijn nog geen actieve configuratie.

Ruimtewaarschuwing onder 10 GiB vrij; onder 8 GiB geen extra audiovoorraad
opbouwen. Ruim eerst trash, vervangen en verlopen audio op. Past een nieuwe
opname niet binnen het budget, behoud het bestaande geldige voorbeeld en sla
de nieuwe waarneming op met expliciete reden storage_pressure. De bridge moet
de bewuste audioweigering kunnen afronden zonder eeuwige herupload/retry.
Reserveer ruimte vóór upload/publicatie en tel gelijktijdige uploads mee.
Verwijder onder opslagdruk nooit stil catalogusdata of menselijke reviewbesluiten.

## Eerlijk API-contract en WP-weergave

- audio_available en audio_url blijven over de EIGEN originele opname gaan.
  Verwijderde eigen audio betekent false en null, met een expliciete reden:
  superseded, expired, rejected, storage_pressure of missing.
- Behoud clipmetadata, oorspronkelijke hash en verwijdertijd. Bied het huidige
  soortvoorbeeld apart aan, met observation_id en URL. Doe niet alsof een oudere
  waarneming opgenomen is met het bewijs van een ander event.
- De actieve reviewlijst toont de eigenaar van het reviewvoorbeeld; oudere
  events blijven in historie. Geen automatische bevestiging/afwijzing door cleanup.
- WP toont beschikbaar, vervangen, verlopen of verwijderd; geen kapotte player.
  Een event zonder eigen bewijs mag niet ongemerkt worden bevestigd met de audio
  van een ander event. Geef een duidelijke beperking bij review/correcties.
- Een expliciete, geversioneerde correctie kan audio binnen trash-grace herstellen;
  na definitieve verwijdering wordt herstel niet beloofd. Reviewhistorie blijft.

## Backendbestanden en verantwoordelijkheden

Deze namen zijn gecontroleerd in de lokale Backyard-broncode:

| Bestand / onderdeel | Benodigde wijziging |
|---|---|
| app/modules/observations/service.py | Voorbeeldselectie, veilige vervanging, afwijzing, verloop en geversioneerde correcties |
| app/modules/observations/effective.py | Bestaande effectieve soortprojectie hergebruiken |
| app/modules/observations/schemas.py | Audio-status/reden en afzonderlijke soortvoorbeeldverwijzing |
| app/modules/observations/models.py + app/core/migrations.py | Alleen indien nodig retentiemetadata/indexen toevoegen; geen reset |
| app/modules/birds/storage.py | Bestaande pad-, WAV- en hashbeveiliging behouden |
| app/core/cleanup_evidence.py | Dry-run, batches, herstartbare cleanup en rapportage uitbreiden |
| app/modules/birds/service.py + models.py | Legacy-audio meenemen; geen parallel onbeperkt archief |
| detector/bat_bridge.py + bird_go_bridge.py | Bewuste retentie onderscheiden van tijdelijke fouten, zonder heruploadgroei |
| app/core/config.py + .env.example | Termijnen, budgetten en activeringsoptie voor opslagbeleid |
| operations/inventory.py + operations.status | Bytes per groep, vrije ruimte, aantallen en laatste cleanup tonen |
| deploy/ | Eén zelfstandige cleanup-service/timer; bestaande API/modelservices behouden |

Voorkeur: bestaande tabellen en evidence_kind-waarden hergebruiken waar ze passen.
Opslagbeleid blijft gescheiden van classificatiebeleid: geen onbedoelde wijziging
van detectiedrempels of policy-fingerprints. De huidige gewone reject bewaart
14 dagen; geversioneerde correcties en onbeoordeelde/permanente/legacy-audio
hebben andere behoudpaden. Pas die samen aan, inclusief bestaande correctietests.

## BirdNET-Go-bronbuffer en blijvend ongeldige clips

Bronclips zijn geen tweede permanent archief. Voorstel: apart budget 2 GiB,
normaal eerst aantoonbaar geïmporteerde/discarded events opruimen. Niet-geïmporteerde
clips krijgen tijdelijk herstelruimte, maximaal 48 uur BINNEN dat budget.
Controleer de mogelijkheden van release 20261014 vóór implementatie; de bestaande
maxusage-drempel begrenst alleen bestandssysteemgebruik en is niet voldoende.

Een verloren bronclip of structurele timingfout mag niet de volledige import
blokkeren. Na begrensde wachttijd expliciet quarantainen met origineel event-ID,
tijden en reden, en volgende events verwerken. Geen succesclaim voor dat event,
geen verzonnen WAV-duur/samples. Tijdelijke netwerk/exportfouten blijven retrybaar.
Quota-gedwongen verlies moet zichtbaar worden gemeld; geen stille verwijdering
op basis van slechts een soortnaam of vermoedelijke bestandsnaam.

## Uitrol en acceptatie

1. Maak een actuele inventaris en consistente DB-back-up. Geen nieuwe schone-lei-reset.
2. Implementeer in de Backyard-repository; behoud bestaande lokale wijzigingen.
3. Dry-run rapporteert behouden/vervangen/verlopen/trash plus aantallen en bytes.
4. Activeer nieuw upload-/reviewgedrag en zet bestaande audio via hervatbare
   batches onder hetzelfde beleid. Waarnemingen/catalogus blijven gelijk.
5. Activeer de timer na tests en een gecontroleerde eerste uitvoering.
6. Verifieer op de Pi nieuwe bird/bat-import, afspeelbaarheid, API/WP-statussen,
   cleanup en werkelijk schijfgebruik. Avian-aantallen mogen niet veranderen.

Tests moeten afdekken: selectie/prioriteit en soortcorrecties; volle schijf en
mislukte upload zonder verlies van huidig bewijs; gelijktijdige uploads/reviews;
14-daags verloop zonder statuswijziging; trash-herstel en definitieve cleanup;
idempotente ingest zonder herleven van oude audio; crashherstel; read-only dry-run;
en voortgaande import na een permanent ongeldige bronclip. Test ook beide
bestaande reviewpaden en legacy Birds-audio.

## Resterende eenmalige opruiming

Al uitgevoerd door gebruiker: oude losse bats-container/map en ongebruikte
Docker-images verwijderd; grote logarchieven opgeruimd. Laatste meting: 53 GB
schijf, 15 GB gebruikt, 36 GB vrij, 29%. Logmap 403 MB; drie centrale services active.
Loglimieten zijn ter uitvoering gegeven: 10 MB, drie archieven, zeven dagen,
gzip. Effectieve configuratie moet nog worden uitgelezen om activering te bewijzen.

Nog te beoordelen: .detector-test, circa 578 MB. Documentatie gebruikt die als
testuitvoermap; procesgebruik en actuele inhoud zijn nog niet bevestigd.
Oude reset-/migratieback-ups kunnen later gecontroleerd worden uitgedund.
Actieve .venv/.venv-detector, modellen, generatorassets, Git-historie en MyRender
blijven staan. Met 36 GB vrij heeft nieuwe groei begrenzen meer waarde dan
agressief verder wissen.
