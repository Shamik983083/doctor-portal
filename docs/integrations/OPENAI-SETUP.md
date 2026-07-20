# AI assist: setting up the OpenAI account and the skill set

Audience: whoever sets up the new OpenAI account, plus the dev team.
Status: **wired.** Disabled by default and cannot send patient data until two flags are set.

---

## 1. Read this before creating the account

**A custom GPT built in the ChatGPT web interface cannot be called from an application.** There is
no API for it. If the skill set is built as a ChatGPT GPT it will work beautifully in the browser
and be unreachable from MEDAXIS.

What this app calls is a **stored prompt** on the platform side, plus **uploaded criteria** the
model retrieves from. That combination is the equivalent of a GPT, and it is callable.

---

## 2. Account setup, in order

1. **Create the account** and confirm it is the account the API key will belong to.
2. **Execute the BAA on that same account.** Every clinical note and patient message draft sends
   PHI. The BAA is what makes that lawful, and it must be on the account owning the key, not on a
   different one in the organisation. This is the gate, not a formality.
3. **Create the stored prompt.** This is the skill set: role, rules, tone, what it must never do.
   Note its id (`pmpt_...`) and version.
4. **Upload the criteria.** Create a vector store, upload the criteria documents, note the id
   (`vs_...`). This is what "feed it skills and information it calls from" means in practice: the
   model retrieves from these per request, so updating the guidance is uploading a new document,
   not a deploy.
5. **Give the dev team the ids.** Nothing else.

### What to upload, and what must never be uploaded

**Upload:** clinical criteria and protocols, dosing rules and ladders, message standards and tone,
worked examples of good and bad output, escalation rules.

**Never upload: patient data.** The vector store is persistent storage on OpenAI's side. Case
material travels in the request instead, which is covered by the BAA and sent with `store=false`
so nothing is retained. A criteria document that happens to contain a real patient example puts
that patient's data into permanent third-party storage. De-identify examples.

---

## 3. Configuration

```dotenv
AI_ASSIST_ADAPTER=openai
AI_ASSIST_ENABLED=true
AI_ASSIST_BAA_CONFIRMED=true      # only after the BAA is actually executed

OPENAI_API_KEY=sk-...
OPENAI_PROMPT_ID=pmpt_...         # the skill set
OPENAI_PROMPT_VERSION=1
OPENAI_VECTOR_STORE_IDS=vs_...    # comma-separated for more than one
OPENAI_STORE=false                # zero retention. Leave false.
```

**Both flags are required.** `AI_ASSIST_ENABLED` alone will not send anything.
`AI_ASSIST_BAA_CONFIRMED` is a human asserting the BAA is executed; nothing can verify it
automatically, which is exactly why it is a separate deliberate switch.

Behaviour of the two modes, which matters when debugging:

- **Stored prompt set** → sends `prompt: {id, version}`. Tools and instructions come from the
  OpenAI side. The in-app instruction sets become a fallback.
- **No stored prompt** → sends the in-app instruction set as `instructions`, and attaches the
  vector stores as a `file_search` tool. This is the mode the admin screen drives.

Vector stores are only attached in the second mode. A stored prompt carries its own tool
configuration and attaching here as well would override what was set there.

---

## 4. The in-app instruction library

`ai_instruction_sets` + `ai_instruction_examples`, one active set per context: clinical note,
patient message, storefront message. Admin-editable, versioned, with worked examples as separate
rows so a bad example can be removed without rewriting the set.

This is prompt guidance, **not** model training. Nothing here fine-tunes anything.

It is seeded with the behaviour the design preview already demonstrates, so the app starts where
the reviewed product is rather than empty.

---

## 5. Safety properties worth preserving

- **The model drafts. The provider edits, decides and signs.** Nothing is sent, posted or signed
  from model output. `draftNote` returns a draft and persists nothing.
- **There is always a grounded fallback.** `AiAssistService` composes from the case record
  *before* contacting any provider, so if the model is off, unreachable or returns nothing, the
  provider still gets a usable grounded draft instead of an empty box and an error.
- **The provider is told which they got.** The response carries `source: model|local` and a
  notice. Never present a locally-composed draft as a model one.
- **No active instruction set means compose locally.** Sending an unguided prompt about a patient
  is worse than not calling the model.
- **Patient context is an explicit whitelist**, not a model dump, so adding a column later cannot
  quietly start sending something new to a third party.
- **Never log the input or output.** Both are PHI. Errors log status codes only.

---

## 6. Verification status

> The PHP in this integration has **never been executed**. It was written on a machine with no
> PHP, Composer or MySQL. Static checks only: imports resolve, braces balance, and every model
> field referenced was read out of the migrations first. Run the suite before trusting it.
