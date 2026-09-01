<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Questionnaire;
use Illuminate\Http\Request;

class QuestionnaireController extends Controller
{
    /** Same accessor the other partner API controllers use. */
    private function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    /**
     * WAS AN UNSCOPED LOOKUP BY UUID. Every other partner API controller scopes
     * to the calling partner; this one did not, and `questionnaires.partner_id`
     * exists and is nullable, so a questionnaire can be partner-owned. Partner A
     * holding Partner B's uuid could read B's entire intake form: every
     * question, slug, key, option set and disqualification rule.
     *
     * THE SCOPE BELOW IS DELIBERATELY THREE CLAUSES, NOT TWO, so that closing
     * the hole cannot break a live integration:
     *
     *   1. partner_id IS NULL   a platform-level template, shared by design.
     *   2. partner_id = caller  their own questionnaire.
     *   3. attached to an offering the caller owns. This is the clause that
     *      matters for not breaking anything. An admin can attach ANY
     *      questionnaire to ANY offering through offering_questionnaire,
     *      including one owned by a different partner, and that composition is
     *      intentional. Scoping on 1 and 2 alone would cut a partner off from a
     *      form their own offering requires.
     *
     * Nothing legitimately reachable is lost. The only way a partner discovers a
     * questionnaire uuid through this API is GET /offerings/{id}/questionnaires,
     * which is already scoped to their own offerings, so clause 3 covers every
     * uuid that endpoint can hand out.
     *
     * NOT scoped: `linkedQuestionnaire`. A linked questionnaire is an
     * admin-configured composition of one form into another, so a link crossing
     * partners is the feature working rather than a leak. Restricting it would
     * break multi-part intake.
     */
    public function show(Request $request, string $uuid)
    {
        $partnerId = $this->partner($request)->id;

        $questionnaire = Questionnaire::with([
            'questions'                      => fn($q) => $q->where('is_active', true)->orderBy('step_number')->orderBy('sort_order'),
            'linkedQuestionnaire.questions'  => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])
            ->where('uuid', $uuid)
            ->where('is_active', true)
            ->where(function ($q) use ($partnerId) {
                $q->whereNull('partner_id')
                  ->orWhere('partner_id', $partnerId)
                  // Phase 1a: check access via offering_partner pivot (replaces offerings.partner_id filter).
                  ->orWhereHas('offerings', fn($o) => $o->whereHas('partners', fn($p) => $p->where('partners.id', $partnerId)));
            })
            ->firstOrFail();

        $mapQuestion = fn($q, string $sourceUuid) => [
            'id'                   => $q->id,
            'slug'                 => $q->slug,
            'question'             => $q->question,
            'key'                  => $q->key,
            'type'                 => $q->type,
            'is_required'          => (bool) $q->is_required,
            'placeholder'          => $q->placeholder,
            'options'              => $q->options,
            'source_questionnaire' => $sourceUuid,
        ];

        $questions = collect();

        // Linked questionnaire questions appear first (Standard Intake 1 pattern)
        if ($questionnaire->linkedQuestionnaire) {
            $linked = $questionnaire->linkedQuestionnaire;
            foreach ($linked->questions as $q) {
                $questions->push($mapQuestion($q, $linked->uuid));
            }
        }

        foreach ($questionnaire->questions as $q) {
            $questions->push($mapQuestion($q, $questionnaire->uuid));
        }

        return response()->json([
            'uuid'                 => $questionnaire->uuid,
            'name'                 => $questionnaire->name,
            'description'          => $questionnaire->description,
            'linked_questionnaire' => $questionnaire->linkedQuestionnaire
                ? ['uuid' => $questionnaire->linkedQuestionnaire->uuid, 'name' => $questionnaire->linkedQuestionnaire->name]
                : null,
            'questions'            => $questions->values(),
        ]);
    }
}
