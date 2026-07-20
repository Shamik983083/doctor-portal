<?php

/**
 * AI assist configuration.
 *
 * Mirrors the posture of config/dispatch.php deliberately: the model DRAFTS,
 * a human edits and commits, and a real provider call is **feature-flagged and
 * mutually gated** so it can never fire by accident. The default adapter is a
 * no-network mock that composes from the record, which is exactly what the
 * design preview does today.
 *
 * WHY THE SECOND GATE IS THE BAA AND NOT A SANDBOX FLAG:
 * every clinical-note and patient-message draft sends PHI to the provider. The
 * thing that makes that lawful is an executed Business Associate Agreement on
 * the SAME account the API key belongs to, plus a zero-retention endpoint. That
 * is a legal signoff, not an engineering one, so it gets its own explicit flag
 * that an operator has to set on purpose. `enabled` alone will not send PHI.
 *
 * SAFETY: with the shipped defaults, drafting runs through the mock adapter and
 * no request leaves the system.
 */
return [
    // Master switch. Off by default.
    'enabled' => env('AI_ASSIST_ENABLED', false),

    // Second gate. Even with `enabled=true`, a real (non-mock) adapter refuses
    // to send PHI until an operator confirms the BAA is executed on the account
    // owning the API key and that the endpoint is covered by it.
    'baa_confirmed' => env('AI_ASSIST_BAA_CONFIRMED', false),

    // Which provider to use: 'mock' (default, no network) | 'openai'.
    'adapter' => env('AI_ASSIST_ADAPTER', 'mock'),

    'openai' => [
        'api_key'  => env('OPENAI_API_KEY'),
        'base_uri' => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
        'model'    => env('OPENAI_MODEL', 'gpt-4.1'),

        /*
         * THE "GPT AGENT / SKILL SET WE SET UP" HOOKS IN HERE.
         *
         * If the skill set lives on the OpenAI platform as a stored prompt, put
         * its id here and the adapter sends `prompt: { id, version }` instead of
         * inline instructions, so the prompt is owned and versioned on OpenAI's
         * side and this app just references it.
         *
         * If it is left null, the adapter falls back to the instruction sets
         * stored in THIS app (see the ai_instruction_sets table and the admin
         * screen), which is the source the product can edit without a deploy.
         *
         * NOTE, and this matters: a "custom GPT" created inside the ChatGPT web
         * interface is NOT callable from an application. There is no API for it.
         * Only a stored prompt / assistant on the platform side can be pointed
         * at from here. If the skill set currently exists as a ChatGPT GPT, it
         * has to be recreated as a stored prompt before this id means anything.
         */
        'prompt_id'      => env('OPENAI_PROMPT_ID'),
        'prompt_version' => env('OPENAI_PROMPT_VERSION'),

        /*
         * THE UPLOADED CRITERIA THE MODEL CALLS FROM.
         *
         * Criteria documents (clinical protocols, dosing rules, message
         * standards) are uploaded once to a vector store on the OpenAI account,
         * and the model retrieves from them per request via the file_search
         * tool. That is what makes the skill set updatable by uploading a new
         * document rather than by editing a prompt or shipping a deploy.
         *
         * Comma-separated so more than one store can be attached, e.g. shared
         * clinical criteria plus a brand's own message standards.
         *
         * WHAT GOES IN HERE: criteria, protocols, standards, worked examples.
         * WHAT MUST NOT: patient data. The vector store is persistent storage on
         * the provider's side. Case material belongs in the request, which is
         * covered by the BAA and sent with store=false, not in a document that
         * sits there permanently.
         */
        'vector_store_ids' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPENAI_VECTOR_STORE_IDS', ''))
        ))),

        // Zero data retention. Required for the BAA-covered path.
        'store'   => env('OPENAI_STORE', false),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
    ],

    // Hard ceiling on a draft, so a runaway response cannot land in a chart note.
    'max_output_chars' => (int) env('AI_ASSIST_MAX_OUTPUT_CHARS', 6000),

    /*
     * The contexts a draft can be requested for. Each one maps to an instruction
     * set the admin owns. Kept here (not free-text) so a typo cannot silently
     * select "no instructions" and send an unguided prompt about a patient.
     */
    'contexts' => [
        'clinical_note'     => 'Clinical note on approval',
        'patient_message'   => 'Direct message to a patient',
        'storefront_message' => 'Message to a storefront partner',
    ],
];
