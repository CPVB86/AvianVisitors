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

## Backyard-overzicht en Birds-review

### Samsung Frame-periode

Kies onder **Backyard → Instellingen → Samsung Frame** uit 1 uur, 12 uur,
24 uur (standaard), 7 dagen of alle waarnemingen en klik **Instellingen opslaan**.
WordPress verstuurt de keuze via Bearer-auth naar `/api/avian-collage/settings`.
De Pi bewaart deze persistent en leest haar lokaal bij iedere export. Het veld
toont de actuele Pi-instelling; een verbindingsfout wordt gemeld en bevestigt
geen opslag. Werk eerst Backyard op de Pi bij en herstart `backyard-api`.
De bestaande export- en uploadtimers hoeven niet te worden gewijzigd.

### AvianVisitors embed

Plaats `[backyard_avian]` op een pagina. Het iframe toont
`https://backyard.tail99c3bd.ts.net:8443/avian-visitors/` op schermbreedte
en schermhoogte, zonder interne scrollbalken. Gebruik een lege paginatemplate
zonder header, footer en paginamarges voor een schermvullende pagina zonder
extra paginascroll. De shortcode past de rest van het thema niet aan.
De bezoeker moet deze presentatie-URL kunnen bereiken; de bronserver moet
embedding door de WordPress-site toestaan. Er wordt geen API-token meegestuurd.

### Correcties achteraf

In Bevestigd en Afgewezen opent **Bewerken** een editor met de oorspronkelijke
BirdNET-soort, effectieve soort/identiteit, confidence, tijd, status en beveiligde
audio. Afgewezen records zijn ook via het statusfilter van Bevestigd bereikbaar.
Soortkeuze zoekt in `GET /api/avian-visitors/search`; een geselecteerde catalogusnaam
gaat als `scientific_name_override` naar het bestaande `/api/observations/{id}/correct`.
Soort behouden laat het veld weg; oorspronkelijke soort herstellen stuurt null.
Identity null verwijdert Otje. Backendvalidatie bepaalt welke doelsoort Otje toestaat.

Opslaan gebruikt de gelezen status/versie, een UUID per actie en de WordPress-user-ID
als actor. Bij onzekere netwerkuitkomst blijft exact dezelfde payload/UUID beschikbaar
voor retry. Bij 409 moet de beheerder expliciet de actuele waarneming ophalen.
Er wordt nooit stilzwijgend met een nieuwere versie overschreven. De API blijft
verantwoordelijk voor audit, evidencebehoud en alle statistische herberekeningen.

Na opslag wordt alleen de Birds-inhoud vernieuwd, niet de volledige pagina. Detail,
reviewtellers, `/api/avian-visitors/stats` (24-uursvenster plus API-all-time-totalen)
en `/api/avian-visitors/lifelist` worden opnieuw opgehaald. `backyard:observations-updated`
publiceert de gefilterde API-snapshot voor eventuele WP-statistiek/Atlascomponenten.
De plugin bewaart of berekent geen eigen tellingen. Een afzonderlijk open
AvianVisitors-venster op een andere origin houdt zijn bestaande eigen refreshflow;
WordPress kan dat venster niet rechtstreeks herladen. Dag/maand/jaar/soortgegevens
blijven afkomstig uit de bijgewerkte backendprojectie.

Vereist het definitieve correctiecontract inclusief `scientific_name_override`,
`review_version` en `effective_identity`. Geen backendwijzigingen in deze pluginstap.
Alleen technische smokechecks zijn uitgevoerd; volledige functionele test op hosting
moet nog plaatsvinden. De bestaande lokale productie-audiofix is niet gewijzigd.

Birds heeft tabs **Review | Otje | Bevestigd | Afgewezen**. Review en Otje gebruiken één set
knoppen voor geselecteerde rijen: Bevestigen, Afwijzen en (indien beschikbaar) Otje.
De kopcheckbox selecteert alle zichtbare rijen (maximaal 50); shift-click selecteert
een reeks. Otje is alleen beschikbaar wanneer alle geselecteerde rijen die capability
hebben. Elke actie controleert opnieuw de actuele API-status en de bestaande regels.
De feedback vermeldt apart hoeveel acties verwerkt, niet bevestigd of niet uitgevoerd
zijn. Langzame batches worden begrensd; controleer de vernieuwde lijst voordat je
resterende rijen opnieuw selecteert. Er is geen nieuwe bulk-API of lokale opslag.

