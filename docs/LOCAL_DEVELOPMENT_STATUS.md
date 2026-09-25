# Local development status

## Live BirdNET en continuous recording (2026-09-25)

De nieuwste mijlpaal bouwt voort op de door de gebruiker toegevoegde
BirdNETDetectionSource. APP_MODE=birdnet en BIRDNET_JSON_PATH zijn behouden.
`demo/live_birdnet.py` en de geïnstalleerde kopie in C:\BirdNET gebruiken nu een
continue audiostream, producerthread en begrensde queue van 3 blokken.
De WAV-predict-route, confidence 0.60 en persistent JSON-contract zijn behouden.
Echte microfoon/ONNX-interleaving en Ctrl+C zijn getest. De API met bestaande live
JSON werkt. Zie [volledige workflow, bewijs en beperkingen](LIVE_BIRDNET.md).
Eerdere vermeldingen hieronder dat alleen demo ondersteund wordt zijn historisch.


## Desktopstabilisatie (2026-09-25)

De lokale desktoproute is `python demo/server.py`, Python 3.10+, standaardbibliotheek.
`DemoDetectionSource` vormt een kleine grens tussen de HTTP-laag en gesimuleerde
JSON-detecties. De bestaande frontend en productie/Pi/e-inkroutes blijven behouden.
Alle desktopinstellingen en stappen staan bovenaan README onder Desktop / Local development.

Gewijzigd: `demo/server.py` (datasource, configuratie, logging, timeouts, herstel,
favicon), `demo/config.py` en `.env.example` (demo/poort/fixture),
`demo/generate.py` (cache zonder sleutel, veilige configuratiefouten, logging),
`avian/scripts/openai_images.py` (ongeldige API-JSON), frontend `index.html`
(cacheversies), gerichte tests en documentatie. Geen nieuwe runtimeframeworks.

Werkelijk uitgevoerd:

- Schone HEAD-export plus de wijzigingen van deze ronde, zonder lokale cache of
  sleutel, met gekopieerde `.env.example`. Start met `python -S` vanuit een andere
  werkmap: HTML, alle gekoppelde scripts/styles/favicon, tabellen en beeld/API-routes
  bereikbaar; 42 detecties / 7 soorten. De eerste controle vond een ontbrekende
  favicon-route; na herstel is dezelfde controle geslaagd.
- Geïsoleerde Python-venv aangemaakt; pytest en Pillow via pip geïnstalleerd.
  De gewone desktopstart heeft deze pakketten niet nodig.
- 23 gerichte tests geslaagd, plus 16 subtests: demo, OpenAI-mocks, Atlas classic,
  Atlas always-all en frontend-cacheversies. Postzegel-JavaScript uit de bestaande
  shelltest direct met Node uitgevoerd en geslaagd.
- Volledige pytest-suite geprobeerd: eerste run 83 passed, 7 skipped, 29 failed,
  7 errors (88 subtests passed). Twee fouten waren verouderde cacheversiecontroles:
  opgelost en afzonderlijk opnieuw geslaagd. Resterende blokkades betreffen
  Windows/Linux-shelluitvoering, ontbrekende PHP/audio/frame-dependencies en
  een Linux-fontaanname. Geen claim dat de volledige productiesuite groen is.
  Hardware-/root-/live-shellsmokes zijn niet op deze Windows-host uitgevoerd.
- Cache-smoke met beide opgeslagen Merel-poses, zonder sleutel en met API-functie
  vervangen door een harde fout bij aanroep: beide overgeslagen, maskers vernieuwd,
  geen API-aanroep. Deze stabilisatieronde heeft geen beelden gegenereerd.
- Browser herladen en Atlas/Collage bekeken op 1366x768 en 1920x1080; geen horizontale
  overflow, geen mislukte niet-lege afbeeldingsbronnen en geen console-errors/warnings.
  Nederlandse namen en gesimuleerde Merel/detecties zichtbaar. Stats-data geladen.
- Server gestopt/herstart. De huidige lokale sessie gebruikt expliciet
  `--fixture .avian/species.json` (8 soorten / 56 detecties); een schone checkout
  gebruikt standaard de meegeleverde fixture (7 soorten / 42 detecties).

