# Questionnaire Sharing — Detailed Implementation Plan (v2)

> Updated to reflect: corrected Q&A submission model, `question_text` freeze, new offering-questionnaire API endpoint, Categories module, and `category_id` on offerings.

---

## Step 1 — Requirement Summary

Eight sequential implementation steps plus two additions:

| # | What |
|---|---|
| 1 | Response + answer storage tables (corrected schema) |
| 2 | `mode` and `step_number` on existing tables |
| 3 | Public form renderer (single-step) |
| 4 | Multi-step JS layer |
| 5 | Shareable link + embed snippet in admin UI |
| 6 | iFrame `postMessage` on completion |
| 7 | `offering_questionnaire` pivot + admin linkage |
| 8 | Case submission API accepts grouped Q&A pairs |
| 9 | `GET /api/partner/offerings/{uuid}/questionnaires` endpoint |
| 10 | Categories module (admin) + `category_id` on offerings |

---

## Step 2 — Existing Codebase Findings

| Area | Relevant Files |
|---|---|
| Routes (web) | `routes/web.php` — admin group `prefix('admin')->middleware(['auth','role:admin'])->name('admin.')`, partner group `prefix('partner')->...->name('partner.')` |
| Routes (api) | `routes/api.php` — partner API group `prefix('partner')->middleware(['auth:api','partner.auth'])` |
| Admin controllers | `Web/Admin/OfferingController.php`, `Web/Admin/QuestionnaireController.php`, `Web/Admin/QuestionController.php` |
| Partner web controllers | `Web/Partner/OfferingController.php` |
| Partner API controllers | `Api/Partner/OfferingController.php`, `Api/Partner/CaseController.php` |
| Models | `Questionnaire.php`, `QuestionnaireQuestion.php`, `Offering.php` |
| Views (admin offerings) | `admin/offerings/create.blade.php`, `show.blade.php`, `index.blade.php` |
| Views (partner offerings) | `partner/offerings/create.blade.php`, `show.blade.php`, `index.blade.php` |
| Layout | `layouts/admin.blade.php` (sidebar), `layouts/partner` (partner layout) |

**Key observations:**

- `Offering` model `$fillable`: no `category_id` — must be added
- Admin `OfferingController`: no `edit()` method — inline editing via `show()` + `update()`. `create()` passes `$partners` + `$usStates`. Need to add `$categories` to both `create()` and `show()`.
- Partner `OfferingController`: `create()` passes only `$usStates`. Need to add `$categories`.
- Partner offering view extends `layouts.partner` and uses `@push('scripts')` — different from admin which uses `@section('scripts')`. Do not mix these.
- `Api/Partner/OfferingController::show()` looks up by `uuid` not `id` — new questionnaire endpoint must do the same.
- `Questionnaire` model already has `uuid` (auto-generated on `creating`). Use this as the public URL token — no separate column needed.
- No `OfferingCategory` model, migration, controller, or views exist — all new.
- `layouts/admin.blade.php` sidebar has a Management section with Cases, Patients, Partners, Clinicians, Offerings, Questionnaires, Question Bank. Categories goes here, between Offerings and Questionnaires.

---

## Step 3 — Risk & Dependency Table

| File | How Affected | Risk If Wrong |
|---|---|---|
| `routes/web.php` | Public form route added outside all middleware; Categories admin routes added inside `admin.` group | Placing public route inside `auth` middleware locks out patients |
| `routes/api.php` | New `GET offerings/{id}/questionnaires` route inside existing partner middleware group | Wrong placement could expose to unauthenticated requests |
| `Offering.php` | Add `category_id` to `$fillable`; add `category()` relationship | Forgetting fillable silently drops category on save |
| `OfferingController` (admin) | `create()` and `show()` need `$categories`; `store()` and `update()` need `category_id` validation | Missing from `update()` would silently clear category on edit |
| `OfferingController` (partner web) | Same as admin — both `create()` and `store()` need `$categories` and validation | Partner offerings created without category if missed |
| `Api/Partner/OfferingController` | New `questionnaires()` method | Must scope to partner-owned offering only (`->where('uuid', $id)`) |
| `admin/offerings/create.blade.php` | Add category `<select>` in Basic Information section | Low risk, additive |
| `partner/offerings/create.blade.php` | Add category `<select>` in right-column Settings card | Uses `@push('scripts')` not `@section` — do not change script pattern |
| `layouts/admin.blade.php` | Add Categories nav link | Must use `request()->routeIs('admin.categories.*') ? 'active' : ''` pattern |
| `questionnaire_answers` table | Add `question_text` column | Without it, changing a question label loses the original clinical record |
| Case API `store()` | `questionnaire_responses` changes from token array to `[{questionnaire_id, answers[]}]` | Must remain `nullable` so existing integrations that send no responses still work |

---

## Step 4 — Full Implementation Plan

---

### STEP 1 — Response & Answer Tables (Corrected Schema)

**Depends on:** nothing.

#### 1a. Migration: `create_questionnaire_responses_table`

File: `database/migrations/2026_06_29_000001_create_questionnaire_responses_table.php`

