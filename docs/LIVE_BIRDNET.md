# Continu luisteren met lokale BirdNET

De recorder is een zelfstandig proces. De grens blijft:
microfoon → BirdNET 3.0 ONNX → atomische JSON → BirdNETDetectionSource → frontend.
Er zijn geen OpenAI-aanvragen in deze route.

## Starten

De versie in Git staat in `demo/live_birdnet.py`. De werkende Windows-installatie
staat ook op `C:\BirdNET\live_birdnet.py`; de oude versie is bewaard als
`C:\BirdNET\live_birdnet.before-continuous.py`. Start niet twee recorders die
hetzelfde JSON-bestand schrijven.

Gebruik een Python-omgeving met BirdNET en sounddevice. Daadwerkelijk getest:
Windows, Python 3.14, birdnet 1.1.1, sounddevice 0.5.6. Installatie op een nieuwe
machine (model kan bij eerste gebruik worden gedownload):

```powershell
python -m pip install birdnet==1.1.1 sounddevice==0.5.6
python demo/live_birdnet.py --output .avian/live-detections.json
```

Of behoud de bestaande zelfstandige installatie:

```powershell
cd C:\BirdNET
python live_birdnet.py
```

Stel voor de webapp in `.env` in:

```env
APP_MODE=birdnet
BIRDNET_JSON_PATH=C:\BirdNET\live-detections.json
APP_PORT=8000
```

Bij de repository-route gebruik je `BIRDNET_JSON_PATH=.avian/live-detections.json`.
Start in een tweede terminal, vanuit de repository: `python demo/server.py`.
Open http://127.0.0.1:8000/. Terug naar simulatie: `APP_MODE=demo` en herstart.
De recorder leest zijn instellingen bovenin het script, niet uit de webapp-.env.
48 kHz, mono PCM16, blokken van 6 seconden, confidence **0.60** zijn behouden.

## Opname en analyse

Eén doorlopende `sounddevice.RawInputStream` blijft open. Een producerthread
leest achtereenvolgende PCM-blokken. De hoofdthread verwerkt de queue met dezelfde
`model.predict(WAV)` en CSV-parser als de eerdere recorder. Het model wordt één
keer geladen. Tijdelijke WAV/CSV-mappen verdwijnen ook bij een analysefout.

Queue: maximaal **3 wachtende blokken**, ongeveer 1.7 MB PCM, naast opname/actieve
analyse. Bij overflow wordt het oudste wachtende blok verwijderd met een warning
inclusief bloknummer. Het actuele analyseblok wordt nooit vervangen. Analyse kan
structureel langzamer zijn dan opname: dit voorkomt opnamepauzes en onbegrensd
geheugen, maar betekent expliciet verlies van analyseblokken. PortAudio-overflows
worden ook gemeld; hardware/CPU-overbelasting kan nog samples verliezen.

Eventtijd volgt het begin van het opgenomen blok, niet het latere moment van
analyse. `species[].count` blijft persistent; alleen `detections` wordt op de
laatste 500 begrensd. Tijdelijke JSON wordt atomisch vervangen. Onleesbare bestaande
historie wordt niet stilzwijgend gewist. De webapp bewaart bij tijdelijk ongeldig
of ontbrekend JSON het laatste geldige snapshot en herstelt na een geldige update.

Ctrl+C: geen nieuwe blokken; een lopende opname wordt binnen het huidige blok
geannuleerd, actieve analyse mag afronden, wachtende blokken worden gelogd en
weggegooid. BirdNET-spawnworkers negeren Ctrl+C. Een vastgelopen native analyse
heeft nog geen harde afbreekdeadline; afsluiten kan daarop wachten.

## Verificatie op 25 september 2026

- Echte microfoon + ONNX, `--blocks 3`, aparte testuitvoer: blok 1 analyse vanaf
  15:04:52; blok 2 opgenomen 15:04:58; blok 3 opgenomen 15:05:04. Alle drie
  geanalyseerd; nul drops/fouten. Analyse duurde circa 12–17 seconden per blok.
- Geen detecties boven 0.60 in deze korte proef; nieuw herkenningssucces is dus
  niet geclaimd. Bestaande live JSON afzonderlijk via API getest: vier soorten,
  demo=false, bestaande soortillustratie HTTP 200.
- Echte Ctrl+C tijdens analyse: stop 15:07:07, opname geannuleerd 15:07:10,
  analyse/afsluiting 15:07:11; één wachtend blok expliciet overgeslagen, geen
  worker-tracebacks. De terminalwrapper rapporteerde interrupt-exitstatus 1.
- Tests: gelijktijdige productie bij geblokkeerde consumer, begrensde queue en
  drops, foutcleanup, 502 persistente tellingen met 500 events, tijdstempels,
  config, beide servermodi, ontbrekend/leeg/ongeldig JSON, herstel, onbekende
  soort en ontbrekende illustratie. Geen betaalde API-aanroepen.

```powershell
python -m unittest tests.test_live_birdnet tests.test_demo tests.test_openai_images -v
```

Nog niet gedaan: SQLite/full-history, Nederlandse soortcatalogus, Pi/Frame,
macOS/Linux hardwaretests, langdurige opnameproef. De web-API blijft voor historie
op de 500-eventbuffer gebaseerd; de recorder bewaart de cumulatieve soorttotalen.
Een onbekende soort zonder plaat blijft in de API maar krijgt geen automatische
nieuwe afbeelding; de bestaande frontend kan een ontbrekend-beeldweergave tonen.

API-bronnen: [sounddevice opname](https://python-sounddevice.readthedocs.io/en/0.5.6/api/raw-streams.html)
en [BirdNET](https://github.com/birdnet-team/birdnet). De geïnstalleerde library en
bestaande WAV-aanroep waren leidend bij implementatie.
