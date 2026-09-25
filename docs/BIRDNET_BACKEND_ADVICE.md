# BirdNET-backendadvies — 25 september 2026

## Advies en afbakening

Kies **BirdNET-Go**, eerst in een **Debian 13 x86-64 VM** op Windows met
USB-microfoonpassthrough, later op **Raspberry Pi 5 met Raspberry Pi OS Lite
64-bit**. Gebruik dezelfde vastgezette BirdNET-Go-release en configuratie,
met de container voor de betreffende processorarchitectuur. AvianVisitors leest
de lokale REST-API; BirdNET-Go beheert audio, classificatie en detectiehistorie.

Dit is het resultaat van fase 1: repository- en bronnenonderzoek. Er is nog
geen VM geïnstalleerd, geen Linux-audiotest uitgevoerd en geen nieuwe adapter
gebouwd. Realtime geschiktheid op deze laptop is dus nog niet bewezen.
De bestaande demo, JSON-koppeling, recorder en beeldencache blijven behouden.

## Vergelijking

Onderhoudsstatus gecontroleerd via de GitHub-API op 25 september 2026.
Een recente commit bewijst activiteit, geen betrouwbaarheid in onze opstelling.

| Stack | Onderhoud en platform | Audio/inference, opslag en interface | Beoordeling |
|---|---|---|---|
| [BirdNET-Go](https://github.com/tphakala/birdnet-go) | Actief; laatste main-commit 24 september. Linux amd64 en arm64; Debian/Ubuntu/Pi OS. | Go, continue audiobuffers, TFLite/ONNX afhankelijk van model; SQLite standaard, MySQL optioneel; REST-API. Docker met systemd-service is gedocumenteerd. | Beste aansluiting op laptop én Pi en op een afzonderlijke datasource. |
| [Nachtzuster/BirdNET-Pi](https://github.com/Nachtzuster/BirdNET-Pi) | Onderhouden fork; laatste commit 28 februari. Pi 5 ondersteund; Bookworm/Trixie; Debian 12/13 x86-64 wordt ook beschreven voor ontwikkeling. | Python/TFLite, arecord naar WAV-blokken, afzonderlijke analyseprocessen en systemd-services; SQLite birds.db. | Goede tweede keuze, vooral voor aansluiting op de bestaande PHP/SQLite-route. |
| [Suncuss/BirdNET-PiPy](https://github.com/Suncuss/BirdNET-PiPy) | Actief; laatste commit 20 september; geen genummerde GitHub-release gevonden. Pi 64-bit/Bookworm of nieuwer. | Python/FFmpeg, aparte modelserver, SQLite, Flask REST/Socket.IO; vijf containers met onder meer Icecast. | Interessant modern alternatief, maar meer componenten en minder duidelijk gedocumenteerde x86-64-installatieroute. |
| [mcguirepr89/BirdNET-Pi](https://github.com/mcguirepr89/BirdNET-Pi) | Oorspronkelijke repository is gearchiveerd. | Historische BirdNET-Pi-architectuur. | Geen nieuwe installatie hierop baseren. |
| [Kytutr/BirdNET-Pi](https://github.com/Kytutr/BirdNET-Pi) | Actieve fork; laatste commit 13 september. | BirdNET-Pi-afstamming. | Onderzocht als extra kandidaat; geen aangetoond voordeel voor onze API/VM-route dat keuze boven Go rechtvaardigt. |

Bij Nachtzuster bevat het onderzochte `scripts/api.php` een beeldmetadata-API,
geen vergelijkbaar compleet contract voor detectiehistorie. Daarom is lokale
read-only SQLite hier de voornaamste terugvalroute. Dit is geen claim dat iedere
fork of uitbreiding zonder API is. De bestaande
`avian/api/birdnet-api.php` leest al BirdNET-Pi's `birds.db`.

PiPy scheidt opname, modelserver, API en frontend. De installatie beschrijft
ARM64-wheels en gedeelde PulseAudio/PipeWire-audio. Een gelijkwaardige
x86-64-VM-installatie vraagt extra verificatie; die is niet als onmogelijk
beoordeeld. Zie [architectuur](https://github.com/Suncuss/BirdNET-PiPy/blob/main/docs/ARCHITECTURE.md)
en [installatie](https://github.com/Suncuss/BirdNET-PiPy/blob/main/docs/INSTALLATION.md).

## BirdNET-Go: versie, audio en capaciteit

Start met de stabiele [release 20260823](https://github.com/tphakala/birdnet-go/releases/tag/20260823),
op de onderzoeksdatum de nieuwste stabiele release. Leg tijdens installatie
ook de image-digest vast; gebruik geen meebewegende nightly-tag.
Begin met één audiobron en het standaardmodel **BirdNET 2.4**. Versie 3.0 is
in deze release expliciet een developer preview. Dit wijkt bewust af van het
Windows-prototype met 3.0 ONNX: vergelijk verwerkingssnelheid en herkenning dus
niet alsof beide exact dezelfde classifier gebruiken.

De gedocumenteerde pipeline heeft afzonderlijke opname- en analysebuffers,
modelafhankelijke vensters en overlap, plus wachtrijen voor vervolgtaken zoals
opslag. Er is bewaking van audiobronnen. Deze architectuur is geschikt voor
continu gebruik, maar buffers maken een te trage classifier niet vanzelf
realtime. Overlap, model en aantal bronnen moeten daarom worden vastgelegd
en gemeten. Zie de [pipeline bij de gekozen release](https://github.com/tphakala/birdnet-go/blob/20260823/doc/wiki/detection-pipeline.md).

De [hardwaredocumentatie](https://github.com/tphakala/birdnet-go/blob/20260823/doc/wiki/hardware.md)
beveelt Pi 5 aan: 2 GB voor één bron met BirdNET 2.4, 4 GB voor meer ruimte
of meerdere modellen. De genoemde circa 400 MB is een schatting voor de
kernprocessen, geen meting van ons totale systeem. Die documentatie noemt
Bookworm als voorkeursimage en Debian 12 of nieuwer als alternatief.
Trixie is inmiddels een [officiële Pi OS-basis](https://www.raspberrypi.com/news/trixie-the-new-version-of-raspberry-pi-os/).
Daarom stel ik Debian 13/Trixie voor beide omgevingen voor, met een echte
installatie- en audiotest als voorwaarde. Voor geen van de kandidaten is hier
een vergelijkbare eigen Pi 5 CPU/RAM/latencybenchmark uitgevoerd.

De [installatieroute](https://github.com/tphakala/birdnet-go/blob/20260823/doc/wiki/installation.md)
gebruikt Docker en een systemd-service met persistente config/data en toegang
tot `/dev/snd`. Bewaar SQLite op de Linux-schijf, niet als actieve database
op een Windows-netwerkshare. Maak consistente back-ups via een geschikte
SQLite-back-up of terwijl de service gestopt is. Stel bewaartermijnen voor
audio apart in en verifieer dat historische detectierijen behouden blijven.

## Windows: VM boven WSL2

Lokaal vastgesteld: Intel Core 5 120U, 32 GB RAM, hypervisor aanwezig,
Ubuntu onder WSL2 geïnstalleerd maar gestopt. Dat geeft voldoende ruimte
voor een eerste VM met bijvoorbeeld **4 vCPU, 4 GB RAM en 40 GB schijf**.
Dit zijn startwaarden voor onze proef, geen officiële minimumvereisten.

Mijn voorkeur is een volledige Debian-VM, bijvoorbeeld met VirtualBox 7.2,
en een USB-audioapparaat dat rechtstreeks aan de gast wordt toegewezen.
[Oracle documenteert USB-passthrough](https://docs.oracle.com/en/virtualization/virtualbox/7.2/user/working-with-vms.html).
Eerst moet Linux de microfoon consequent herkennen en een lange opname kunnen
maken. Betrouwbaarheid met onze microfoon en de Windows-hypervisor is nog
onbekend; test ook opnieuw aansluiten en een volledige herstart.

[WSLg ondersteunt microfooninvoer](https://github.com/microsoft/wslg),
dus WSL2 valt niet principieel af. De PulseAudio/RDP-route is echter niet
hetzelfde als een Linux USB-microfoon onder `/dev/snd`; de standaard
Docker-audiomapping werkt daardoor niet vanzelf. Ook
[USB doorgeven aan WSL](https://learn.microsoft.com/en-us/windows/wsl/connect-usb)
vraagt apparaat- en driververificatie. Kies WSL pas wanneer een lange test
betrouwbare audio bewijst. Die extra afwijking maakt de VM nu de voorkeur.

## AvianVisitors: read-only API-adapter

De huidige `DemoDetectionSource` en JSON-`BirdNETDetectionSource` in
`demo/server.py` vormen de juiste grens. Voeg later een afzonderlijke
`BirdNETGoDetectionSource` toe, bijvoorbeeld met modus `birdnet-go`.
Laat `demo` en `birdnet` hun huidige betekenis behouden.

```text
USB-microfoon → BirdNET-Go → eigen persistente SQLite
                                  ↓ lokale REST-API
                         BirdNETGoDetectionSource
                                  ↓ bestaand frontendcontract
                         Vogel Bezoeken / collage
```

De volgende GET-routes zijn in de broncode van release 20260823 gevonden.
Dit is een mappingvoorstel, nog geen getest adaptercontract.

| Scherm/functie | BirdNET-Go-route onder `/api/v2` | Te controleren |
|---|---|---|
| Recent, today, last hour, week, soortdetail | `/detections`, `/detections/recent` | Paginering en exacte tijdvensters; recent alleen is onvoldoende voor historie. |
| Lifelist, first/last seen, aantallen | `/analytics/species/summary` | Bevat scientific_name, count, first_heard, last_heard en confidence; volledige periode opvragen. |
| Kalender, timeseries, dagtotalen | `/analytics/time/daily`, `/analytics/species/daily` | Datumgrenzen, lege dagen en soortselectie. |
| Hourly en rhythm | `/analytics/time/hourly`, `/analytics/time/distribution/hourly` | Betekenis van uurvak en verdeling; zo nodig gefilterde detecties aggregeren. |
| Confidence | Detectierijen en `/analytics/confidence/distribution` | Schaal, ontbrekende waarden en dezelfde filters als aantallen. |

Bronnen: [detectieroutes](https://github.com/tphakala/birdnet-go/blob/20260823/internal/api/v2/detections/handler.go),
[analyticsroutes](https://github.com/tphakala/birdnet-go/blob/20260823/internal/api/v2/analytics/handler.go)
en [responsevelden](https://github.com/tphakala/birdnet-go/blob/20260823/internal/api/v2/analytics/analytics.go).

Gebruik uitsluitend leesroutes met time-outs, begrensde retries en een laatste
geldige response bij tijdelijke uitval. De API is versiegebonden; leg het
contract vast in tests voordat een backendupgrade wordt toegepast. Query de
historie bij BirdNET-Go in plaats van een tweede eventdatabase te bouwen.
Controleer standaardfilters expliciet: analytics heeft bijvoorbeeld een
standaard confidence-drempel van 0.8, terwijl het prototype 0.60 gebruikt.
Zonder afstemming kunnen schermen verschillende aantallen tonen.

Behoud `scientific_name` als sleutel. Leg tijdzone Europe/Amsterdam en zomer-
en wintertijd vast. Onderscheid opgeslagen detecties van ruwe modelkandidaten.
De oude 500 JSON-events en soorttotalen reconstrueren geen verdwenen
historische tijdstippen: bewaar die als prototypehistorie, verzin geen events
bij migratie. Detecties starten nooit automatisch betaalde beeldgeneratie.

## Volgende fasen en bewijs

Na backendkeuze:

1. Installeer Debian, koppel de USB-microfoon en bewijs eerst stabiele opname.
   Leg hypervisor, audioapparaat en instellingen vast. Voorkom host-slaapstand
   gedurende de proef.
2. Installeer de vastgezette Go-release als service; leg model, overlap,
   confidence, tijdzone, volumes en netwerktoegang vast. Houd de API lokaal
   bereikbaar voor de Windows-app, zonder publieke portforwarding.
3. Doe eerst een korte rooktest en daarna een proef van minimaal 24 uur.
   Meet analysecapaciteit, audio-uitval/drops, detectievertraging, CPU/RAM en
   schijfgroei. Een kleine queue met voortdurend gedropte audio is geen
   geslaagde realtime test. Houd ingestelde bevestigingsvertraging apart van
   echte achterstand; vereis geen oplopende vertraging onder normale belasting.
4. Herstart service en VM: historische rijen, totalen en first/last seen moeten
   behouden blijven en de microfoon moet opnieuw beschikbaar zijn.
5. Bouw pas daarna de adapter. Test meer dan 500 events, tijdgrenzen,
   paginering, confidencefilters en API-uitval. Bewijs een echte detectie tot
   de juiste illustratie; test bestaande demo en JSON-modus opnieuw.
6. Installeer op Pi 5 dezelfde release voor ARM64. Verhuis consistente data-
   en configuratieback-ups, pas apparaat/netwerkpaden aan en herhaal de lange
   test. Een x86-64 VM-schijf kan niet rechtstreeks op ARM64 starten.

Deze ronde verandert uitsluitend documentatie. Installatie, lange audiotest,
adapter en Pi-migratie zijn nog open; Samsung Frame en verdere localisatie
vallen buiten deze fase.