```php
Schema::create('questionnaire_responses', function (Blueprint $table) {
    $table->id();
    $table->string('token', 64)->unique();           // UUID — used in API responses
    $table->foreignId('questionnaire_id')->constrained()->cascadeOnDelete();
    $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
    $table->string('external_patient_id')->nullable();
    $table->boolean('is_disqualified')->default(false);
    $table->string('disqualified_on')->nullable();   // question key that triggered it
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

#### 1b. Migration: `create_questionnaire_answers_table`

File: `database/migrations/2026_06_29_000002_create_questionnaire_answers_table.php`

```php
Schema::create('questionnaire_answers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('response_id')
          ->constrained('questionnaire_responses')->cascadeOnDelete();
    $table->foreignId('question_id')
          ->constrained('questionnaire_questions')->cascadeOnDelete();
    // Freeze question label at time of submission — clinical record must be immutable
    $table->string('question_text', 500);
    $table->text('answer')->nullable();              // plain text; JSON array for multi-select
    $table->boolean('is_disqualified')->default(false);
    $table->timestamp('created_at')->useCurrent();
});
```

> **Why `question_text`?** Question labels can be edited by admins after the fact. For clinical and audit purposes, the exact question shown to the patient at the time of submission must be preserved. The `question_id` FK is for linking and reporting; `question_text` is the frozen copy.

#### 1c. Model: `app/Models/QuestionnaireResponse.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QuestionnaireResponse extends Model
{
    protected $fillable = [
        'token', 'questionnaire_id', 'patient_id', 'partner_id',
        'case_id', 'external_patient_id',
        'is_disqualified', 'disqualified_on', 'completed_at',
    ];

    protected $casts = [
        'is_disqualified' => 'boolean',
        'completed_at'    => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->token = $m->token ?? (string) Str::uuid());
    }

    public function questionnaire() { return $this->belongsTo(Questionnaire::class); }
    public function patient()       { return $this->belongsTo(Patient::class); }
    public function partner()       { return $this->belongsTo(Partner::class); }
    public function case_()         { return $this->belongsTo(PatientCase::class, 'case_id'); }
    public function answers()       { return $this->hasMany(QuestionnaireAnswer::class, 'response_id'); }
}
```

#### 1d. Model: `app/Models/QuestionnaireAnswer.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionnaireAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'response_id', 'question_id', 'question_text', 'answer', 'is_disqualified',
    ];

    protected $casts = [
        'is_disqualified' => 'boolean',
        'created_at'      => 'datetime',
    ];

    public function response() { return $this->belongsTo(QuestionnaireResponse::class, 'response_id'); }
    public function question() { return $this->belongsTo(QuestionnaireQuestion::class, 'question_id'); }
}
```

#### 1e. Add `responses()` relationship to `Questionnaire` model

```php
public function responses() { return $this->hasMany(QuestionnaireResponse::class); }
```

**Run:** `php artisan migrate`

---

### STEP 2 — `mode` + `step_number` on Existing Tables

**Depends on:** Step 1.

#### 2a. Migration

File: `database/migrations/2026_06_29_000003_add_mode_and_step_to_questionnaires.php`

```php
Schema::table('questionnaires', function (Blueprint $table) {
    $table->enum('mode', ['single', 'multi'])->default('single')->after('is_active');
});

Schema::table('questionnaire_questions', function (Blueprint $table) {
    $table->unsignedTinyInteger('step_number')->default(1)->after('sort_order');
});
```

#### 2b. Update `Questionnaire` model

- Add `'mode'` to `$fillable`
- Update `questions()` relationship to: `->orderBy('step_number')->orderBy('sort_order')`

#### 2c. Update `QuestionnaireQuestion` model

- Add `'step_number'` to `$fillable`
- Add `'step_number' => 'integer'` to `$casts`

#### 2d. Update `QuestionnaireController`

In both `store()` and `update()`:
- Add to validation: `'mode' => 'nullable|in:single,multi'`
- Add to validation: `'questions.*.step_number' => 'nullable|integer|min:1'`
- Add `'mode' => $request->input('mode', 'single')` to the create/update data array

In `syncQuestions()`, add:
```php
'step_number' => (int) ($q['step_number'] ?? 1),
```

#### 2e. Update admin builder views

`admin/questionnaires/create.blade.php` and `edit.blade.php` — add Mode radio in the left meta column:

```html
<div class="mb-3">
  <label class="form-label small fw-semibold">Form Mode</label>
  <div class="d-flex gap-3">
    <div class="form-check">
      <input class="form-check-input" type="radio" name="mode" value="single"
             id="mode-single" {{ old('mode', $questionnaire->mode ?? 'single') === 'single' ? 'checked' : '' }}>
      <label class="form-check-label small" for="mode-single">Single Page</label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="mode" value="multi"
             id="mode-multi" {{ old('mode', $questionnaire->mode ?? 'single') === 'multi' ? 'checked' : '' }}>
      <label class="form-check-label small" for="mode-multi">Multi-Step</label>
    </div>
  </div>
</div>
```

`_builder_js.blade.php` — in `buildQuestionHtml()`, add Step input (hidden until multi mode):

```html
<div class="step-field d-none ms-2">
  <label class="form-label small mb-0 text-muted">Step</label>
  <input type="number" name="questions[${idx}][step_number]"
         class="form-control form-control-sm" style="width:65px"
         value="${data.step_number || 1}" min="1" max="20">
