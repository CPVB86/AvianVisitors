# Backyard

Lokale backend voor één tuin. Backyard bezit de detectiehistorie; BirdNET levert
later detecties. AvianVisitors en WordPress worden API-consumers.

## Scope en structuur

Dit zelfstandige project staat voorlopig in `backyard/` binnen de bestaande
AvianVisitors-repository om bestaande code te behouden. Het importeert niets
uit AvianVisitors en kan later als eigen repository worden afgesplitst.

- `app/core/config.py`: getypeerde configuratie, alleen Backyard's eigen .env.
- `app/core/logging.py`: consolelogging, geen eigen logbestanden of polling.
- `app/core/database.py`: SQLAlchemy-engine, SQLite-initialisatie en UTC-type.
- `app/modules/birds/models.py`: centraal detectiemodel.
- `app/modules/birds/router.py`: begrensde read-only detectielijst.
- `app/main.py`: FastAPI, lifecycle en healthcheck.
- `tests/test_foundation.py`: database-, API- en configuratietests.
- `data/`, `logs/`: gereserveerde lokale uitvoer, buiten Git.
- `requirements*.txt`, `.env.example`, `.gitignore`: geïsoleerde setup.

Weather, Garden en Bats krijgen later eigen packages onder modules met eigen
routers en modellen; nu zijn daarvoor geen lege services of tabellen nodig.
Core importeert geen audio-, hardware- of AI-code.

## Local development / demo mode

Python 3.11+; deze fase is uitgevoerd op Windows met Python 3.14.
Stel eerst `BACKYARD_API_TOKEN` in zoals hieronder beschreven (environment of
`backyard/.env`); zonder geldig token start de API niet.
Vanaf de repository-root, PowerShell:

```powershell
cd backyard
python -m venv .venv
.\.venv\Scripts\python -m pip install -r requirements.txt
.\.venv\Scripts\python -m uvicorn app.main:app --host 127.0.0.1 --port 8010 --no-access-log
```

Linux / Raspberry Pi, vanuit dezelfde repository-root:

```bash
cd backyard
python3 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
.venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8010 --no-access-log
```

Python met venv/pip moet beschikbaar zijn. Er worden geen OS-pakketten of
services automatisch geïnstalleerd. Stop met Ctrl+C. Poort 8010 voorkomt
conflict met de bestaande AvianVisitors-demo op 8000.

Test in een tweede terminal met hetzelfde token in de shellomgeving:

```powershell
Invoke-RestMethod http://127.0.0.1:8010/api/health -Headers @{ Authorization = "Bearer $env:BACKYARD_API_TOKEN" }
```

Op Linux: `curl --fail -H "Authorization: Bearer $BACKYARD_API_TOKEN" http://127.0.0.1:8010/api/health`.
De shell laadt `.env` niet automatisch. Een browserbezoek zonder header geeft 401.

Verwacht HTTP 200:
```json
{"status":"ok","service":"backyard","database":"ok"}
```

De healthcheck voert werkelijk SELECT 1 uit; databasefalen geeft HTTP 503.
`GET /api/birds/detections?limit=50` geeft aanvankelijk `[]`; maximum 100.
Er is nog geen invoerendpoint, demo-seeding, statistiek of afbeeldingregistratie.
Swagger staat op /docs (laadt CDN-assets); /openapi.json beschrijft de API.
Ook deze documentatie/schema-routes vereisen de Bearer-header; rechtstreeks
openen in een browser zonder header geeft 401. Gebruik voor testen een HTTP-client.
Alle `/api/*`-verzoeken, inclusief lokale clients, vereisen de Bearer-header.

## API-token op de Pi / systemd

Genereer eenmalig een sterk random token op de Pi:

```bash
python3 -c 'import secrets; print(secrets.token_urlsafe(32))'
```

Dit levert 32 random bytes (256 bits), URL-veilig gecodeerd. Zet het resultaat
als `BACKYARD_API_TOKEN=<gegenereerd token>` in `backyard/.env`, of in het reeds
gebruikte systemd `EnvironmentFile`. Kopieer `.env.example` alleen bij een nieuwe
installatie; overschrijf geen bestaande configuratie. Bescherm het bestand met
`chmod 600` en zorg dat de API-servicegebruiker het kan lezen. Commit het nooit.
Windows: genereer met `python -c "import secrets; print(secrets.token_urlsafe(32))"`.

Het token is verplicht, 43–128 tekens (`A–Z`, `a–z`, cijfers, `_`, `-`). Configuratie
zonder token of met een ongeldig token stopt de start; er is geen open fallback.
Elke `/api/*`-route vereist `Authorization: Bearer <token>`, inclusief health,
lokale requests, onbekende routes en toekomstige modules. Vergelijking gebeurt
constant-time. Foute/ontbrekende tokens geven een generieke 401 en worden niet
gelogd. Gebruik HTTPS of een privé versleuteld netwerk zoals Tailscale voor
server-to-server transport; Bearer-authenticatie versleutelt HTTP zelf niet.

Herstart de API-service na configuratie/rotatie, bijvoorbeeld
`sudo systemctl restart backyard-api.service` **als de bestaande unit zo heet**.
Gebruik anders de werkelijk geïnstalleerde unitnaam. Alleen bij wijziging van
het unitbestand is vooraf `sudo systemctl daemon-reload` nodig.

Deze repository bevat nog geen Backyard-detectorclient, ingestendpoint of
systemd-units. Er is dus geen bestaande detectorclient aangepast. Als de Pi
aanvullende, niet ingecheckte detectorcode heeft, moet die hetzelfde token uit
zijn environment lezen en bij ieder API-request de Bearer-header meesturen;
herstart dan ook die detectorservice na het instellen/roteren. Er is bewust
geen uitzondering voor localhost. Breng die code eerst onder versiebeheer om
de volledige detector→API-keten hier te kunnen testen. Observation/policy-logica
en de bestaande Birds-database zijn niet gewijzigd.

