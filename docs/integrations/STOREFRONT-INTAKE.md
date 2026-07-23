# Storefront clinical intake

How a storefront feeds the provider review queue's medication columns
(Devin msg 2258: "They send to us", "everything from the preview in that exact
format").

## What it is

The provider review queue shows a column set the doctor portal does not itself
capture: the requested dose ladder, term, titrate plan, additional medications 2
to 4, on-GLP, standard Zofran, and an allergy flag with the patient's own words.
The requested medication is the offering already on the case. Everything else
comes from the storefront, as a `clinical_intake` block.

The storefront **pushes** this to the portal. The portal never calls out to the
storefront for it.

## The block

All fields optional. A field that is not sent shows as a dash in the queue; the
portal never invents a value.

```json
{
  "product": "Semaglutide",
  "dose": "L1 · 2.5 mg",
  "term": "3M",
  "plan": "Titration",
  "med2": "Zofran",
  "med3": "-",
  "med4": "-",
  "onGlp": "N",
  "zofran": "Y",
  "allergy": "N",
  "allergyDetail": null,
  "video": "Clear",
  "protocolVersion": "GLP-1 protocol v8",
  "findings": [["green", "Requested dose matches the current protocol step."]],
  "summary": [["Requested regimen: 3M term, dose L1 · 2.5 mg.", ["requestedTerm"]]],
  "sourceAnswers": { "requestedTerm": "3 months", "requestedDoseLevel": "Level 1 (2.5 mg)" }
}
```

`product`, `dose`, `term`, `plan`, `med2`..`med4`, `onGlp`, `zofran`, `allergy`,
`allergyDetail`, `video`, `protocolVersion` are the column and drawer values.
`findings`, `summary`, `sourceAnswers` feed the quick-review drawer and mirror
the shape in `docs/design-preview/index.html`'s `QUEUE` rows.

## How to send it

**On case creation** — include `clinical_intake` in the `POST /api/partner/cases`
body alongside `patient`, `offerings`, and the rest.

**After creation, or to update** — `POST /api/partner/cases/{id}/clinical`:

```
POST /api/partner/cases/{uuid}/clinical
Authorization: Bearer <partner token>
Content-Type: application/json

{ "clinical_intake": { "product": "Semaglutide", "dose": "L1 · 2.5 mg", ... } }
```

This **replaces** the block wholesale: the queue shows exactly what the
storefront last sent, not a merge of two snapshots. The endpoint is
partner-scoped, so a storefront can only write to its own cases.

## Where it renders

`PatientCase::queueClinical()` reads the block (falling back to the case's
offering for the requested medication, and to the patient for ID / sex / age /
BMI), and the clinician queue renders the columns from it. A case with no
`clinical_intake` shows the requested medication from its offering and a dash for
every storefront-only column.