</div>
```

Add JS to show/hide step fields when mode radio changes:

```javascript
document.querySelectorAll('input[name=mode]').forEach(function(r) {
    r.addEventListener('change', function() {
        var isMulti = document.querySelector('input[name=mode]:checked')?.value === 'multi';
        document.querySelectorAll('.step-field').forEach(function(el) {
            el.classList.toggle('d-none', !isMulti);
        });
    });
});
```

**Run:** `php artisan migrate && php artisan view:clear`

---

### STEP 3 — Public Form Renderer (Single-Step)

**Depends on:** Steps 1 and 2.

#### 3a. New controller: `app/Http/Controllers/Web/Form/QuestionnaireFormController.php`

```php
<?php
namespace App\Http\Controllers\Web\Form;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use Illuminate\Http\Request;

class QuestionnaireFormController extends Controller
{
    private const OPTION_TYPES = ['select', 'multiselect', 'radio', 'checkbox'];

    public function show(string $uuid)
    {
        $questionnaire = Questionnaire::with(['questions' => fn($q) => $q->where('is_active', true)])
            ->where('uuid', $uuid)->where('is_active', true)->firstOrFail();

        $grouped    = $questionnaire->questions->groupBy('step_number')->sortKeys();
        $totalSteps = $grouped->count();

        return view('forms.questionnaire', compact('questionnaire', 'grouped', 'totalSteps'));
    }

    public function submit(Request $request, string $uuid)
    {
        $questionnaire = Questionnaire::with(['questions' => fn($q) => $q->where('is_active', true)])
            ->where('uuid', $uuid)->where('is_active', true)->firstOrFail();

        // Dynamic validation from question definitions
        $rules = [];
        foreach ($questionnaire->questions as $q) {
            $key  = 'answers.' . $q->id;
            $rule = $q->is_required ? 'required' : 'nullable';
            $rules[$key] = in_array($q->type, ['multiselect', 'checkbox'])
                ? "$rule|array"
                : "$rule|string|max:5000";
        }
        $request->validate($rules);

        $isDisqualified = false;
        $disqualifiedOn = null;
        $answersToSave  = [];

        foreach ($questionnaire->questions as $q) {
            $raw    = $request->input('answers.' . $q->id);
            $answer = is_array($raw) ? json_encode($raw) : ($raw ?? '');
            $disq   = false;

            if (in_array($q->type, self::OPTION_TYPES) && $q->options) {
                $selected = is_array($raw) ? $raw : [$raw];
                foreach ($q->options as $opt) {
                    if (!empty($opt['is_disqualify']) && in_array($opt['value'], $selected)) {
                        $isDisqualified = true;
                        $disqualifiedOn = $q->key ?: 'question_' . $q->id;
                        $disq = true;
                        break;
                    }
                }
            }

            $answersToSave[] = [
                'question_id'    => $q->id,
                'question_text'  => $q->question,   // frozen at submission time
                'answer'         => $answer,
                'is_disqualified'=> $disq,
            ];
        }

        $response = QuestionnaireResponse::create([
            'questionnaire_id'    => $questionnaire->id,
            'external_patient_id' => $request->input('external_id'),
            'is_disqualified'     => $isDisqualified,
            'disqualified_on'     => $disqualifiedOn,
            'completed_at'        => now(),
        ]);

        $response->answers()->createMany($answersToSave);

        $postMessagePayload = json_encode([
            'event'           => 'questionnaire_completed',
            'response_token'  => $response->token,
            'disqualified'    => $isDisqualified,
            'disqualified_on' => $disqualifiedOn,
        ]);

        return view('forms.result', [
            'status'  => $isDisqualified ? 'disqualified' : 'success',
            'message' => $isDisqualified
                ? 'Based on your answers, you do not currently qualify for this service.'
                : 'Thank you. Your responses have been submitted successfully.',
            'postMessagePayload' => $postMessagePayload,
        ]);
    }
}
```

#### 3b. Add public routes to `routes/web.php`

Add **before** all middleware groups (no auth):

```php
use App\Http\Controllers\Web\Form\QuestionnaireFormController;

// Public questionnaire form renderer — no auth required
Route::prefix('forms')->name('forms.')->group(function () {
    Route::get('/{uuid}',  [QuestionnaireFormController::class, 'show'])->name('show');
    Route::post('/{uuid}', [QuestionnaireFormController::class, 'submit'])->name('submit');
});
```

#### 3c. New view: `resources/views/forms/questionnaire.blade.php`

Standalone HTML (does **not** extend `layouts.admin`). Bootstrap 5 CDN. Renders all questions; multi-step JS controls visibility (Step 4).

Key structure:
```html
<!DOCTYPE html>
<html>
<head>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Bootstrap 5 CDN -->
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:680px">
  <div class="card shadow-sm">
    <div class="card-header">
      <!-- title, description, progress bar (if multi) -->
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('forms.submit', $questionnaire->uuid) }}">
        @csrf
        <input type="hidden" name="external_id" value="{{ request()->query('external_id') }}">

        @foreach($grouped as $stepNum => $stepQuestions)
        <div class="form-step" data-step="{{ $loop->index }}">
          @foreach($stepQuestions as $q)
            <!-- field rendered by @switch($q->type) -->
          @endforeach
        </div>
        @endforeach

        <!-- step nav + submit button -->
      </form>
    </div>
  </div>