Beperkingen / inspectie:

- Alleen Windows is daadwerkelijk uitgevoerd. Padresolutie, standaardbibliotheek
  en optionele `webbrowser`-opening zijn cross-platform ontworpen; macOS/Linux en
  automatisch openen zijn alleen via code-inspectie beoordeeld.
- Geen desktop-exe, service of database; terminal blijft open. Ontbrekende lokale
  illustratie/maskerparen vallen terug of slaan de betreffende soort over.
  PNG-signatuur en maskerstructuur worden gecontroleerd; dit is geen volledige
  beeldintegriteits- of anatomiecontrole. Genereerresultaten blijven visuele review nodig hebben.
- Voor BirdNET-Pi later: adres/authenticatie en leveringsformaat bepalen, een tweede
  datasource met dezelfde publieke JSON-contracten toevoegen, netwerkfouten en
  echte detecties testen. Nu is alleen `APP_MODE=demo` toegestaan.
- Gegenereerde bestanden en `.env` blijven buiten Git. Back-up/overzetten van
  `.avian/illustrations` en `.avian/frontend` gebeurt afzonderlijk.


## Update: Nederlandse interface en OpenAI (2026-09-25)

De gebruiker heeft de OpenAI-migratie nu expliciet gevraagd en de echte
BirdNET-koppeling voorlopig uitgesteld. Detecties blijven gesimuleerd.

- De publieke interface heet Vogel Bezoeken en gebruikt Nederlandse teksten,
  familienamen, datums en enkelvoud/meervoud. Interne API-paden en ontwerp-
  groepssleutels blijven gelijk. Collage/Stats/Atlas en life list zijn behouden.
- `demo/generate.py` en `avian/scripts/openai_images.py` verzorgen expliciete
  OpenAI-aanvragen, transparante lokale PNGs en silhouetmaskers. De demoserver
  combineert lokale `.avian/`-afbeeldingen met de meegeleverde bibliotheek.
- `demo/config.py` leest de lokale `.env` zonder shell-evaluatie of sleutel-
  uitvoer. `.env.example` en `demo/requirements-images.txt` beschrijven setup.
- De negen nieuwe OpenAI-tests en zeven demotests slagen. De bestaande
  postzegelregressietest slaagt met Nederlandse weergaveteksten; interne
  CSS-familiesleutels behouden hun oorspronkelijke waarden.
- Browsercontrole bevestigt Recent gehoord, Nederlandse statistieken,
  Nederlandse families en '1 soort'. De echte OpenAI-proef is geslaagd: één
  Merel (medium, zittende pose) is gegenereerd en de lokale maskers zijn gebouwd.
  Het beeld heeft alfa-transparantie, maar ook een zachte gloed rondom de vogel;
  dit blijft een proefbeeld. De lokale demo gebruikt nu `.avian/species.json`
  met de Merel toegevoegd (14 gesimuleerde detecties). Beeld en sleutel blijven lokaal.
- Het bestaande `pregen.py` leest prompt en soortnotities nu expliciet als
  UTF-8, zodat dezelfde bronnen op Windows werken. Gemini-aanroepen zijn niet
  veranderd. Nederlandse soortnamen volgen de aangeleverde detecties; deze
  wijziging vertaalt niet automatisch iedere soortnaam uit een externe bron.

Start/test/uitvoer: zie [OpenAI-instructies](OPENAI_IMAGES.md).
De eerdere mijlpaal hieronder beschrijft de toestand vóór deze update.

## Current implementation

The local proof-of-concept uses the existing browser frontend and bundled art.
Start from the repository root with Python 3.10+:

```bash
python demo/server.py
```

Open http://127.0.0.1:8000/. No package installation or environment variables
are needed. See README's **Local development / demo mode** for the complete
clean-checkout setup and fixture customization.

## Architecture discovered by code inspection

- The root scripts are the BirdNET-Pi recording/analysis stack. Detection
  reporting in `scripts/utils/reporting.py` inserts into SQLite; the default
  path in `scripts/utils/helpers.py` is `scripts/birds.db`.
