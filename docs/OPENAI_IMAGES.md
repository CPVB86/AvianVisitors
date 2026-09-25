# Vogel Bezoeken: OpenAI-afbeeldingen

BirdNET blijft voorlopig gesimuleerd. Beeldgeneratie is een afzonderlijke,
expliciete handeling; de website maakt geen betaalde verzoeken bij het openen.
De bestaande Gemini-generator en Raspberry Pi-installatie blijven beschikbaar.

## Eenmalig instellen

Gebruik Python 3.10 of nieuwer vanuit de root van deze repository:

```bash
python -m pip install -r demo/requirements-images.txt
```

Maak een kopie van `.env.example` met de naam `.env` (als die nog niet bestaat).
Vul uitsluitend in je lokale bestand de waarde van `OPENAI_API_KEY` in.
De sleutel komt niet in Git, de browser of de argumenten van een commando.
Er zijn geen nieuwe pakketten nodig voor de gewone simulatie; alleen de
generator heeft Pillow nodig. Een al ingestelde omgevingsvariabele heeft
voorrang op `.env`.

Standaard gebruikt deze implementatie `gpt-image-2.5-flare`, kwaliteit `medium`
en één transparante PNG van 1024×1024 per pose. Model en kwaliteit zijn
instelbaar via `.env`. De actuele contracten voor genereren, referentiebeelden
en transparantie staan in de [officiële OpenAI-beeldgeneratiedocumentatie](https://developers.openai.com/api/docs/guides/image-generation).
Je API-project moet toegang en voldoende API-tegoed hebben; de code kan dat
pas bij een echte aanvraag bevestigen.

## Test zonder kosten

```bash
python demo/generate.py --sci "Turdus merula" --com "Merel" --dry-run
```

Dit toont model, prompt en referentierollen en verstuurt niets. De prompt
hergebruikt de bestaande Japanse houtsnedestijl en soortnotities. Een bestaande
illustratie verankert de stijl. Je kunt een eigen anatomiefoto toevoegen met
`--reference pad/naar/foto.jpg`, een negatieve referentie met
`--anti-reference` en een stijlvoorbeeld met `--style-reference`.
Deze bestanden worden bij een echte aanvraag naar OpenAI verstuurd.

## Eén echte proefafbeelding

```bash
python demo/generate.py --sci "Turdus merula" --com "Merel"
```

Dit is één betaalde aanvraag voor de zittende pose. Gebruik `--pose flight`
voor één vliegende pose of `--pose both` voor twee aanvragen. Bestaande lokale
beelden worden overgeslagen; alleen `--force` vraagt opnieuw generatie aan.
Er zijn geen automatische retries: bij een time-out kan het verzoek al
verwerkt zijn. Controleer je API-verbruik voordat je het opnieuw probeert.

Resultaten staan lokaal onder `.avian/`:

- `illustrations/raw/`: origineel API-resultaat, ook voor visuele beoordeling;
- `illustrations/<soort>.png`: transparant uitgesneden beeld met marge;
- `frontend/dims.json` en `frontend/masks.json`: lokale silhouetgegevens.

De generator weigert een volledig ondoorzichtige of lege afbeelding, bewaart
het origineel ter beoordeling en overschrijft dan geen bestaande uitsnede.
Controleer zelf ook de soortkenmerken, poten, vleugels en randjes: automatisch
slagen van de alfa-controle betekent niet dat de anatomie correct is.

## In de collage testen

Voor een soort die al in de demo staat, bijvoorbeeld een Huismus, heeft het
lokale beeld voorrang op de meegeleverde illustratie. Herlaad de browser na
generatie. De meegeleverde afbeeldingen blijven ongewijzigd.

Voor de nieuwe Merel maak je `.avian/species.json` met:

```json
[{"sci":"Turdus merula","com":"Merel","count":14}]
```

Stop de server en start met dit lokale detectiebestand:

```bash
python demo/server.py --fixture .avian/species.json
```

Open http://127.0.0.1:8000/. De zittende pose is voldoende; zonder vliegende
pose toont de demo hetzelfde beeld. Voor meerdere vogels kun je een kopie van
`demo/species.json` in `.avian/species.json` zetten en de Merel toevoegen.
De standaarddemo herstel je met `python demo/server.py`.

Alle gegenereerde bestanden en de sleutel blijven buiten Git. Voor een andere
computer moeten de lokale afbeeldingen/maskers bewust worden overgezet of
opnieuw gegenereerd; alleen de code en de instructies worden gepusht.

## Tests en huidige status

```bash
python -m unittest discover -s tests -p test_openai_images.py -v
python -m unittest discover -s tests -p test_demo.py -v
```

De negen OpenAI-tests gebruiken nagebootste netwerkantwoorden en tijdelijke
afbeeldingen; ze kosten niets. Ze testen onder meer referentie-upload, PNG-
validatie, foutafhandeling zonder sleuteluitvoer, lokale maskers en behoud van
bestaande beelden. De zeven demotests blijven geslaagd. De eerste echte API-aanroep is op 25 september 2026 geslaagd: één Merel,
zittende pose, medium. PNG, transparantie, maskers en laden in de demo werken.
Visuele inspectie toont een zachte gloed rond de vogel; het resultaat is een
proefbeeld en nog geen definitief goedgekeurde illustratie.

Deze route is bedoeld voor de lokale app. De bestaande generatieknop op een
Raspberry Pi gebruikt nog Gemini; die migratie is niet stilzwijgend uitgevoerd.