</div>
</body>
</html>
```

Field rendering by type (inside `@switch($q->type)`):

| Type | Element |
|---|---|
| `input` | `<input type="text">` |
| `email` | `<input type="email">` |
| `textarea` | `<textarea>` |
| `date` | `<input type="date">` |
| `number` / `height` / `weight` / `bmi` | `<input type="number" step="any">` |
| `select` | `<select>` with options loop |
| `multiselect` | `<select multiple>` |
| `radio` | `<input type="radio">` loop |
| `checkbox` | `<input type="checkbox">` loop |
| `file` | `<input type="file">` |
| `hidden` | `<input type="hidden">` |

All `is_required` questions get the `required` attribute. `is_readonly` questions get `readonly`.

#### 3d. New view: `resources/views/forms/result.blade.php`

Standalone success/disqualified screen. Sends `postMessage` on load for iFrame integrations.

```html
<script>
try {
  window.parent.postMessage({{ Js::from($postMessagePayload) }}, '*');
} catch(e) {}
</script>
```

**Run:** `php artisan route:clear && php artisan view:clear`  
**Test:** Browse `/forms/{uuid}` — form renders; submit saves a `questionnaire_responses` row and `questionnaire_answers` rows.

---

### STEP 4 — Multi-Step JS Layer

**Depends on:** Step 3.

The form groups questions by `step_number` into `.form-step` divs (already done in Step 3 via `$grouped`). JavaScript in the same view controls display.

```javascript
(function () {
    var totalSteps = {{ $totalSteps }};
    if (totalSteps <= 1) return;

    var steps      = document.querySelectorAll('.form-step');
    var btnPrev    = document.getElementById('btn-prev');
    var btnNext    = document.getElementById('btn-next');
    var indicator  = document.getElementById('step-indicator');
    var progressBar = document.getElementById('progress-bar');
    var submitWrap = document.getElementById('submit-wrap');
    var current    = 0;

    function showStep(n) {
        steps.forEach(function(s, i) { s.style.display = i === n ? '' : 'none'; });
        indicator.textContent  = 'Step ' + (n + 1) + ' of ' + totalSteps;
        progressBar.style.width = Math.round(((n + 1) / totalSteps) * 100) + '%';
        btnPrev.style.display  = n === 0 ? 'none' : '';
        var last = n === totalSteps - 1;
        btnNext.style.display        = last ? 'none' : '';
        submitWrap.style.display     = last ? '' : 'none';
    }

    function validateStep(n) {
        var valid = true;
        steps[n].querySelectorAll('[required]').forEach(function(inp) {
            var ok = inp.type === 'radio'
                ? !!steps[n].querySelector('[name="' + inp.name + '"]:checked')
                : !!inp.value.trim();
            inp.classList.toggle('is-invalid', !ok);
            if (!ok) valid = false;
        });
        return valid;
    }

    btnNext.addEventListener('click', function() { if (validateStep(current)) showStep(++current); });
    btnPrev.addEventListener('click', function() { showStep(--current); });
    showStep(0);
})();
```

---

### STEP 5 — Shareable Link + Embed Snippet in Admin UI

**Depends on:** Step 3 routes exist.

Add a **Share & Embed** card to `resources/views/admin/questionnaires/show.blade.php`, below the Details card in the left column:

```html
<div class="card mt-4">
  <div class="card-header">
    <h6 class="mb-0"><i class="bi bi-share me-2"></i>Share & Embed</h6>
  </div>
  <div class="card-body">
    <p class="small text-muted mb-1 fw-semibold">Public Form URL</p>
    <div class="input-group input-group-sm mb-3">
      <input type="text" id="share-url" class="form-control font-monospace" readonly
             value="{{ url('/forms/' . $questionnaire->uuid) }}">
      <button class="btn btn-outline-secondary" type="button" onclick="copyField('share-url',this)">
        <i class="bi bi-clipboard"></i>
      </button>
    </div>

    <p class="small text-muted mb-1 fw-semibold">iFrame Embed Code</p>
    <div class="input-group input-group-sm">
      <textarea id="embed-code" class="form-control font-monospace" rows="3" readonly
                style="font-size:.7rem">{{ '<iframe src="' . url('/forms/' . $questionnaire->uuid) . '?partner_token=YOUR_TOKEN&external_id=PATIENT_ID" width="100%" height="640" frameborder="0"></iframe>' }}</textarea>
      <button class="btn btn-outline-secondary" type="button" onclick="copyField('embed-code',this)">
        <i class="bi bi-clipboard"></i>
      </button>
    </div>
  </div>