Elke rij toont een Generator-afbeelding, met de bestaande lege `nest.webp` als
fallback (ook bij een downloadfout). De authenticated audioproxy is ongewijzigd.

**Bevestigd** is alleen-lezen en toont de status (automatisch geaccepteerd of
handmatig bevestigd), met filters voor het laatste uur, 12 uur, 24 uur, 7 dagen of
alle tijden. De periode gaat over de observation-timestamp, niet de reviewtijd.
Standaard: 24 uur, beide statussen. Iedere selectie toont maximaal de 50 nieuwste
resultaten; ‘Alle’ verwijdert de tijdsgrens, niet deze weergavelimiet.

**Afgewezen** gebruikt dezelfde tijd-/statusfilters en toont opgeslagen
`human_rejected` observations. Automatische `discarded` detecties worden door de
huidige backend niet opgeslagen en zijn niet op te vragen. De tab vermeldt dit;
automatische afwijzingen worden niet verzonnen of afgeleid uit confidence.

Gerichte checks: `php wordpress/tests/test-birds-bulk.php`,
`php wordpress/tests/test-otje-view.php` en `node wordpress/tests/test-review-selection.js`.

Het WordPress **Dashboard** (`wp-admin/index.php`) toont voor beheerders de widget
**Backyard**, zichtbaar/verbergbaar via **Scherminstellingen** en verplaatsbaar
zoals andere dashboardwidgets. De Birds-regel bevat een klikbare, ongecapte
teller van `pending_review` vogelwaarnemingen. **Backyard → Birds**
toont Review: maximaal 50 meest recente waarnemingen met datum/tijd (WordPress-
tijdzone), Nederlandse naam (fallback `common_name`), Latijnse naam, confidence,
aantal supports, beschikbare audio en Bevestigen/Afwijzen. Na een actie volgt
een redirect; lijst en teller worden opnieuw opgehaald, zonder caching/polling.
Afgehandelde rijen verdwijnen en de volgende waarnemingen komen in beeld.

