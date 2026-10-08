# Samsung Frame — handmatige test

Doelapparaat: Samsung The Frame 50LS03F (2025), vanaf Raspberry Pi 5 via Wi-Fi.
Deze module staat binnen het bestaande zelfstandige Backyard-project.
De transportlibrary is [samsungtvws 3.0.6](https://github.com/xchwarze/samsung-tv-ws-api),
met de actuele Art Mode-socketupload en protocolversiedetectie voor moderne
Frame-tv's. Geen cloudaccount, Gemini of OpenAI nodig. Compatibiliteit met de
specifieke LS03F-firmware en daadwerkelijke weergave zijn nog niet op hardware
geverifieerd; de eigenaar voert die acceptatietest uit.

## Installatie via SSH

Vervang `PI_USER` en `PI_HOST` door je SSH-gebruiker en Pi-hostnaam/IP.
Dit gebruikt `~/AvianVisitors` als checkout; gebruik je bestaande checkoutpad
als Backyard elders is geïnstalleerd. Python 3.11+ is vereist.

```bash
ssh PI_USER@PI_HOST
sudo apt-get update
sudo apt-get install -y git python3-venv python3-pip
test -d "$HOME/AvianVisitors/.git" || git clone --branch main https://github.com/CPVB86/AvianVisitors.git "$HOME/AvianVisitors"
cd "$HOME/AvianVisitors"
git switch main
git pull --rebase
cd backyard
test -x .venv/bin/python || python3 -m venv .venv
.venv/bin/python -m pip install -r requirements-samsung-frame.txt
test -f .env || cp .env.example .env
chmod 600 .env
nano .env
```

Stel in `backyard/.env` het echte tv-IP in, bijvoorbeeld:

```dotenv
BACKYARD_SAMSUNG_FRAME_HOST=192.168.1.50
BACKYARD_SAMSUNG_FRAME_TOKEN=
BACKYARD_SAMSUNG_FRAME_TOKEN_PATH=data/samsung_frame/token
BACKYARD_SAMSUNG_FRAME_STATE_PATH=data/samsung_frame/artwork.json
BACKYARD_SAMSUNG_FRAME_IMAGE_PATH=data/samsung_frame/samsung-frame.png
BACKYARD_SAMSUNG_FRAME_TIMEOUT=60
```

Voeg ontbrekende instellingen toe; vervang bestaande API/database-instellingen
niet. Een API-token is verplicht voor de Backyard-API, maar niet voor deze CLI.
Environment heeft voorrang op `.env`; relatieve paden zijn vanaf `backyard/`,
ook wanneer het proces vanuit een andere directory wordt gestart.
Samsung-authenticatie gebeurt via tv-goedkeuring en daarna het tokenbestand.
Een bestaand token kan optioneel via `BACKYARD_SAMSUNG_FRAME_TOKEN` worden
aangeleverd. Zet het niet op de commandoregel; laat deze instelling normaal leeg.

## Eerste handmatige test

Zet de tv aan, verbind Pi en tv met hetzelfde lokale subnet, en houd de
afstandsbediening gereed om **Backyard Samsung Frame** toe te staan. Controleer
op de tv de externe apparaatverbindingen/IP-afstandsbediening. Menunamen hangen
van taal/firmware af. De library gebruikt lokaal WSS/HTTPS op poort 8002 en het
door de tv aangewezen uploadsocket; netwerkisolatie kan dit blokkeren.
Samsung gebruikt een zelfondertekend certificaat; de library valideert dat niet.
Gebruik dit op je vertrouwde lokale netwerk.

Vanuit `~/AvianVisitors/backyard`:

```bash
.venv/bin/python -m app.modules.samsung_frame connect
.venv/bin/python -m app.modules.samsung_frame generate
.venv/bin/python -m app.modules.samsung_frame upload
.venv/bin/python -m app.modules.samsung_frame status
```

`connect` test werkelijk de pairing, Art API en tv-identiteit; het uploadt niets.
`generate` maakt `data/samsung_frame/samsung-frame.png`: exact 3840 × 2160,
gekleurde geometrische compositie, rand en kruislijnen, kleine lokale timestamp
rechtsonder. `upload` schakelt Art Mode in en selecteert het nieuwe artwork.
Succes geeft `activated` met het Samsung-content-ID en `matte: none` terug.
Controleer op de tv de compositie, timestamp, schermvulling en afwezigheid van
passe-partout. `status` leest de live verbinding, actieve afbeelding en het
eigendomjournal; bij verbindingsfalen toont het het lokale journal en exitcode 1.
Connect/status veranderen geen artworks; pairing kan wel een token opslaan.

Een tweede handmatige proef met nieuwe timestamp:

```bash
.venv/bin/python -m app.modules.samsung_frame generate
.venv/bin/python -m app.modules.samsung_frame upload
.venv/bin/python -m app.modules.samsung_frame status
```

## Veiligheid, fouten en herstel

- Upload gebruikt altijd `matte="none"` én `portrait_matte="none"`. Geen
  automatische fallback of tweede upload met passe-partout.
- De module vereist terugmelding `matte_id="none"` in de contentlijst én bij
  het actieve artwork, het juiste actieve ID en Art Mode `on`. Ontbrekende of
  afwijkende terugmelding is een fout; er wordt dan niets oud verwijderd.
- `data/samsung_frame/artwork.json` bewaart alleen eigen `MY_`-IDs, gekoppeld
  aan de werkelijke `device.id`. Samsung-IDs (`SAM_`) worden geweigerd. De enige
  delete-call richt zich op `previous` uit dit journal; nooit bulkverwijdering.
- Upload-ID wordt vóór selectie opgeslagen; actieve ID vóór verwijdering.
  Atomisch vervangen en fsync beschermen tegen gedeeltelijke lokale writes;
  een OS-lock verhindert overlappende modulecommando's. Na procescrash wordt
  die lock vanzelf vrijgegeven.
- Token en journal krijgen op Linux bestandsmodus 0600, hun opslagmappen 0700.
  Gebruik hiervoor een aparte map die eigendom is van de uitvoerende Pi-user.
  Windows heeft andere ACL-semantiek; bescherm daar de userdirectory met ACLs.
  Tokens worden niet gelogd. Bestanden en afbeelding vallen onder genegeerde
  `backyard/data/`; een aangepast pad moet je zelf buiten Git houden.
- Tv-/libraryfouten verschijnen met exceptiontype en oorspronkelijke melding
  (eventueel onderliggende fout), met authenticatietokens geredigeerd. Exitcode
  is 1. Een mislukte opruiming kan betekenen dat het nieuwe beeld al actief is;
  het journal bewaart dan de vorige ID voor herstel.

Bij een onderbroken upload eerst `status` lezen. Staat er `pending` of
`previous`, hervat expliciet zonder opnieuw uploaden:

```bash
.venv/bin/python -m app.modules.samsung_frame upload --resume
```

Dit verifieert/selecteert opnieuw de al opgeslagen nieuwe afbeelding en
verwijdert pas daarna de vorige. Een gewone upload weigert zolang herstel nodig
is. Blijvende passe-partout/selectionfouten blijven fouten: bewaar journal en
foutmelding voor diagnose, wijzig geen IDs handmatig. Bij timeout vóór ontvangst
van een content-ID kan op de tv een onbekend uploadrestant staan. Dat wordt
nooit automatisch verwijderd; controleer het zelf in Mijn foto's op de tv.
Als het journal ontbreekt, neemt de module geen bestaande artworks in beheer.
Maak een veilige backup van token/journal; gebruik een apart opslagpad voor een
andere tv. Handmatige externe artworkverwijdering kan herstel blokkeren.

## Voorbereiding op Avian

`FrameService.upload(image_path)` is onafhankelijk van de testgenerator en CLI.
Later kan Avian dezelfde geconfigureerde `samsung-frame.png` atomisch vervangen;
upload leest één volledige snapshot en valideert PNG en resolutie. De producer
moet een tijdelijk bestand naar de doelnaam hernoemen om halve bestanden te
vermijden. De upload-/selectie-/ownershiplogica hoeft daarvoor niet te wijzigen.
Deze oplevering bevat geen Avian-code, polling, 15-minutenschema of systemd-unit.

## Minimale technische controles — 8 oktober 2026

Uitgevoerd op Windows, in de bestaande Backyard-venv: 29 pytest-cases geslaagd
(11 Samsung en 18 bestaande backendcases), CLI-help, test-PNG-generatie en
`pip check`. De Samsung-tests gebruiken een fake tv, geen netwerkverbinding.
Onder meer upload-, matte-, selectie-, terugmelding- en opslagfalen behouden het
oude artwork; hervatten en verkeerd tv-ID zijn getest. Eén bestaande upstream
Starlette/httpx-deprecationwaarschuwing. Pi/ARM-installatie, pairing, netwerkupload
en de fysieke weergave blijven de handmatige acceptatietest van de eigenaar.

```bash
.venv/bin/python -m pytest tests/test_samsung_frame.py tests/test_foundation.py -q
```