Stel hetzelfde token in onder **WordPress → Backyard → Instellingen**.
Rotatie: vervang het token op de API, herstart betrokken services en sla het
nieuwe token in WordPress op. Er is geen overgangsperiode met twee tokens.

## Configuratie en opslag

Een .env is optioneel als het verplichte token al via environment beschikbaar is.
Anders: kopieer .env.example naar .env **in backyard/** en stel het token in.
Omgevingsvariabelen hebben voorrang. De AvianVisitors-.env wordt niet geladen.
BACKYARD_DATABASE_PATH is standaard data/backyard.sqlite3; relatieve paden
zijn altijd relatief aan backyard/. BACKYARD_LOG_LEVEL is standaard INFO.
Een verkeerd logniveau of onbekende .env-instelling blokkeert de start.

De database wordt uitsluitend tijdens applicatiestart aangemaakt, inclusief
ontbrekende bovenliggende mappen. Herstart behoudt bestaande gegevens.
create_all maakt ontbrekende tabellen maar migreert geen bestaande schema's.
Voeg vóór een eerste schemawijziging versiebeheer/migraties toe.

Detecties bevatten UUID, waarnemingstijd, wetenschappelijke en optionele gewone
naam, confidence (0–1), bron, optionele audioverwijzing/modelversie, ruwe JSON
metadata en created_at. Audio wordt niet in SQLite opgeslagen. Metadata wordt
wel bewaard, maar niet standaard via de lijst gepubliceerd.
Tijden vereisen een tijdzone, worden naar UTC omgezet en als UTC teruggegeven.
Indexen ondersteunen tijdselecties en soorthistorie; dagstatistieken moeten later
expliciet een lokale tijdzone gebruiken.

SQLAlchemy houdt modellen los van SQLite. PostgreSQL vereist later een driver,
engineconfiguratie, schema-/datamigratie en integratietests; alleen een URL
wijzigen migreert geen data. SQLite gebruikt nu conservatieve standaard
durability, een lock-timeout van 5 seconden en één API-worker.
Geen periodieke writes, SQL-debuglogging of accesslogs bij het startcommando.
Bij echte ingest later transacties bundelen, retentie/back-ups en WAL afwegen.
Kopieer een actieve SQLite-database niet blind voor een back-up.

## Tests

Vanuit backyard, Windows:

```powershell
.\.venv\Scripts\python -m pip install -r requirements-dev.txt
.\.venv\Scripts\python -m pytest tests -q
```

Linux: vervang `.\.venv\Scripts\python` door `.venv/bin/python`.
Tests gebruiken tijdelijke databases; normale opslag blijft onaangeraakt.

## Volgende fase en Pi-services

Eerst deze fundering op de Pi starten en /api/health controleren. Daarna een
ingestcontract ontwerpen met validatie, bron/event-ID voor deduplicatie en
behoud van ruwe modeluitvoer. Pas daarna BirdNET als onafhankelijk proces
aansluiten via lokale HTTP-ingest met begrensde retries/buffering. Het
BirdNET-proces importeert of start de API niet; een detectorcrash laat de API
draaien. Installeer BirdNET/model en kies microfoon pas na controle op de Pi.

Later twee systemd-units: backyard-api en backyard-birds, met eigen
ExecStart, WorkingDirectory, EnvironmentFile en Restart=on-failure.
De API moet onafhankelijk starten, zonder Requires op de detector.
Consolelogs kunnen dan naar journald; retentie apart beoordelen voor de SD-kaart.
Er zijn nu geen unitbestanden geïnstalleerd of systeeminstellingen aangepast.
De API bindt standaard aan loopback en vereist Bearer-authenticatie; netwerk-
bereikbaarheid en schrijftoegang vereisen afzonderlijke configuratie.

Het eerdere docs/BIRDNET_BACKEND_ADVICE.md is historisch advies voor
AvianVisitors. De nieuwe richting is Backyard als eigenaar, zonder Docker.
ARM64, Raspberry Pi, audio en systemd zijn in deze Windows-ronde niet uitgevoerd.

## Uitgevoerde verificatie (29 september 2026)

Aanvulling 1 oktober 2026: 18 backendtestcases slagen, inclusief centrale auth
voor alle API-paden/methoden, ongeldige/dubbele headers, fail-closed configuratie,
en het bestaande read-endpoint (volgorde, limiet, namen, tijd en confidence).
De onderstaande HTTP-procescontrole is historisch, vóór authenticatie.

- Nieuwe lokale venv, dependencies geïnstalleerd, pip check zonder conflicten.
- Zes pytest-cases geslaagd: lege start, persistentie na herstart, UTC-conversie,
  behoud van metadata, confidencegrenzen, tijdzonevalidatie, querylimieten,
  databasefout met 503 en configuratievoorrang/validatie.
- Echt Uvicorn-proces gestart op 127.0.0.1:8010; via HTTP health=200 en
  database=ok, detectielijst=[]; proces daarna gestopt.
- Database en venv worden door Git genegeerd. Geen bestaande applicatiecode gewijzigd.
- TestClient geeft een upstream deprecationwaarschuwing over httpx; tests slagen.
  Bij een dependency-update de testclientcompatibiliteit opnieuw beoordelen.

Directe dependencies zijn vastgezet op de geteste versies; transitieve dependencies
zijn nog niet volledig gelockt. Linux/ARM64-installatie blijft een acceptatiecheck.
Lifecycle-tests gebruiken de contextmanager uit de
[FastAPI-documentatie](https://fastapi.tiangolo.com/advanced/testing-events/).