</div>
```

Add to `@section('scripts')`:

```javascript
function copyField(id, btn) {
    navigator.clipboard.writeText(document.getElementById(id).value).then(function() {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        setTimeout(function() { btn.innerHTML = orig; }, 1500);
    });
}
```

---

### STEP 6 — iFrame `postMessage` on Completion

Already implemented inside `forms/result.blade.php` (Step 3). No additional server work needed.

Document for partners in the partner show page API guide:

```javascript
// Partner's parent page listens:
window.addEventListener('message', function(e) {
    if (!e.data || e.data.event !== 'questionnaire_completed') return;
    if (e.data.disqualified) {
        // show "not eligible" messaging
    } else {
        // store e.data.response_token, unlock checkout, proceed to case submission
    }
});
```

---

### STEP 7 — `offering_questionnaire` Pivot + Admin Linkage

**Depends on:** Steps 1–3.

#### 7a. Migration: `create_offering_questionnaire_table`

File: `database/migrations/2026_06_29_000004_create_offering_questionnaire_table.php`

```php
Schema::create('offering_questionnaire', function (Blueprint $table) {
    $table->foreignId('offering_id')->constrained()->cascadeOnDelete();
    $table->foreignId('questionnaire_id')->constrained()->cascadeOnDelete();
    $table->boolean('is_required')->default(true);
    $table->unsignedTinyInteger('sort_order')->default(0);
    $table->primary(['offering_id', 'questionnaire_id']);
});
```

#### 7b. Update `Offering` model — add `questionnaires()` relationship

```php
public function questionnaires()
{
    return $this->belongsToMany(Questionnaire::class, 'offering_questionnaire')
                ->withPivot('is_required', 'sort_order')
                ->orderByPivot('sort_order');
}
```

#### 7c. Update `Questionnaire` model — add `offerings()` relationship

```php
public function offerings()
{
    return $this->belongsToMany(Offering::class, 'offering_questionnaire')
                ->withPivot('is_required', 'sort_order');
}
```

#### 7d. Update admin `OfferingController::show()` and `store()`

`show()` — pass questionnaires for edit form:
```php
$questionnaires = \App\Models\Questionnaire::where('is_active', true)->orderBy('name')->get(['id','name']);
$selectedIds    = $offering->questionnaires->pluck('id')->toArray();
return view('admin.offerings.show', compact('offering', 'usStates', 'questionnaires', 'selectedIds'));
```

`update()` — sync after saving:
```php
$offering->questionnaires()->sync($request->input('questionnaire_ids', []));
```

#### 7e. Add questionnaire checkboxes to `admin/offerings/show.blade.php`

```html
<div class="mb-3">
  <label class="form-label fw-semibold">Required Questionnaires</label>
  <div class="border rounded p-2" style="max-height:200px;overflow-y:auto">
    @foreach($questionnaires as $q)
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="questionnaire_ids[]"
             value="{{ $q->id }}" id="qlink{{ $q->id }}"
             {{ in_array($q->id, $selectedIds) ? 'checked' : '' }}>
      <label class="form-check-label small" for="qlink{{ $q->id }}">{{ $q->name }}</label>
    </div>
    @endforeach
  </div>
  <small class="text-muted">Partners must complete these forms before submitting a case with this offering.</small>
