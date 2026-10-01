# Backyard WordPress-plugin

Kleine plugin voor WordPress 6.0+ en PHP 7.4+. Geen Composer, buildstap,
cronjobs of lokale kopie van detecties. De API/Pi blijft de bron van waarheid.
Alleen de base URL en het API-token worden als WordPress-opties opgeslagen.

## Installatie

Kopieer deze volledige map `backyard` naar `wp-content/plugins/backyard` op
de WordPress-server van backyard.runiversity.nl. Activeer **Backyard** onder
**Plugins**. Alternatief: zip deze map inclusief de bovenliggende mapnaam
`backyard` en upload via **Plugins → Nieuwe plugin → Plugin uploaden**.
De Python-backend in de repositorymap `backyard/` hoort niet in de pluginzip.

Activatie voegt alleen de standaardinstelling toe, zonder bestaande waarden
te overschrijven. Deactivatie behoudt instellingen. Er zijn geen taken om te
stoppen, tabellen om te verwijderen of rewrite-regels om te vernieuwen.

## API-verbinding testen

1. Open **Backyard → Instellingen** als beheerder (`manage_options`).
2. Vul de base URL in, standaard `http://192.168.1.31:8010`, zonder `/api/health`.
3. Vul het API-token van de Pi in. Klik **Instellingen opslaan**, daarna **Test verbinding**.
4. Een geldig antwoord `{"status":"ok","service":"backyard","database":"ok"}`
   toont **API bereikbaar — database ok**. Databasefalen (HTTP 503), HTTP-fouten,
   ongeldige antwoorden en verbindingsfouten worden afzonderlijk gemeld.

De test gebruikt de opgeslagen URL, doet één server-side GET en wacht maximaal
5 seconden. Redirects worden niet gevolgd; HTTPS-certificaatcontrole blijft aan.
LAN-adressen zijn bewust toegestaan voor de Pi. Alleen beheerders kunnen de
URL aanpassen/testen; beide formulieren gebruiken WordPress-noncecontrole.
Gebruik geen inloggegevens of tokens in de URL.

Het tokenveld blijft na opslaan leeg: leeg opslaan behoudt het bestaande token,
een nieuw geldig token vervangt het. Het opgeslagen token wordt nooit terug in
HTML gezet en wordt niet via REST aangeboden. De Settings API bewaart het in de
WordPress-database: behandel databaseback-ups daarom als vertrouwelijk. De client
stuurt de Bearer-header uitsluitend server-side en volgt geen redirects. Bij
401/403 toont de verbindingstest een authenticatiefout, zonder response/debugdata.

Een extern gehoste WordPress-server kan `192.168.1.31` niet vanzelf bereiken.
Er is een netwerkroute nodig (bijvoorbeeld een privéverbinding), en de API moet
op die interface luisteren. De backend luistert volgens zijn huidige README
standaard alleen op loopback. Publiceer de API niet onbeveiligd om dit op te lossen.
Browser-CORS is voor deze server-side verzoeken niet nodig.
Gebruik HTTPS of een versleutelde privéverbinding (zoals Tailscale).

## Birds-shortcode

De Handleiding heeft ook vier veelgebruikte, kopieerbare beheercommando’s en
een standaard ingeklapt overzicht voor PowerShell en de Pi na SSH. API-tests
vragen het token verborgen in PowerShell op; het WordPress-token komt nooit
in deze commando’s terecht. Pi-paden/services volgen het aangeleverde spiekbriefje;
`operations.status` is niet in deze repository aanwezig. Git ophalen gebruikt
de ingestelde trackingbranch, zonder een branchnaam vast te leggen.

De Handleiding groepeert commando’s en shortcodes in kaarten. Shortcodes hebben
links een titel, korte uitleg en uitklapbare parameters, rechts de kopieerbare code.
Een groene gloed die uitdooft bevestigt het kopiëren; schermlezers ontvangen een
tekstbevestiging. Dit werkt ook met toetsenbordbediening. CSS wordt op Handleiding
en Instellingen geladen; kopieer-JavaScript alleen op Handleiding.
Instellingen groepeert URL, token en testknop in **PI Connection**, met de algemene
opslagknop buiten de kaart. De test blijft de opgeslagen verbinding gebruiken.

Plaats in een WordPress-shortcodeblok:

```text
[backyard_birds_log]
[backyard_birds_log limit="50"]
```

Standaard 25, minimaal 1 en maximaal 100 registraties. De tabel toont Tijd,
Soort, Latijnse naam en Confidence als percentage, nieuwste bovenaan. Tijden
volgen de WordPress-tijdzone. Er zijn nette lege- en foutmeldingen. De shortcode
leest uitsluitend de bestaande Birds-detectietabel via `/api/birds/detections`;
geen demo-data, nieuwe taxonomie/policy of databasekopie. In deze backend zijn
nog geen ingest- of observation/status-endpoints aanwezig.

Route: browser → WordPress/PHP → authenticated Backyard API. Bezoekers krijgen
alleen HTML; geen token, privé-API-URL, JavaScript-fetch, audio of afbeeldingen.
Er is geen caching of polling. Latere mediaweergave kan dezelfde server-side
API-client gebruiken met afzonderlijke WordPress-presentatie/proxylogica.

## Structuur

- `backyard.php`: bootstrap, lifecycle en laden van vier onafhankelijke modules.
- `includes/settings.php`: Settings API en URL-validatie.
- `includes/class-backyard-api-client.php`: gedeelde read-only WordPress HTTP-client.
- `modules/<module>/<module>.php`: eigen modulecode en adminpaginabeschrijving.
- Birds biedt `backyard_birds_detections($limit = 50)` voor
  `/api/birds/detections?limit=50` (1–100), met JSON of `WP_Error` als resultaat.
  De shortcode gebruikt deze functie; de adminpagina doet geen datarequest.
- `admin/pages.php`: standaard WordPress-adminpagina's; hoofdmenu met
  `dashicons-carrot`, zonder maatwerk voor submenu-iconen. Volgorde: Birds, Bats,
  Weather, Garden, Instellingen, Handleiding.
- `includes/shortcodes.php`: documentatieregister via filter
  `backyard_shortcode_docs`. Modules voegen entries toe met `module`, `shortcode`,
  `parameters` (naam → toelichting) en `description`. Een toekomstige module
  registreert de echte shortcode apart met `add_shortcode`; dit filter verzorgt
  uitsluitend de handleiding. Alle velden worden als tekst ge-escaped.

Geen dashboard, synchronisatie, audio, afbeeldingen, grafieken, filters of
functionele Bats/Weather/Garden-module.

## Checks

Vanuit de repository-root met PHP op PATH:

```sh
php wordpress/tests/test-backyard.php
```

De tests gebruiken WordPress-testdoubles en controleren API-contracten, fouten,
URL/token-validatie, Bearer-header, shortcode, menuvolgorde, begrenzing,
capability/nonce-gating en escaping. Ze vervangen geen
test in een actieve WordPress-installatie. Alle plugin- en testbestanden zijn
met PHP 8.4 gelint en de contracttests zijn uitgevoerd. Activatie/menuweergave
in echte WordPress en bereikbaarheid vanaf de hosting zijn nog niet uitgevoerd.
