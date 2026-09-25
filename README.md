# AvianVisitors

*A live bird collage from your window.*

See it running at [bird.onethreenine.net](https://bird.onethreenine.net).

<img alt="avianvisitors collage" src="docs/thumb.png" />

---

## Desktop / Local development

De desktopversie draait op **Python 3.10+** op Windows, macOS of Linux.
De standaardroute gebruikt simulatie. Optioneel zijn lokale live BirdNET-detecties
beschikbaar; zie [continu luisteren met BirdNET](docs/LIVE_BIRDNET.md).

De live Windows-recorder is een proof-of-concept. Voor de vervolgrichting naar
een Linux-backend en Raspberry Pi 5: [BirdNET-backendadvies](docs/BIRDNET_BACKEND_ADVICE.md).

```bash
git clone --branch avian-visitors https://github.com/CPVB86/AvianVisitors.git
cd AvianVisitors
python demo/server.py
```

Dit is de hoofdroute. Op Windows mag `py -3` en op macOS/Linux `python3`
worden gebruikt wanneer `python` niet beschikbaar is. De gewone app heeft
**geen pip-pakketten** nodig. Installeer niet de root `requirements.txt`:
die hoort bij de oorspronkelijke BirdNET/Pi-installatie.

Open **http://127.0.0.1:8000/**. Stop in de terminal met **Ctrl+C**.
Met `python demo/server.py --open-browser` opent de standaardbrowser automatisch
via Python `webbrowser`; zonder die optie verandert er niets aan je browser.
De server luistert uitsluitend op deze computer (127.0.0.1).

### Configuratie

Kopieer optioneel `.env.example` naar `.env` in de repository-root. Een schone
checkout start ook zonder `.env`. De shellomgeving heeft voorrang op het bestand.

| Instelling | Standaard | Gebruik |
|---|---|---|
| `APP_MODE` | `demo` | `demo` voor simulatie, `birdnet` voor live JSON. |
| `BIRDNET_JSON_PATH` | leeg | Vereist bij `APP_MODE=birdnet`; pad naar live-detections.json. |
| `APP_PORT` | `8000` | Lokale poort; `--port 8001` heeft voorrang. |
| `DEMO_FIXTURE` | `demo/species.json` | JSON-detecties; relatieve paden zijn vanaf de repository-root. `--fixture` heeft voorrang. |
| `OPENAI_API_KEY` | leeg | Alleen nodig voor expliciet genereren van ontbrekende beelden. |
| `OPENAI_IMAGE_MODEL` | `gpt-image-2.5-flare` | Model voor de optionele generator. |
| `OPENAI_IMAGE_QUALITY` | `medium` | Kwaliteit voor de optionele generator. |

Er zijn geen verplichte environment variables voor de desktopapp. `.env` en
`.avian/` blijven buiten Git. Deel de sleutel niet via URL of commandoregel.

### Simulatie en opgeslagen beelden

De standaardfixture levert 42 detecties over zeven soorten met meegeleverde
illustraties. Tijdstippen worden relatief aan de huidige tijd berekend.
**24H → 1H** laat het verschil zien: zeven soorten tegenover één Huismus met
twee detecties. Dit is geen groeiende detectiehistorie en gebruikt geen database.

Voor eigen data: kopieer `demo/species.json` naar `.avian/species.json`, pas
namen/aantallen aan en stel `DEMO_FIXTURE=.avian/species.json` in. Maak de map
`.avian` indien nodig eerst aan. Herstart na een fixturewijziging. Elke rij heeft
`sci`, `com` en `count` (0–1000); maximaal 100 unieke soorten. Ongeldige JSON of
velden geven een gecontroleerde startfout. Een soort zonder beeld/masker wordt
met een waarschuwing overgeslagen; overige soorten blijven bruikbaar.

Lokale beelden staan in `.avian/illustrations/`, originele API-uitvoer in
`raw/` daaronder en maskers/afmetingen in `.avian/frontend/`. Complete lokale
beelden krijgen voorrang op de meegeleverde bibliotheek. Ontbrekende of ongeldige
lokale tabellen vallen terug op meegeleverde beelden. Voor een uitsluitend lokaal
beschikbare soort: herstel de cache uit je back-up of bouw maskers opnieuw met
de generator (bestaande beelden worden overgeslagen). Bewaar de hele `.avian/`
beeldenset als je naar een andere computer verhuist; die wordt niet gepusht.

### OpenAI (optioneel)

Alleen voor beeldgeneratie:

```bash
python -m pip install -r demo/requirements-images.txt
python demo/generate.py --sci "Turdus merula" --com "Merel" --pose both --dry-run
```

Vul de sleutel lokaal in `.env` in. Verwijder `--dry-run` alleen wanneer je bewust
betaalde beelden wilt maken. `--pose both` maakt de ontbrekende zittende en
vliegende pose. Bestaande beelden worden hergebruikt; `--force` genereert opnieuw.
Starten, bladeren en verversen veroorzaken nooit API-kosten.
Zie [OpenAI en referentiebeelden](docs/OPENAI_IMAGES.md) voor details.

### Problemen oplossen, stoppen en updaten

- Poort bezet: stop de andere server of gebruik `--port 8001`.
- Startfout: controleer modus, poort en fixture; waarden uit `.env` worden niet gelogd.
- Geen vogels: kies 24H en controleer aantallen en waarschuwingen over ontbrekende beelden.
- Beschadigde lokale maskers: herstel `.avian/frontend/` of voer de generator uit
  voor een al bestaande soort; hergebruik kost geen API-aanvraag (Pillow is nodig).
- API-fout: de generator stopt zonder automatische retry. Bij een time-out eerst
  API-verbruik controleren; een aanvraag kan al verwerkt zijn. De app blijft bruikbaar.
- Wijzigingen niet zichtbaar: herlaad de pagina; na fixture/configwijziging de server herstarten.
- Updaten: **Ctrl+C**, `git pull --rebase`, daarna opnieuw `python demo/server.py`.

Audio, externe encyclopedie en stationbeheer zijn niet beschikbaar in de
simulatie. De bestaande Pi/BirdNET/e-inkcode blijft behouden.

Gerichte desktoptests (Pillow nodig voor de beeldtests):

```bash
python -m unittest tests.test_demo tests.test_openai_images -v
```

Zie [desktopstatus en uitgevoerde controles](docs/LOCAL_DEVELOPMENT_STATUS.md).

## Nederlandse interface en OpenAI-illustraties

De publieke collage heet **Vogel Bezoeken**. Collage, Stats, Atlas en life list
blijven herkenbaar; koppen, statistieken, familienamen en vogelgegevens zijn
Nederlands. De BirdNET-detecties blijven voorlopig gesimuleerd.

De optionele OpenAI-generator maakt lokale transparante illustraties en
silhouetmaskers, zonder de productie/Gemini-route te wijzigen. Zie
[OpenAI instellen en één proefafbeelding maken](docs/OPENAI_IMAGES.md).
API-sleutels horen in de genegeerde `.env`; uitvoer staat in de genegeerde
`.avian/`. Normaal bladeren veroorzaakt geen beeldgeneratie.

## BOM

| Qty | Description | Price | Link | Notes |
|-----|-------------|-------|------| ----- |
| 1 | Raspberry Pi (4B / 5 / 3A+ / Zero 2W) | ~$25-80 | [Amazon](https://amzn.to/43yLDZJ) | [See note for 512 MB Pis](https://github.com/mcguirepr89/BirdNET-Pi/wiki/RPi0W2-Installation-Guide) |
| 1 | Micro SD Card (≥32 GB) | ~$10 | [Amazon](https://amzn.to/4eGy7te) | |
| 1 | USB lavalier microphone | $16.95 | [Amazon](https://amzn.to/4vLSaMK) | |
| 1 | Pi power supply | ~$10 | - | |

Optional: a [Gemini API key](https://aistudio.google.com/apikey) to restyle illustrations, an [eBird API key](https://ebird.org/api/keygen) to filter species by region.

### Kits

I offer the bird mic and the wall frame as separate electronics kits. I put up a store for some of my open-source projects and will soon be able to offer kits cheaper than buying all the components individually, once I start buying in bulk.

- [Bird mic kit](https://theodore.net/store/avian-mic/)
- [Frame kit](https://theodore.net/store/avian-visitors/)

---

## 1. Flash the SD card

Use [Raspberry Pi Imager](https://www.raspberrypi.com/software/). Pick Raspberry Pi OS Lite (64-bit). In the customisation dialog set:

- Username
- WiFi SSID + password
- Hostname: `birdnet`
- Enable SSH with password auth

Plug the USB mic into the Pi. Place the capsule in a window or mount it outside. Boot.

---

## 2. Run the installer

Installer assumes passwordless sudo (Raspberry Pi OS Lite default - if you've tightened it, run `sudo raspi-config` -> *System Options* -> restore the default first).

```bash
ssh <your-username>@birdnet.local
curl -s https://raw.githubusercontent.com/Twarner491/AvianVisitors/avian-visitors/newinstaller.sh | bash
```

Clones this fork, installs BirdNET-Pi, symlinks the AvianVisitors overlay into the Caddy web root. Takes 20-40 minutes. Reboots when done.

Collage: `http://birdnet.local/`. Stock BirdNET-Pi UI: `http://birdnet.local/index.php`. The menu button in the top right opens an admin overlay with Settings, System, Logs, and Tools.

Stock BirdNET-Pi pages still render, but privileged legacy controls are not enabled. Use the Avian Visitors menu for the station controls it exposes, and SSH for remaining maintenance.

Optional Google Drive backups are set up under **Settings → Nightly Drive backup**. Local cleanup stays unavailable until an archive run has been verified.

### Local admin access

Optional password protection for local administrator controls can be enabled in **Settings**. Public bird pages remain available without signing in, while live audio is unavailable when protection is on.

If no password is configured, or the state is missing or invalid, recover it from an SSH session:

```bash
sudo /usr/local/sbin/avian-admin-control password-reset
```

The command prompts privately for a new password. Return to **Settings** after it finishes.

### Educators mode

Educators mode is an optional profile for the BirdNET-Pi website. It adds a fifth menu page for starting, pausing, organizing, and reviewing listening periods. Enable it after installation over SSH:

```bash
sudo /usr/local/sbin/avian-educators enable
```

New stations can also install with the profile enabled:

```bash
curl -fsSL https://raw.githubusercontent.com/Twarner491/AvianVisitors/avian-visitors/newinstaller.sh | bash -s -- --educators
```

Listening periods scope the Collage, Stats, Atlas, and available detection clips without copying or protecting audio files from normal retention. Saved period and folder exports require a direct local connection. See [the Educators guide](docs/educators.md) for the full workflow and privacy details.

### Updating an existing station

For the first v1 update, keep the existing checkout and run:

```bash
upgrade=$(mktemp "$HOME/avian-v1-upgrade.XXXXXX")
curl -fsSL https://raw.githubusercontent.com/Twarner491/AvianVisitors/avian-visitors/scripts/bootstrap_v1.sh -o "$upgrade"
sudo bash "$upgrade"
rm -f "$upgrade"
```

After v1, use **Tools → Pull latest** or run:

```bash
cd ~/BirdNET-Pi
./scripts/update_birdnet.sh
```

The updater keeps generated mask data and stops if tracked files have local edits. If its service setup needs repair, use **Tools → Reinstall services** or run:

```bash
cd ~/BirdNET-Pi
./scripts/reinstall_services.sh
```

---

## 3. (Optional) Restyle the illustrations

The repo ships with 666 bundled illustrations (333 species, perched + flight). To restyle them or generate a set for your own region:

```bash
pip install -r ~/BirdNET-Pi/avian/scripts/requirements.txt
export GEMINI_API_KEY='your-key'  # image generation requires billing enabled

# generate on a cream ground, cut the ground off, rebuild the collage masks
python3 ~/BirdNET-Pi/avian/scripts/pregen.py --labels ~/BirdNET-Pi/model/labels.txt --force
python3 ~/BirdNET-Pi/avian/scripts/cutout.py
python3 ~/BirdNET-Pi/avian/scripts/build_masks.py
```

On a Pi with 4 GB of RAM or less, add `--model u2net` to the `cutout.py` command; the default model may be [OOM-killed](https://github.com/Twarner491/AvianVisitors/issues/17).

Filter to your region with `--ebird-region US-CA` (needs `EBIRD_API_KEY`). The full pipeline, prompt, reference images, and per-species tuning live in [`avian/scripts/README.md`](avian/scripts/README.md). Style lives in [`prompt.template.md`](avian/scripts/prompt.template.md).

See [illustration bundles](illustration-bundles.md) for pregenerated bundles shared by other folks in the community, or share your own for others to use!

---

## 4. (Optional) Forward off your LAN

See [`avian/forwarding/`](avian/forwarding/) for three independent recipes:

- **Cloudflare Tunnel** for a public HTTPS URL.
- **Home Assistant REST sensor** that exposes the latest detection.
- **MQTT bridge** that publishes every new detection.

---

## Repo layout

```
avian/                  # everything we add to BirdNET-Pi
├── frontend/           # static HTML/JS/CSS for the collage
├── assets/             # 666 bundled illustrations + photo-cutout fallbacks
├── api/                # PHP shims served by BirdNET-Pi's PHP-FPM
├── scripts/            # generate -> cutout -> masks pipeline + prompt
└── forwarding/         # optional HA / MQTT / Cloudflare configs
frame/                  # optional e-ink wall display
```

Everything outside `avian/` and `frame/` is upstream BirdNET-Pi.

---

## Wall frame

An optional e-ink frame puts the bird collage on a panel by your window. Build it from [`frame/`](frame/README.md). It can run off your own BirdNET mic, from BirdWeather around a ZIP code, or from one public BirdWeather station with `frame/install.sh --station-id <ID>`.

---

## License

CC-BY-NC-SA-4.0, inherited from [BirdNET-Pi](https://github.com/Nachtzuster/BirdNET-Pi/blob/main/LICENSE). Non-commercial use only. See the [BirdNET-Pi README](https://github.com/Nachtzuster/BirdNET-Pi/blob/main/README.md) for full Cornell attribution.

---

- [Fork this repository](https://github.com/Twarner491/AvianVisitors/fork)
- [Watch this repo](https://github.com/Twarner491/AvianVisitors/subscription)
- [Create issue](https://github.com/Twarner491/AvianVisitors/issues/new)