</div>
```

**Run:** `php artisan migrate`

---

### STEP 8 — Case API Accepts Grouped Q&A Pairs

**Depends on:** Steps 1 and 7.

This is the corrected submission model. Q&A pairs are grouped by questionnaire — not flat tokens.

#### 8a. Updated case submission payload

```json
POST /api/partner/cases
{
  "patient": { "first_name": "Jane", "last_name": "Doe", "email": "jane@example.com" },
  "external_id": "order-1001",
  "offerings": [{ "offering_id": "uuid-of-weightloss-offering", "quantity": 1 }],
  "questionnaire_responses": [
    {
      "questionnaire_id": 1,
      "answers": [
        { "question_id": 5,  "answer": "Jane" },
        { "question_id": 6,  "answer": "1990-04-15" },
        { "question_id": 7,  "answer": "CA" }
      ]
    },
    {
      "questionnaire_id": 2,
      "answers": [
        { "question_id": 10, "answer": "210" },
        { "question_id": 11, "answer": "165" },
        { "question_id": 13, "answer": "No" }
      ]
    }
  ]
}
```

> Grouping by `questionnaire_id` preserves the source form for each answer. The demographic answers never get mixed with the condition-specific answers in the DB.

#### 8b. Update `Api/Partner/CaseController::store()` — validation

```php
'questionnaire_responses'                   => 'nullable|array',
'questionnaire_responses.*.questionnaire_id'=> 'required|integer|exists:questionnaires,id',
'questionnaire_responses.*.answers'         => 'required|array',
'questionnaire_responses.*.answers.*.question_id' => 'required|integer|exists:questionnaire_questions,id',
'questionnaire_responses.*.answers.*.answer'      => 'nullable|string|max:5000',
```

#### 8c. Update `Api/Partner/CaseController::store()` — processing block

Add after offerings are attached:

```php
// Validate required questionnaires for attached offerings are present
if (!empty($data['questionnaire_responses'])) {
    $attachedOfferingIds = $case->caseOfferings()->pluck('offering_id');
    $requiredQIds = \DB::table('offering_questionnaire')
        ->whereIn('offering_id', $attachedOfferingIds)
        ->where('is_required', true)
        ->pluck('questionnaire_id');

    $providedQIds = collect($data['questionnaire_responses'])->pluck('questionnaire_id');
    $missing      = $requiredQIds->diff($providedQIds);

    if ($missing->isNotEmpty()) {
        $case->delete();
        return response()->json([
            'message'                    => 'Required questionnaires have not been completed.',
            'missing_questionnaire_ids'  => $missing->values(),
        ], 422);
    }

    // Persist each questionnaire response with its Q&A pairs
    foreach ($data['questionnaire_responses'] as $responseData) {
        $questionnaire = \App\Models\Questionnaire::find($responseData['questionnaire_id']);

        // Build a question ID → question text map for this questionnaire
        $questionMap = $questionnaire->questions->keyBy('id');

        $response = \App\Models\QuestionnaireResponse::create([
            'questionnaire_id'    => $questionnaire->id,
            'partner_id'          => $partner->id,
            'case_id'             => $case->id,
            'external_patient_id' => $data['patient']['external_id'] ?? null,
            'completed_at'        => now(),
        ]);

        $answers = [];
        foreach ($responseData['answers'] as $ans) {
            $q = $questionMap->get($ans['question_id']);
            if (!$q) continue;

            // Evaluate disqualify
            $disq = false;
            if (in_array($q->type, ['select','multiselect','radio','checkbox']) && $q->options) {
                $selected = is_array($ans['answer']) ? $ans['answer'] : [$ans['answer']];
                foreach ($q->options as $opt) {
                    if (!empty($opt['is_disqualify']) && in_array($opt['value'], $selected)) {
                        $disq = true;
                        break;
                    }
                }
            }

            $answers[] = [
                'question_id'    => $q->id,
                'question_text'  => $q->question,   // frozen at submission time
                'answer'         => is_array($ans['answer']) ? json_encode($ans['answer']) : $ans['answer'],
                'is_disqualified'=> $disq,
                'created_at'     => now(),
            ];
        }

        $response->answers()->createMany($answers);
    }
}
```

---

### STEP 9 — `GET /api/partner/offerings/{uuid}/questionnaires` Endpoint

**Depends on:** Steps 1, 7.

This is the endpoint a third-party system calls to fetch the questionnaire structure before rendering a form.

#### 9a. Add `questionnaires()` method to `Api/Partner/OfferingController.php`

```php
public function questionnaires(Request $request, string $id)
{
    $offering = $this->partner($request)->offerings()
        ->with(['questionnaires.questions' => fn($q) => $q->where('is_active', true)])
        ->where('uuid', $id)
        ->firstOrFail();

    $questionnaires = $offering->questionnaires
        ->sortBy('pivot.sort_order')
        ->values()
        ->map(fn($q) => [
            'id'         => $q->id,
            'uuid'       => $q->uuid,
            'name'       => $q->name,
            'mode'       => $q->mode,
            'is_required'=> (bool) $q->pivot->is_required,
            'sort_order' => $q->pivot->sort_order,
            'questions'  => $q->questions->map(fn($qu) => [
                'id'          => $qu->id,
                'key'         => $qu->key,
                'label'       => $qu->question,
                'type'        => $qu->type,
                'placeholder' => $qu->placeholder,
                'is_required' => $qu->is_required,
                'is_readonly' => $qu->is_readonly,
                'step'        => $qu->step_number,
                'options'     => $qu->options ?? [],
            ])->values(),
        ]);

    return response()->json([
        'offering'        => ['id' => $offering->uuid, 'name' => $offering->name],
        'questionnaires'  => $questionnaires,
    ]);
}
```

#### 9b. Add route to `routes/api.php`

Inside the existing `prefix('offerings')` group:

```php
Route::get('/{id}/questionnaires', [OfferingController::class, 'questionnaires']);
```

#### 9c. Sample response

```json
{
  "offering": { "id": "uuid-of-weightloss", "name": "Weight Loss Program" },
  "questionnaires": [
    {
      "id": 1, "uuid": "qqq-demo-uuid", "name": "Patient Demographics",
      "mode": "single", "is_required": true, "sort_order": 1,
      "questions": [
        { "id": 5,  "key": "first_name", "label": "First Name",   "type": "input",  "is_required": true,  "step": 1, "options": [] },
        { "id": 6,  "key": "dob",        "label": "Date of Birth","type": "date",   "is_required": true,  "step": 1, "options": [] },
        { "id": 7,  "key": "state",      "label": "State",        "type": "select", "is_required": true,  "step": 1,
          "options": [{"value":"CA","is_disqualify":false}, {"value":"NY","is_disqualify":false}] }
      ]
    },
    {
      "id": 2, "uuid": "qqq-wl-uuid", "name": "Weight Loss Intake",
      "mode": "multi", "is_required": true, "sort_order": 2,
      "questions": [
        { "id": 10, "key": "current_weight", "label": "Current weight (lbs)", "type": "number",   "is_required": true,  "step": 1, "options": [] },
        { "id": 11, "key": "goal_weight",    "label": "Goal weight (lbs)",    "type": "number",   "is_required": true,  "step": 1, "options": [] },
        { "id": 13, "key": "prior_surgery",  "label": "Prior bariatric surgery?","type": "radio", "is_required": true,  "step": 2,
          "options": [{"value":"Yes","is_disqualify":true},{"value":"No","is_disqualify":false}] }
      ]
    }
  ]
}
```

**Run:** `php artisan route:clear`

---

### STEP 10 — Categories Module + `category_id` on Offerings

**Depends on:** nothing (independent, but do before touching offering views).

#### 10a. Migration: `create_offering_categories_table`

File: `database/migrations/2026_06_29_000005_create_offering_categories_table.php`

```php
Schema::create('offering_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### 10b. Migration: `add_category_id_to_offerings_table`

File: `database/migrations/2026_06_29_000006_add_category_id_to_offerings_table.php`

```php
Schema::table('offerings', function (Blueprint $table) {
    $table->foreignId('category_id')
          ->nullable()
          ->after('partner_id')
          ->constrained('offering_categories')
          ->nullOnDelete();
});
```

#### 10c. Model: `app/Models/OfferingCategory.php`

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferingCategory extends Model
{
    protected $table = 'offering_categories';

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function offerings() { return $this->hasMany(Offering::class, 'category_id'); }
}
```

#### 10d. Update `Offering` model

Add to `$fillable`: `'category_id'`

Add relationship:
```php
public function category() { return $this->belongsTo(OfferingCategory::class, 'category_id'); }
```

#### 10e. New controller: `app/Http/Controllers/Web/Admin/OfferingCategoryController.php`

```php
<?php
namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfferingCategory;
use Illuminate\Http\Request;

class OfferingCategoryController extends Controller
{
    public function index()
    {
        $categories = OfferingCategory::withCount('offerings')->latest()->paginate(25);
        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100|unique:offering_categories,name',
            'description' => 'nullable|string',
        ]);

        OfferingCategory::create([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'is_active'   => true,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category "' . $request->input('name') . '" created.');
    }

    public function toggleStatus(int $id)
    {
        $cat = OfferingCategory::findOrFail($id);
        $cat->update(['is_active' => !$cat->is_active]);
        return back()->with('success', 'Category status updated.');
    }

    public function destroy(int $id)
    {
        $cat = OfferingCategory::findOrFail($id);
        if ($cat->offerings()->exists()) {
            return back()->with('error', 'Cannot delete a category that has offerings assigned to it.');
        }
        $cat->delete();
        return back()->with('success', 'Category deleted.');
    }
}
```

#### 10f. Add routes to `routes/web.php` — inside the `admin.` group

Add after the offerings prefix block:

```php
use App\Http\Controllers\Web\Admin\OfferingCategoryController as AdminCategoryController;

// Offering Categories
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/',             [AdminCategoryController::class, 'index'])->name('index');
    Route::post('/',            [AdminCategoryController::class, 'store'])->name('store');
    Route::patch('/{id}/toggle',[AdminCategoryController::class, 'toggleStatus'])->name('toggle');
    Route::delete('/{id}',      [AdminCategoryController::class, 'destroy'])->name('destroy');
});
```

#### 10g. New view: `resources/views/admin/categories/index.blade.php`

Two-column layout: left = create form, right = list. Follows the same card/table pattern as `admin/questionnaires/index.blade.php`.

```blade
@extends('layouts.admin')
@section('title', 'Offering Categories')
@section('page-title', 'Offering Categories')