Dit vereist de zelfstandige [Backyard-backend](https://github.com/CPVB86/Backyard)
met het observation-contract van commit `480c486` of nieuwer. De oude Python-
fundering in deze AvianVisitors-repository heeft die routes nog niet. Benodigd:

- `GET /api/observations/count?domain=bird&status=pending_review` → `{count: ...}`.
- `GET /api/observations?domain=bird&status=pending_review&limit=50` → lijst met
  `id`, `domain`, `status`, `timestamp`, `common_name_nl`, `common_name`,
  `scientific_name`, `confidence`, `supports`, `audio_available`.
- `GET /api/observations/{id}` voor domein-/statuscontrole vóór een actie/audio.
- `POST /api/observations/{id}/confirm` of `/reject`, JSON
  `{"expected_status":"pending_review"}`. De API bewaakt gelijktijdige wijzigingen
  (409); de plugin verandert geen policy. Bevestigen vereist beschikbare audio.
- `GET /api/observations/{id}/audio` voor WAV, inclusief byte ranges (206).

Alle API-verzoeken gebruiken dezelfde server-side Bearer-client. Beheer vereist
`manage_options`. Reviewacties gebruiken POST en een observation-specifieke nonce.
Alleen `bird` wordt geaccepteerd, ook bij handmatig gemanipuleerde requests.
Conflicten, ontbrekende audio en storingen krijgen vaste meldingen zonder API-
debugdata. De publieke logshortcode gebruikt uitsluitend geaccepteerde observations.

De audioplayer gebruikt uitsluitend een WordPress `admin-post.php`-URL met nonce.
De proxy controleert login/rechten, nonce, UUID en bird-domein; hij construeert het
API-pad zelf en volgt geen redirects. Een tijdelijk transportbestand (geen cache)
houdt audiobytes uit PHP-geheugen en wordt na afhandeling verwijderd, ook bij
fouten/disconnects. Maximaal 64 MiB en 30 seconden per audioaanvraag; contenttype,
WAV-signatuur (volledige response), grootte en Content-Range worden gecontroleerd.
Alleen gecontroleerde audioheaders worden teruggestuurd; geen token of privé-URL.
Er zijn geen publieke audio-proxyroutes. Bij verlopen nonce de Birds-pagina herladen.

Modules kunnen een `summary`-callback (tekst + admin-URL) en een `admin_page`-
callback aanbieden. De dashboardwidget hoeft daardoor niet te wijzigen bij toekomstige
modulestatussen. Birds heeft nu uitsluitend Review; Overzicht/Waarnemingen als
Birds-subonderdelen worden nog niet gebouwd.

## Birds-shortcode

### Menselijke identiteit Otje in Birds-review

Bij observations met backend-capability `otje` is naast Bevestigen/Afwijzen een expliciete 🐔 Otje-keuze
voorbereid. Deze bevestigt via dezelfde reviewactie met `identity_override: "otje"`;
de oorspronkelijke soort/evidence wordt nooit vervangen. Zonder deze capability
verschijnt de knop niet. Alleen de backend bepaalt de geschiktheid, zonder
soortenlijst in WordPress: zie [het API-contract](OTJE_API_CONTRACT.md).
De view **🐔 Potentiële Otjes** filtert uitsluitend op openstaande observations met
deze capability. De gelijknamige dashboardwidget toont het volledige actuele aantal
en linkt direct naar deze view. Na elke reviewactie wordt dezelfde view opnieuw geladen.
Vereist de bijgewerkte backend met `identity_override=otje` op `/api/observations/review`
en `/api/observations/count?review_only=true`; update de Pi en herstart `backyard-api`
vóór de WordPress-upload. Er is geen lokale identity-opslag of automatische mapping.
Er is geen automatische aliasing en de publieke shortcodepresentatie blijft ongewijzigd.

De Handleiding heeft ook zes veelgebruikte, kopieerbare beheercommando’s en
een standaard ingeklapt overzicht voor PowerShell en de Pi na SSH. API-tests
vragen het token verborgen in PowerShell op; het WordPress-token komt nooit
in deze commando’s terecht. Pi-paden/services volgen het aangeleverde spiekbriefje;
`operations.status` is niet in deze repository aanwezig. Git ophalen gebruikt
de ingestelde trackingbranch, zonder een branchnaam vast te leggen.

De Handleiding groepeert commando’s en shortcodes in kaarten. Shortcodes hebben
links een titel en korte uitleg, rechts de kopieerbare code. Uitklapbare parameters
staan op een aparte rij over beide kolommen, zodat de code op zijn plek blijft.
De parametertabel toont naam en kleinere toelichting; cursieve voorbeelden zijn
ook kopieerbaar. Meerdere shortcodeblokken worden door horizontale lijnen gescheiden.
Een groene gloed die uitdooft bevestigt het kopiëren; schermlezers ontvangen een
tekstbevestiging. Dit werkt ook met toetsenbordbediening. CSS wordt op
Birds, Handleiding en Instellingen geladen; kopieer-JavaScript alleen op Handleiding.
Instellingen groepeert URL, token en testknop in **Pi Connection**, met de algemene
opslagknop buiten de kaart. De test blijft de opgeslagen verbinding gebruiken.

Plaats in een WordPress-shortcodeblok:

```text
[backyard_birds_log]
[backyard_birds_log limit="50"]
[backyard_birds_log language="nl" limit="50"]
```

Standaard 25, minimaal 1 en maximaal 100 registraties. De tabel toont Tijd,
Soort, Latijnse naam en Confidence als percentage, nieuwste bovenaan. Tijden
volgen de WordPress-tijdzone. Er zijn nette lege- en foutmeldingen. De shortcode
leest `/api/observations` met `domain=bird` en uitsluitend `auto_accepted` of
`human_confirmed`. Omdat het endpoint één status per verzoek ondersteunt, worden
twee begrensde lijsten samengevoegd, gesorteerd en op de gezamenlijke limiet begrensd.
`pending_review`, `human_rejected`, andere statussen en legacy detections worden
nooit publiek getoond. Geen demo-data, nieuwe taxonomie/policy of databasekopie.
`language` ondersteunt NL (standaard), EN en DE, hoofdletterongevoelig, via de
bestaande API-velden. Ontbrekende vertalingen vallen terug op `common_name`.

Route: browser → WordPress/PHP → authenticated Backyard API. Bezoekers krijgen
alleen HTML en afbeeldingen via WordPress; geen token, privé-API-URL of JavaScript-fetch.
Observations worden niet gecachet; er is geen polling. Audio is alleen in de afgeschermde adminreview
beschikbaar.

### Vogelafbeeldingen uit Generator

De kolom **Vogel** vóór **Soort** toont een transparante thumbnail van 64 × 64 px.
De bestaande authenticated `GET /api/generator/bird/species?scientific_name=...`
levert de assets; voorkeur is `perched`, `flight`, `photo_cutout`, anders een lege cel.
WordPress start nooit generatie. Een Generator-storing laat de observation-tabel intact.
Per render is er maximaal één lookup per wetenschappelijke soortnaam. Resultaten
worden 5 minuten in transients bewaard, ontbrekende assets/fouten 1 minuut.
Wijziging van API-URL/token maakt de oude lookupcache ongeldig.

Een ondertekende publieke `admin-post.php?action=backyard_generator_asset`-URL
haalt alleen het geselecteerde Generator-asset server-side op via Bearer-auth.
De proxy accepteert uitsluitend PNG/WebP/JPEG-rasterdata (maximaal 8 MiB), volgt
geen redirects en geeft geen upstream URLs, tokens of foutdetails door. De
afbeeldingbytes blijven ongewijzigd, inclusief transparantie; browsercache 5 minuten.
Deze route vereist geen beheerderslogin, omdat de shortcode openbaar is.
De afzonderlijke productie-audioproxy is niet aangepast.

Test op Pi/WP: controleer met de bestaande authenticated Generator-lookup dat een
soort uit publieke observations een asset heeft. Upload de bijgewerkte plugin en
open `[backyard_birds_log]` uitgelogd. Controleer de nieuwe kolom, herhaalde soorten,
een soort zonder asset en NL/EN/DE. In browser Network hoort de afbeeldings-URL
uitsluitend naar WordPress te wijzen; wacht maximaal 5 minuten na een assetwijziging.
Controleer ook dat Birds-reviewaudio nog afspeelt en zoeken in audio blijft werken.

## Structuur

- `backyard.php`: bootstrap, lifecycle en laden van vier onafhankelijke modules.
- `includes/settings.php`: Settings API en URL-validatie.
- `includes/class-backyard-api-client.php`: gedeelde WordPress HTTP-client (JSON GET/POST en WAV-transport).
- `modules/<module>/<module>.php`: eigen modulecode en adminpaginabeschrijving.
- Birds biedt `backyard_birds_public_observations($limit = 25)` voor geaccepteerde
  observations (1–100), met een lijst of `WP_Error` als resultaat.
- `modules/birds/review.php`: reviewlijst, teller, acties en afgeschermde audioproxy.
- `admin/index.php`: native dashboardwidget met modulaire statusregels.
- `admin/pages.php`: standaard WordPress-adminpagina's; hoofdmenu met
  `dashicons-carrot`, zonder maatwerk voor submenu-iconen. Volgorde: Birds, Bats,
  Weather, Garden, Instellingen, Handleiding.
- `includes/shortcodes.php`: documentatieregister via filter
  `backyard_shortcode_docs`. Modules voegen entries toe met `module`, `shortcode`,
  `parameters` (naam → toelichting) en `description`. Een toekomstige module
  registreert de echte shortcode apart met `add_shortcode`; dit filter verzorgt
  uitsluitend de handleiding. Alle velden worden als tekst ge-escaped.

Geen publiek dashboard, synchronisatie, publieke audio, grafieken of
functionele Bats/Weather/Garden-module.

## Checks

Vanuit de repository-root met PHP op PATH:

```sh
php wordpress/tests/test-backyard.php
php wordpress/tests/test-review.php
php wordpress/tests/test-public-observations.php
php wordpress/tests/test-generator.php
php wordpress/tests/test-otje.php
```

Alleen de compacte view/dashboard-checks draaien (zonder andere suites):
`php wordpress/tests/test-otje-view.php`.

De tests gebruiken WordPress-testdoubles en controleren API-contracten, fouten,
URL/token-validatie, Bearer-header, shortcode, menuvolgorde, begrenzing,
capability/nonce-gating en escaping. Ze vervangen geen
test in een actieve WordPress-installatie. Alle plugin- en testbestanden zijn
met PHP 8.4 gelint en de contracttests zijn uitgevoerd. Activatie/menuweergave
in echte WordPress en bereikbaarheid vanaf de hosting zijn nog niet uitgevoerd.
De reviewtests omvatten teller/list-filtering, confirm/reject, vernieuwde data,
conflicten, domeincontrole, ontbrekende audio, rechten/nonces, veilige HTML,
WAV/range-transport, headerfouten, redirects en opruimen van tijdelijke bestanden.

## Bats en gedeelde Elementor-shortcodes

Bats biedt **Overzicht**, **Waarnemingen** (laatste 50 geaccepteerde), **Soorten**
en **Statistieken**, met lege statussen zolang er geen gegevens zijn. Birds-review
blijft ongewijzigd. De Dashboard-samenvatting bevat ook Bats.

Gebruik een Elementor Shortcode-widget voor tekst/HTML of de dynamische
Shortcode-tag waar het Elementor-veld deze ondersteunt. De plugin registreert
gewone WordPress-shortcodes en vereist geen Elementor-afhankelijkheid.

- `[backyard_data module="birds" type="last" field="name"]`
- `[bird_data type="last" field="perched" output="image"]`
- `[bat_data type="most" field="scientific_name" fallback="Nog geen waarnemingen"]`

| Parameter | Waarden / betekenis |
|---|---|
| module | `birds` (standaard), `bats`; aliassen vullen deze vast in |
| type | `last` (standaard), `most`, `first`, `rarest`, `random`, `species`, `stats` |
| field | Eén veld uit onderstaande lijsten; standaard `name` |
| period | `today`, `24h`, `7d`, `30d`, `all` (standaard) |
| rank | 1 t/m 10; standaard 1; niet gebruikt bij species/stats |
| species | Wetenschappelijke naam bij type species |
| output | `text` (standaard), `url`, `image`, `link` |
| fallback | Ontbrekende data/API-fout: deze tekst; standaard leeg |
| format | PHP-datumformaat; standaard Nederlandse numerieke notatie |

Soortvelden: `name` (NL, met bestaande naam als fallback), `scientific_name`,
`species_id` (stabiele soort/presentatie-identificatie), `count`, `perched`, `flying`,
`wikipedia_url`, `observations_url`, `first_seen`, `first_date`, `first_time`,
`last_seen`, `last_date`, `last_time`. De eerste/laatste waarneming en count gelden
binnen de geselecteerde periode. `species` kiest de gewone soort als meerdere
lokale identiteiten dezelfde wetenschappelijke naam hebben; anders de beschikbare
identiteit. Otje gebruikt de bestaande eigen naam en Generator-assets.

Statistiekvelden: `total_observations`, `unique_species`, `today_observations`,
`today_species`, `last_activity`, `new_species`, `active_days`. Statistieken gelden
voor de periode, behalve `today_*` (altijd vandaag). `new_species` telt biologische
soorten waarvan de eerste geaccepteerde waarneming ooit in de periode valt.
Otje voegt geen biologische soort toe. Dagen volgen de WordPress-tijdzone.

Datums: `d-m-Y H:i:s`, `d-m-Y` of `H:i:s`; `format="j F Y"` gebruikt de WordPress-sitetaal
voor maandnamen. Ranglijsten worden door de Pi bepaald; bij gelijke waarden geldt
wetenschappelijke naam/identiteit als vaste volgorde. Random blijft gelijk binnen
één paginarender. Alle velden delen één API-snapshot per module/periode/tijdzone;
er is geen blijvende statistiekcache. Correcties zijn zichtbaar bij de volgende
render. Een externe Elementor/WordPress-paginacache moet zo nodig worden geleegd.

Afbeeldingen: `output="image"` geeft een toegankelijk img-element zonder styling,
`output="url"` alleen de WordPress-proxy-URL. Ontbrekende poses leveren fallback;
er wordt nooit gegenereerd. Links ondersteunen `url` en `link` (soortnaam als label).
`observations_url` is de bestaande cataloguslink naar waarneming.nl, geen privé-API
of beheerlink. `wikipedia_url` gebruikt de bestaande NL-link, anders EN-link.

Voorbeelden staan met kopieerknoppen onder Handleiding → Elementor / gedeelde gegevens:

```text
[bird_data type="last" field="first_date"]
[bird_data type="last" field="last_time"]
[bird_data type="most" rank="2" field="count" period="7d"]
[backyard_data module="birds" type="species" species="Erithacus rubecula" field="count" period="30d"]
[backyard_data module="birds" type="stats" field="unique_species" period="all"]
```

Werk eerst Backyard op de Pi bij en herstart `backyard-api`. Upload vervolgens de
gewijzigde bestanden `backyard.php`, `admin/pages.php`, `modules/bats/bats.php` en
het nieuwe `includes/data-shortcodes.php` naar de bestaande pluginmap. Instellingen
blijven behouden. Geen databasekopie, detectorwijziging of generatie vanuit WordPress.