- Caddy/PHP-FPM serves `avian/api/birdnet-api.php`, a read-only JSON facade
  over detections with scientific/common names, timestamps, confidence and
  recording references. Educator scoping and admin authentication are shared
  PHP helpers. The installer exposes the overlay at the station web root.
- `avian/frontend/index.html`, CSS and `apt.js` implement Collage, Stats and
  Atlas. The browser fetches recent counts, lifelist, history and aggregates.
  The collage sizes birds by count and packs silhouettes using `dims.json`
  and `masks.json`. `stamps.js` and the stamp batches render Atlas designs.
- `avian/api/cutout.php` resolves scientific-name slugs to bundled PNGs,
  optional photo cutouts, then cached/dynamic Wikipedia + rembg fallbacks.
  Poses use `<slug>.png` and `<slug>-2.png`. Images and masks already ship.
- `frame/shoot.py` uses Playwright to screenshot the same frontend. Its
  BirdWeather mode already substitutes a recent-species list, but suppresses
  other views and requires a browser package. The demo follows the same
  frontend contract without importing that screenshot pipeline.
- `frame/display.py` sends rendered pixels to the Inky panel. Frame setup
  configures SPI/I2C and systemd; BirdNET setup handles audio/model services.
  None of those modules is imported by the demo.
- Production dependencies include TensorFlow/audio analysis and scientific
  Python from root `requirements.txt`, Caddy/PHP/SQLite, plus optional
  Playwright for screenshots and Pillow/Inky for the panel. The illustration
  pipeline has its own Pillow/rembg/ONNX dependencies. Demo: Python standard
  library and the user's browser only.

## Files changed and why

| File | Purpose |
| --- | --- |
| `demo/server.py` | Separate loopback-only, read-only HTTP adapter; generates synthetic timestamps and returns the public JSON shapes consumed by the unchanged frontend. Serves only frontend assets and selected bundled images. |
| `demo/species.json` | Editable seven-species fixture, 42 calls. Uses species with complete bundled art rather than mislabelling substitutes as the brief's example species. |
| `tests/test_demo.py` | Seven dependency-free tests for counts, time filtering, midnight/empty history, fixture changes, HTTP endpoints, images/HEAD, input validation and private-path/write rejection. |
| `README.md` | Exact setup, startup, URL, limitations and test commands. |
| `docs/LOCAL_DEVELOPMENT_STATUS.md` | Architecture, verification and continuation record. |

No production PHP, frontend, installer, hardware or image-generation files
were modified. No new packages or secrets were introduced.

## What is simulated

The fixture is expanded to individual detections in memory. The most recent
Huismus calls fall in the last hour; the other species fall earlier in the
last 24 hours. API aggregations supply the real frontend, including count
dependent collage size and time-window filtering. Timestamps move with local
time and are not persisted. The previous-week average is empty because this
fixture contains no detections that old. Before sufficient time has passed
since midnight, some calls correctly belong to yesterday.

The wiki endpoint returns demo text. Recordings, spectrograms, generation,
station mutations, backups and educator administration are unavailable;
they are not simulated as successful operations. The demo is not a complete
replacement for the production API and must remain a separate entry point.

## Verification

Verified by execution on Windows, 2026-09-25:

- Python server starts and serves http://127.0.0.1:8000/ without external
  packages, API keys, PHP or hardware.
- Browser screenshot and accessibility inspection show seven real bird
  illustrations and Dutch labels. The API and UI show the specified counts
  14/9/7/5/4/2/1 (42 total).
- Selecting 1H changes the actual collage to one Huismus with two calls;
  selecting 24H restores seven birds. Stats renders totals and charts;
  Atlas renders the seven illustrated stamps. Browser console inspection
  reported no warnings/errors during these checks.
- All seven new tests pass, including all initial frontend API requests and
  both image poses for all seven species.
- Existing `test_birdweather_architecture.py`: nine tests pass, one PHP
  syntax check skips because PHP is unavailable.
- A fresh local Git clone of implementation commit `e8c3ed4` passes all seven
  demo tests with Python 3.12.14 and `-S` (site-packages disabled). The same
  checkout starts via `python -S demo/server.py --port 8001`, verifying that
  the startup needs no untracked configuration or installed dependencies.

