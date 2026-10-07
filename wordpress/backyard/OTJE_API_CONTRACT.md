# Otje: vereiste uitbreiding van de bestaande Backyard-review

Status: WordPress is voorbereid; de geïnspecteerde Backyard API ondersteunt dit
nog niet. Geen identiteit wordt in WordPress gesimuleerd of opgeslagen. Tot de
API onderstaande capability levert, staat de Otje-knop uitgeschakeld.

## Bestaande structuur en minimale uitbreiding

`app/modules/observations/schemas.py`: voeg aan `ReviewInput` toe:

```python
identity_override: Literal["otje"] | None = None
```

Gebruik uitsluitend de bestaande `POST /api/observations/{id}/confirm`:

```json
{"expected_status":"pending_review","identity_override":"otje"}
```

Ook `review_recommended` blijft mogelijk volgens de bestaande reviewregels.
Normaal Bevestigen/Afwijzen blijft dezelfde payload zonder override versturen.
Reject met een niet-null override moet 422 geven, zonder mutatie.

`app/modules/observations/service.py`: schrijf binnen de bestaande transactie
voor confirm status `human_confirmed` én `record.review.identity_override`.
De bestaande JSON-kolom `Observation.review` volstaat; geen duplicaatdatabase
of nieuwe tabel nodig. Behoud `action`, `note`, `at` en `reason`, bijvoorbeeld:

```json
{
  "status": "human_confirmed",
  "scientific_name": "Gallus gallus",
  "common_name": "Red Junglefowl",
  "review": {
    "action": "confirm",
    "identity_override": "otje",
    "note": "",
    "at": "<UTC-reviewtijd>",
    "reason": "human_confirmation"
  }
}
```

De oorspronkelijke `scientific_name`, `common_name`, confidence, observatietijden,
candidates/raw evidence, policy en decision blijven volledig ongewijzigd.
De normale confirm-audiopromotie en statuswijziging blijven gelden.
Otje is een aparte menselijke identiteit, geen correctie van het soortveld.

Pas de bestaande idempotentiecheck aan: dezelfde status en note zijn niet genoeg;
de opgeslagen override moet ook exact overeenkomen (`null` voor oude reviews).
Een al definitieve review met een andere override moet 409 geven, zonder mutatie.
Behoud expected_status/conflictcontrole, authenticatie en audiovereisten.

## Capability en responses

Voeg via `serialize()` aan de bestaande reviewlijst en detailresponse toe:

```json
{"review_capabilities":{"identity_overrides":["otje"]}}
```

Lever dit alleen voor reviewbare bird-observations waarvoor de backend de
override accepteert. Conservatieve WP-keuzelijst (geen automatische mapping):
`Gallus gallus`, `Gallus gallus domesticus`, `Gallus domesticus`,
`Gallus sonneratii`, `Gallus lafayettii`, `Gallus varius`.
De backend moet dezelfde domein/soortvalidatie afdwingen; andere soorten: 422.
Deze lijst bepaalt alleen waar de reviewer de knop krijgt, nooit automatisch Otje.

Bewaar en retourneer `review.identity_override` via confirm, detail en alle
observation-lijsten. WordPress meldt uitsluitend succes na een response met het
juiste id, domain=bird, status=human_confirmed, review.action=confirm en
review.identity_override=otje. Een fout leidt nooit tot terugvallen op gewone confirm.

## Vereiste backendtests en acceptatie

- Confirm met otje bewaart status en override atomair en persistent (ook na heropenen DB).
- Vergelijk alle oorspronkelijke evidencevelden en candidates voor/na exact.
- Zelfde request herhalen is idempotent; afwijkende override of stale status geeft 409.
- Reject+override, onbekende identity, andere soort/domein geeft 422 zonder mutatie.
- Ontbrekende audio en ontbrekende/ongeldige Bearer blijven geweigerd.
- Detail, reviewlijst en publieke observations leveren de identity metadata terug.
- Bestaande confirm/reject zonder override en bestaande observations blijven werken.

Na backendimplementatie: review een Gallus-waarneming via 🐔 Otje; controleer de
database/detailresponse, de succesmelding en het verdwijnen uit lijst/teller.
Herhaal met gewone confirm om het onderscheid te controleren. WP-tests gebruiken
testdoubles en bewijzen geen backendpersistente opslag. Presentatie als Otje of
asset `otje`, automatische aliasing, confidence/geo-regels vallen buiten deze stap.