@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

  {{-- Left: Create form --}}
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><h6 class="mb-0">Add New Category</h6></div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.categories.store') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label small fw-semibold">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" placeholder="e.g. Weight Loss" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3"
                      placeholder="Optional — describe when to use this category">{{ old('description') }}</textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-circle me-1"></i>Create Category
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- Right: List --}}
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">All Categories</h6>
        <span class="badge bg-primary">{{ $categories->total() }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th class="text-center">Offerings</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width:80px">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($categories as $cat)
            <tr>
              <td class="fw-semibold">{{ $cat->name }}</td>
              <td class="text-muted small">{{ $cat->description ? Str::limit($cat->description, 60) : '—' }}</td>
              <td class="text-center">
                <span class="badge bg-primary bg-opacity-75">{{ $cat->offerings_count }}</span>
              </td>
              <td class="text-center">
                <form method="POST" action="{{ route('admin.categories.toggle', $cat->id) }}">
                  @csrf @method('PATCH')
                  <button type="submit" class="badge border-0 {{ $cat->is_active ? 'bg-success' : 'bg-secondary' }}"
                          style="cursor:pointer">
                    {{ $cat->is_active ? 'Active' : 'Inactive' }}
                  </button>
                </form>
              </td>
              <td class="text-center">
                <form method="POST" action="{{ route('admin.categories.destroy', $cat->id) }}"
                      onsubmit="return confirm('Delete this category?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete">
                    <i class="bi bi-trash fs-5"></i>
                  </button>
                </form>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-5">
                <i class="bi bi-tag fs-2 d-block mb-2 opacity-25"></i>
                No categories yet. Add one using the form on the left.
              </td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($categories->hasPages())
      <div class="card-footer">{{ $categories->links() }}</div>
      @endif
    </div>
  </div>

</div>
@endsection
```

#### 10h. Add Categories link to `layouts/admin.blade.php`

Add between Offerings and Questionnaires:

```html
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"
       href="{{ route('admin.categories.index') }}">
        <i class="bi bi-tag"></i> Categories
    </a>
</li>
```

#### 10i. Update admin `OfferingController` — add `$categories` everywhere

`create()`:
```php
$categories = \App\Models\OfferingCategory::where('is_active', true)->orderBy('name')->get(['id','name']);
return view('admin.offerings.create', compact('partners', 'usStates', 'categories'));
```

`store()` — add to validation:
```php
'category_id' => 'nullable|exists:offering_categories,id',
```

`show()`:
```php
$categories = \App\Models\OfferingCategory::where('is_active', true)->orderBy('name')->get(['id','name']);
return view('admin.offerings.show', compact('offering', 'usStates', 'categories'));
```

`update()` — add to validation:
```php
'category_id' => 'nullable|exists:offering_categories,id',
```

#### 10j. Update partner `OfferingController` — add `$categories`

`create()`:
```php
$categories = \App\Models\OfferingCategory::where('is_active', true)->orderBy('name')->get(['id','name']);
return view('partner.offerings.create', compact('usStates', 'categories'));
```

`store()` — add to validation:
```php
'category_id' => 'nullable|exists:offering_categories,id',
```

`show()`:
```php
$categories = \App\Models\OfferingCategory::where('is_active', true)->orderBy('name')->get(['id','name']);
return view('partner.offerings.show', compact('offering', 'usStates', 'categories'));
```

`update()` — add to validation:
```php
'category_id' => 'nullable|exists:offering_categories,id',
```

#### 10k. Add category dropdown to `admin/offerings/create.blade.php`

In the Basic Information section, after the Name + Type row, add:

```html
<div class="row g-3 mb-3">
  <div class="col-md-6">
    <label class="form-label fw-semibold">Category</label>
    <select name="category_id" class="form-select">
      <option value="">No category</option>
      @foreach($categories as $cat)
        <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
          {{ $cat->name }}
        </option>
      @endforeach
    </select>
  </div>
</div>
```

#### 10l. Add category dropdown to `partner/offerings/create.blade.php`

In the right-column Settings card, above the Active toggle:

```html
<div class="mb-3">
  <label class="form-label fw-medium">Category</label>
  <select name="category_id" class="form-select">
    <option value="">No category</option>
    @foreach($categories as $cat)
      <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
        {{ $cat->name }}
      </option>
    @endforeach
  </select>
</div>
```

#### 10m. Add category to `admin/offerings/show.blade.php` summary table

```html
<tr><th class="text-muted">Category</th><td>{{ $offering->category?->name ?? '—' }}</td></tr>
```

Add category to the edit form in `show.blade.php` alongside the existing fields.

**Run:** `php artisan migrate && php artisan route:clear && php artisan view:clear`

---

## Step 5 — Style Checklist

- [x] All new controllers follow namespace conventions (`Web/Admin/`, `Web/Form/`, `Api/Partner/`)
- [x] Public `/forms/` route is **outside** all middleware groups — no `auth` gate
- [x] Admin categories routes inside `prefix('admin')->middleware(['auth','role:admin'])->name('admin.')` group
- [x] Categories view extends `layouts.admin`, uses `@section('content')` and `@section('scripts')`
- [x] Partner offering view uses `@push('scripts')` — not changed to `@section`
- [x] Admin offering view uses `@section('scripts')` — preserved
- [x] All new migrations add columns as `nullable()` where appropriate — no breaking defaults
- [x] `category_id` is `nullable` on offerings — existing offerings unaffected
- [x] `questionnaire_responses` in case API is `nullable` — existing partner integrations unbroken
- [x] `question_text` column added to `questionnaire_answers` — preserves clinical record
- [x] `Offering::$fillable` updated to include `category_id`
- [x] `OfferingCategory` model has `offerings()` relationship — `withCount()` works in controller
- [x] Sidebar Categories link uses `request()->routeIs('admin.categories.*') ? 'active' : ''` pattern
- [x] No Vite, no npm — Bootstrap 5 CDN only
- [x] JS is vanilla in `<script>` blocks — no jQuery
- [x] After all migrations: `php artisan migrate && php artisan route:clear && php artisan view:clear`

---

## Execution Order Summary

| # | What | New Files | Changed Files | Migration? |
|---|---|---|---|---|
| 1 | Response + answer tables + models | 2 models | `Questionnaire.php` | Yes (2) |
| 2 | mode + step_number | — | `Questionnaire.php`, `QuestionnaireQuestion.php`, `QuestionnaireController.php`, builder views | Yes (1) |
| 3 | Public form renderer | `Form/QuestionnaireFormController.php`, `forms/questionnaire.blade.php`, `forms/result.blade.php` | `routes/web.php` | No |
| 4 | Multi-step JS | — | `forms/questionnaire.blade.php` | No |
| 5 | Share + embed in admin | — | `admin/questionnaires/show.blade.php` | No |
| 6 | postMessage | — | already done in Step 3 | No |
| 7 | Offering pivot + admin linkage | — | `Offering.php`, `Questionnaire.php`, admin `OfferingController.php`, `admin/offerings/show.blade.php` | Yes (1) |
| 8 | Case API grouped Q&A | — | `Api/Partner/CaseController.php` | No |
| 9 | Offering questionnaires API endpoint | — | `Api/Partner/OfferingController.php`, `routes/api.php` | No |
| 10 | Categories module + category_id on offerings | `OfferingCategory.php`, `OfferingCategoryController.php`, `admin/categories/index.blade.php` | `Offering.php`, admin `OfferingController.php`, partner `OfferingController.php`, both create views, `admin/offerings/show.blade.php`, `layouts/admin.blade.php` | Yes (2) |