Not verified on this computer: physical Raspberry Pi/audio/e-ink deployment,
real BirdNET database integration, Gemini calls or OpenAI image generation.
Production compatibility is supported by unchanged production code and
targeted regression checks, not by a hardware test.

The broader pytest invocation could not run because pytest is absent from
the bundled Python runtime. The demo's unittest suite requires no pytest.

## Connecting real BirdNET data

Use the existing Raspberry Pi deployment instructions in README to run
recording/inference and populate `scripts/birds.db`; the existing PHP API
then supplies the collage. The schema includes `Date`, `Time`, `Sci_Name`,
`Com_Name`, `Confidence`, `File_Name` and the current detection identifiers;
educator helpers also depend on schema/version state. Keep real recordings
and database files out of Git.

For a future desktop connection, add an explicit separate read-only adapter
for an exported BirdNET database or the station's authenticated API, preserving
the existing JSON/time-window contract. The demo currently does not read an
arbitrary production DB or proxy to a station. Real audio playback additionally
needs the production recording/spectrogram endpoints and their access checks.

## Gemini pipeline and future OpenAI adapter

These are findings from the source code, not verified remote API calls:

- `avian/scripts/pregen.py:gen_one` sends a JSON POST to Google's
  `v1beta/models/gemini-2.5-flash-image:generateContent`. Authentication is
  `x-goog-api-key`; the secret comes from `GEMINI_API_KEY` or a CLI argument.
  It requests TEXT and IMAGE response modalities, extracts base64 image data
  from candidate parts and returns image bytes to the caller.
- Input is `prompt.template.md` with scientific/common name and pose, plus
  species notes. Optional inline image inputs are a target-species Wikipedia
  photo (`assets/references/<slug>.*`, downscaled when Pillow is available),
  a lookalike anti-reference (`_anti_<key>.jpg`) and an Edo print selected from
  `assets/references/styles/` by the `STYLE_REFS` mapping. Missing references
  are omitted. Do not assume the style references are bundled or licensed for
  new distribution.
- Output is a cream-ground illustration saved as `<slug>.png` or
  `<slug>-2.png` under `avian/assets/illustrations/`. `cutout.py` removes the
  ground using rembg/BiRefNet (or a selected model), then `build_masks.py`
  produces the dimensions and packed alpha masks consumed by the collage.
- `generate_one.py` calls `pregen.gen_one` for on-demand poses, applies an
  immediate chroma cutout and merges masks. `avian/api/generate.php` launches
  that job with the configured Gemini key in its process environment.
  `frame/generate_illustrations.py` invokes the generation pipeline for its
  region/station species. These call sites need provider-neutral configuration
  when a new provider is introduced.
- `verify.py` independently calls
  `gemini-2.5-flash:generateContent` with artwork and an anatomy/species prompt,
  expecting JSON fields used in its CSV report. This is a separate vision
  adapter from image generation.

Migration seam: keep the current `gen_one` input/output contract and introduce
a provider adapter that accepts the rendered prompt plus labelled reference
images and returns normalized PNG bytes. Configure the future OpenAI secret
via `OPENAI_API_KEY`, add `.env.example` then, and update the generation callers
and admin provider configuration. Select and validate the current supported
image model/request format against official documentation when implementing;
do not assume Google's request/response payloads transfer directly. Preserve
the cutout/mask pipeline and filenames, validate both poses and transparency,
and update frontend art/table cache revisions after regeneration. Migrate
`verify.py` separately if Gemini is to be removed entirely.

No generation migration is needed to run this demo, and none was performed.

## Known issues / next logical work

- The root README and illustration README quote different historical library
  counts. Use the actual files and masks when selecting demo species.
- `verify.py` documentation claims a blind species check, but its current
  prompt includes the target name. Resolve that discrepancy during a future
  verifier migration.
- Some existing Atlas family labels are inaccurate for this European sample
  (for example Huismus appears under Passerellidae). This is existing frontend
  taxonomy behavior; scientific names and image matching remain correct.
- The demo duplicates a subset of the public PHP API contract. Keep its tests
  in step with future frontend changes; do not expand it into station admin.
- Next optional milestone: real BirdNET read-only data integration, then
  dedicated illustrations for the original Dutch example species.
