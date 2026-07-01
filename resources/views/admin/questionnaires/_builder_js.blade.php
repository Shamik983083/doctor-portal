<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
(function () {
    var qIdx  = 0;
    var existingQuestions = @json($existingQuestions);

    var FIELD_TYPES = [
        { id: 'hidden',      label: 'Hidden' },
        { id: 'input',       label: 'Input' },
        { id: 'email',       label: 'Email' },
        { id: 'textarea',    label: 'Textarea' },
        { id: 'date',        label: 'Date' },
        { id: 'select',      label: 'Select' },
        { id: 'multiselect', label: 'Multi Select' },
        { id: 'radio',       label: 'Radio' },
        { id: 'checkbox',    label: 'Checkbox' },
        { id: 'file',        label: 'File' },
        { id: 'number',      label: 'Number' },
        { id: 'height',      label: 'Height' },
        { id: 'weight',      label: 'Weight' },
        { id: 'bmi',         label: 'BMI' },
    ];

    var OPTION_TYPES      = ['select', 'multiselect', 'radio', 'checkbox'];
    var PLACEHOLDER_TYPES = ['input', 'email', 'textarea', 'number'];

    var OPERATORS = [
        { id: 'equals',       label: 'equals' },
        { id: 'not_equals',   label: 'does not equal' },
        { id: 'contains',     label: 'contains' },
        { id: 'is_answered',  label: 'is answered' },
    ];

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function isMultiMode() {
        var r = document.querySelector('input[name=mode]:checked');
        return r && r.value === 'multi';
    }

    /* ── Option row ─────────────────────────────────────────────── */
    function optionRowHtml(qIdx, oIdx, opt) {
        var val  = (opt && typeof opt === 'object') ? (opt.value || '') : (opt || '');
        var disq = (opt && opt.is_disqualify) ? 'checked' : '';
        var disqId = 'disq_' + qIdx + '_' + oIdx;
        return '<div class="option-row d-flex align-items-center gap-2 mb-2" data-oidx="' + oIdx + '">'
            + '<i class="bi bi-grip-vertical text-muted" style="cursor:grab"></i>'
            + '<input type="text" name="questions[' + qIdx + '][options][' + oIdx + '][value]"'
            +        ' class="form-control form-control-sm" placeholder="Option text" value="' + esc(val) + '">'
            + '<div class="form-check form-switch d-flex align-items-center gap-1 text-nowrap ms-1">'
            +   '<input class="form-check-input mt-0" type="checkbox"'
            +          ' name="questions[' + qIdx + '][options][' + oIdx + '][is_disqualify]"'
            +          ' value="1" id="' + disqId + '" ' + disq + '>'
            +   '<label class="form-check-label small text-danger mb-0" for="' + disqId + '">Disqualify</label>'
            + '</div>'
            + '<button type="button" class="btn btn-sm btn-outline-danger remove-option px-2">'
            +   '<i class="bi bi-x"></i>'
            + '</button>'
            + '</div>';
    }

    /* ── Condition section HTML ──────────────────────────────────── */
    function conditionHtml(idx, dep) {
        var hasCondition  = dep && dep.idx !== null && dep.idx !== undefined;
        var operator      = (dep && dep.operator) ? dep.operator : 'equals';
        var value         = (dep && dep.value)    ? dep.value    : '';
        var hideValue     = operator === 'is_answered' ? ' d-none' : '';

        var operatorOpts = '';
        OPERATORS.forEach(function (op) {
            operatorOpts += '<option value="' + op.id + '"' + (operator === op.id ? ' selected' : '') + '>'
                          + op.label + '</option>';
        });

        return '<div class="col-12 condition-section border-top pt-3 mt-1">'
            + '<div class="d-flex align-items-center gap-2 mb-2">'
            +   '<div class="form-check mb-0">'
            +     '<input type="checkbox" class="form-check-input condition-toggle" id="cond_' + idx + '"'
            +            (hasCondition ? ' checked' : '') + '>'
            +     '<label class="form-check-label small fw-semibold text-secondary" for="cond_' + idx + '">'
            +       '<i class="bi bi-arrow-right-circle me-1"></i>Show only if…'
            +     '</label>'
            +   '</div>'
            + '</div>'
            + '<div class="condition-fields d-flex flex-wrap gap-2 align-items-center' + (hasCondition ? '' : ' d-none') + '">'
            +   '<select class="form-select form-select-sm cond-question-select" style="max-width:220px">'
            +     '<option value="">— select question —</option>'
            +   '</select>'
            +   '<select class="form-select form-select-sm cond-operator-select" style="max-width:160px">'
            +     operatorOpts
            +   '</select>'
            +   '<input type="text" class="form-control form-control-sm cond-value-input' + hideValue + '"'
            +          ' placeholder="value…" value="' + esc(value) + '" style="max-width:160px">'
            +   '<input type="hidden" name="questions[' + idx + '][depends_on_idx]"      class="cond-hidden-idx"     value="' + (hasCondition && dep.idx !== null ? dep.idx : '') + '">'
            +   '<input type="hidden" name="questions[' + idx + '][depends_on_operator]" class="cond-hidden-operator" value="' + esc(operator) + '">'
            +   '<input type="hidden" name="questions[' + idx + '][depends_on_value]"    class="cond-hidden-value"    value="' + esc(value) + '">'
            + '</div>'
            + '</div>';
    }

    /* ── Question card HTML ──────────────────────────────────────── */
    function buildQuestionHtml(idx, data) {
        var d           = data || {};
        var type        = d.type || 'input';
        var question    = esc(d.question || '');
        var key         = esc(d.key || '');
        var placeholder = esc(d.placeholder || '');
        var stepNum     = d.step_number || 1;
        var isRequired  = d.is_required ? 'checked' : '';
        var isReadonly  = d.is_readonly ? 'checked' : '';
        var showOptions = OPTION_TYPES.indexOf(type) !== -1;
        var showPh      = PLACEHOLDER_TYPES.indexOf(type) !== -1;
        var multiHidden = isMultiMode() ? '' : ' d-none';

        var dep = (d.depends_on_idx !== undefined && d.depends_on_idx !== null) ? {
            idx:      d.depends_on_idx,
            operator: d.depends_on_operator || 'equals',
            value:    d.depends_on_value    || '',
        } : null;

        /* type <select> */
        var typeOpts = '';
        FIELD_TYPES.forEach(function (ft) {
            typeOpts += '<option value="' + ft.id + '"' + (type === ft.id ? ' selected' : '') + '>'
                      + ft.label + '</option>';
        });

        /* pre-populate options */
        var optionsHtml = '';
        if (showOptions && d.options && d.options.length) {
            d.options.forEach(function (opt, oIdx) {
                optionsHtml += optionRowHtml(idx, oIdx, opt);
            });
        }

        return '<div class="question-card card mb-3 border" data-idx="' + idx + '">'

            /* header */
            + '<div class="card-header d-flex justify-content-between align-items-center py-2 bg-light">'
            +   '<div class="d-flex align-items-center gap-3">'
            +     '<i class="bi bi-grip-vertical drag-handle text-muted" style="cursor:grab;font-size:1.1rem" title="Drag to reorder"></i>'
            +     '<span class="fw-semibold small text-secondary question-label">Question ' + (idx + 1) + '</span>'
            +     '<div class="step-field d-flex align-items-center gap-1' + multiHidden + '">'
            +       '<label class="form-label small mb-0 text-muted">Step</label>'
            +       '<input type="number" name="questions[' + idx + '][step_number]"'
            +              ' class="form-control form-control-sm step-input" style="width:65px"'
            +              ' value="' + stepNum + '" min="1" max="20">'
            +     '</div>'
            +   '</div>'
            +   '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-question">'
            +     '<i class="bi bi-trash"></i>'
            +   '</button>'
            + '</div>'

            /* body */
            + '<div class="card-body">'
            +   '<div class="row g-3">'

            /* Question label */
            +     '<div class="col-md-8">'
            +       '<label class="form-label small fw-semibold mb-1">Question / Label <span class="text-danger">*</span></label>'
            +       '<input type="text" name="questions[' + idx + '][question]"'
            +              ' class="form-control form-control-sm" value="' + question + '"'
            +              ' placeholder="e.g. What is your age?" required>'
            +     '</div>'

            /* Key */
            +     '<div class="col-md-4">'
            +       '<label class="form-label small fw-semibold mb-1">Field Key'
            +         '<span class="text-muted fw-normal ms-1" style="font-size:.7rem">(optional)</span>'
            +       '</label>'
            +       '<input type="text" name="questions[' + idx + '][key]"'
            +              ' class="form-control form-control-sm" value="' + key + '"'
            +              ' placeholder="e.g. patient_age">'
            +     '</div>'

            /* Field type */
            +     '<div class="col-md-4">'
            +       '<label class="form-label small fw-semibold mb-1">Field Type <span class="text-danger">*</span></label>'
            +       '<select name="questions[' + idx + '][type]" class="form-select form-select-sm question-type">'
            +         typeOpts
            +       '</select>'
            +     '</div>'

            /* Placeholder */
            +     '<div class="col-md-4 placeholder-field' + (showPh ? '' : ' d-none') + '">'
            +       '<label class="form-label small fw-semibold mb-1">Placeholder</label>'
            +       '<input type="text" name="questions[' + idx + '][placeholder]"'
            +              ' class="form-control form-control-sm" value="' + placeholder + '"'
            +              ' placeholder="Enter placeholder text...">'
            +     '</div>'

            /* Required + Readonly */
            +     '<div class="col-md-4 d-flex align-items-end gap-4 pb-1">'
            +       '<div class="form-check">'
            +         '<input type="checkbox" name="questions[' + idx + '][is_required]" value="1"'
            +                ' class="form-check-input" id="req_' + idx + '" ' + isRequired + '>'
            +         '<label class="form-check-label small" for="req_' + idx + '">Required</label>'
            +       '</div>'
            +       '<div class="form-check">'
            +         '<input type="checkbox" name="questions[' + idx + '][is_readonly]" value="1"'
            +                ' class="form-check-input" id="ro_' + idx + '" ' + isReadonly + '>'
            +         '<label class="form-check-label small" for="ro_' + idx + '">Read-only</label>'
            +       '</div>'
            +     '</div>'

            /* Options section */
            +     '<div class="col-12 options-section' + (showOptions ? '' : ' d-none') + '">'
            +       '<label class="form-label small fw-semibold mb-2">'
            +         'Options <span class="text-danger">*</span>'
            +         '<span class="text-muted fw-normal ms-2" style="font-size:.7rem">'
            +           'Toggle <strong>Disqualify</strong> to exclude the patient if they pick that option.'
            +         '</span>'
            +       '</label>'
            +       '<div class="options-list">' + optionsHtml + '</div>'
            +       '<button type="button" class="btn btn-sm btn-outline-secondary add-option mt-1">'
            +         '<i class="bi bi-plus me-1"></i>Add Option'
            +       '</button>'
            +     '</div>'

            /* Condition section */
            +     conditionHtml(idx, dep)

            +   '</div>'  /* /row */
            + '</div>'    /* /card-body */
            + '</div>';   /* /card */
    }

    /* ── Attach remove to one option row ────────────────────────── */
    function attachOptionRemove(row) {
        row.querySelector('.remove-option').addEventListener('click', function () {
            row.remove();
        });
    }

    /* ── Build <option> list for condition question dropdown ────── */
    function buildConditionOptions(selfIdx) {
        var html = '<option value="">— select question —</option>';
        document.querySelectorAll('.question-card').forEach(function (card) {
            var cardIdx = parseInt(card.dataset.idx);
            if (cardIdx === selfIdx) return;
            var label = card.querySelector('.question-label').textContent;
            var qText = card.querySelector('input[name="questions[' + cardIdx + '][question]"]');
            var preview = qText ? (qText.value.trim().substring(0, 40) || label) : label;
            html += '<option value="' + cardIdx + '">' + esc(preview) + '</option>';
        });
        return html;
    }

    /* ── Rebuild all condition dropdowns (after add/remove/reorder) */
    function refreshAllConditionDropdowns() {
        document.querySelectorAll('.question-card').forEach(function (card) {
            var selfIdx  = parseInt(card.dataset.idx);
            var sel      = card.querySelector('.cond-question-select');
            var hidden   = card.querySelector('.cond-hidden-idx');
            if (!sel) return;

            var currentVal = hidden ? hidden.value : '';
            sel.innerHTML  = buildConditionOptions(selfIdx);

            // Re-select previously chosen question if it still exists
            if (currentVal !== '') {
                var opt = sel.querySelector('option[value="' + currentVal + '"]');
                if (opt) {
                    opt.selected = true;
                } else {
                    // Referenced card no longer exists — clear condition
                    if (hidden) hidden.value = '';
                }
            }
        });
    }

    /* ── Initialise a newly-added question card ──────────────────── */
    function initCard(card) {
        var idx = card.dataset.idx;
        var nextOptIdx = card.querySelectorAll('.option-row').length;

        /* type change → toggle placeholder + options sections */
        card.querySelector('.question-type').addEventListener('change', function () {
            var t          = this.value;
            var phField    = card.querySelector('.placeholder-field');
            var optSection = card.querySelector('.options-section');
            phField.classList.toggle('d-none', PLACEHOLDER_TYPES.indexOf(t) === -1);
            optSection.classList.toggle('d-none', OPTION_TYPES.indexOf(t) === -1);
        });

        /* remove question */
        card.querySelector('.remove-question').addEventListener('click', function () {
            card.remove();
            updateNumbers();
            syncEmptyMsg();
            refreshAllConditionDropdowns();
        });

        /* add option */
        card.querySelector('.add-option').addEventListener('click', function () {
            var list = card.querySelector('.options-list');
            list.insertAdjacentHTML('beforeend', optionRowHtml(idx, nextOptIdx, null));
            attachOptionRemove(list.lastElementChild);
            nextOptIdx++;
        });

        /* attach remove to pre-existing options */
        card.querySelectorAll('.option-row').forEach(attachOptionRemove);

        /* ── Condition logic ─────────────────────────────────────── */
        var condToggle   = card.querySelector('.condition-toggle');
        var condFields   = card.querySelector('.condition-fields');
        var condQSel     = card.querySelector('.cond-question-select');
        var condOpSel    = card.querySelector('.cond-operator-select');
        var condValInput = card.querySelector('.cond-value-input');
        var condHidIdx   = card.querySelector('.cond-hidden-idx');
        var condHidOp    = card.querySelector('.cond-hidden-operator');
        var condHidVal   = card.querySelector('.cond-hidden-value');

        condToggle.addEventListener('change', function () {
            condFields.classList.toggle('d-none', !this.checked);
            if (!this.checked) {
                condHidIdx.value = '';
                condHidOp.value  = '';
                condHidVal.value = '';
            }
        });

        condQSel.addEventListener('change', function () {
            condHidIdx.value = this.value;
        });

        condOpSel.addEventListener('change', function () {
            condHidOp.value = this.value;
            condValInput.classList.toggle('d-none', this.value === 'is_answered');
            if (this.value === 'is_answered') condHidVal.value = '';
        });

        condValInput.addEventListener('input', function () {
            condHidVal.value = this.value;
        });

        /* auto-populate field key from question label keywords */
        var KEY_MAP = [
            { patterns: ['email'],                             key: 'email' },
            { patterns: ['first name', 'firstname'],           key: 'first_name' },
            { patterns: ['last name', 'lastname', 'surname'],  key: 'last_name' },
            { patterns: ['phone', 'mobile', 'cell'],           key: 'phone' },
            { patterns: ['date of birth', 'dob', 'birth'],     key: 'date_of_birth' },
            { patterns: ['gender', 'sex'],                     key: 'gender' },
            { patterns: ['address line 2', 'address 2', 'apt','suite'], key: 'address2' },
            { patterns: ['address', 'street'],                 key: 'address' },
            { patterns: ['city', 'town'],                      key: 'city' },
            { patterns: ['state', 'province'],                 key: 'state' },
            { patterns: ['zip', 'postal'],                     key: 'zip' },
            { patterns: ['country'],                           key: 'country' },
            { patterns: ['age'],                               key: 'age' },
            { patterns: ['height'],                            key: 'height' },
            { patterns: ['weight'],                            key: 'weight' },
            { patterns: ['bmi', 'body mass'],                  key: 'bmi' },
            { patterns: ['name'],                              key: 'first_name' }, // fallback — after last_name rule
        ];

        var questionInput = card.querySelector('input[name="questions[' + idx + '][question]"]');
        var keyInput      = card.querySelector('input[name="questions[' + idx + '][key]"]');

        questionInput.addEventListener('input', function () {
            if (keyInput.value.trim() !== '') return; // never overwrite manual entry
            var lower = this.value.toLowerCase();
            var matched = '';
            KEY_MAP.some(function (rule) {
                return rule.patterns.some(function (p) {
                    if (lower.indexOf(p) !== -1) { matched = rule.key; return true; }
                });
            });
            keyInput.value = matched;
            keyInput.style.background = matched ? '#fff9e6' : '';

            // Also refresh condition dropdowns so question text previews stay current
            refreshAllConditionDropdowns();
        });

        /* clear highlight when user manually edits the key */
        keyInput.addEventListener('input', function () {
            this.style.background = '';
        });
    }

    function updateNumbers() {
        document.querySelectorAll('.question-card').forEach(function (card, i) {
            card.querySelector('.question-label').textContent = 'Question ' + (i + 1);
        });
    }

    function syncEmptyMsg() {
        var msg = document.getElementById('no-questions');
        if (!msg) return;
        msg.style.display = document.querySelectorAll('.question-card').length ? 'none' : 'block';
    }

    function applyModeToAllCards() {
        var multi = isMultiMode();
        document.querySelectorAll('.step-field').forEach(function (el) {
            el.classList.toggle('d-none', !multi);
        });
    }

    function addQuestion(data) {
        var container = document.getElementById('questions-container');
        container.insertAdjacentHTML('beforeend', buildQuestionHtml(qIdx, data));
        initCard(container.lastElementChild);
        qIdx++;
        syncEmptyMsg();
        refreshAllConditionDropdowns();
    }

    /* Mode radio toggle */
    document.querySelectorAll('input[name=mode]').forEach(function (r) {
        r.addEventListener('change', applyModeToAllCards);
    });

    /* Add Question button */
    document.getElementById('add-question').addEventListener('click', function () {
        addQuestion(null);
    });

    /* ── Reindex all field names after drag-reorder ─────────────────── */
    function reindexCards() {
        document.querySelectorAll('.question-card').forEach(function (card, newIdx) {
            var oldIdx = parseInt(card.dataset.idx);
            if (oldIdx === newIdx) return;

            // Rename all [name] attributes: questions[old] → questions[new]
            card.querySelectorAll('[name]').forEach(function (el) {
                el.setAttribute('name', el.getAttribute('name').replace(
                    'questions[' + oldIdx + ']',
                    'questions[' + newIdx + ']'
                ));
            });

            // Update Required checkbox id + label
            var reqEl = card.querySelector('#req_' + oldIdx);
            if (reqEl) {
                var reqLabel = card.querySelector('label[for="req_' + oldIdx + '"]');
                reqEl.id = 'req_' + newIdx;
                if (reqLabel) reqLabel.htmlFor = 'req_' + newIdx;
            }

            // Update Read-only checkbox id + label
            var roEl = card.querySelector('#ro_' + oldIdx);
            if (roEl) {
                var roLabel = card.querySelector('label[for="ro_' + oldIdx + '"]');
                roEl.id = 'ro_' + newIdx;
                if (roLabel) roLabel.htmlFor = 'ro_' + newIdx;
            }

            // Update disqualify option ids + labels
            card.querySelectorAll('[id^="disq_' + oldIdx + '_"]').forEach(function (el) {
                var suffix    = el.id.slice(('disq_' + oldIdx + '_').length);
                var oldDId    = 'disq_' + oldIdx + '_' + suffix;
                var newDId    = 'disq_' + newIdx + '_' + suffix;
                var dLabel    = card.querySelector('label[for="' + oldDId + '"]');
                el.id = newDId;
                if (dLabel) dLabel.htmlFor = newDId;
            });

            // Update condition toggle id + label
            var condEl = card.querySelector('#cond_' + oldIdx);
            if (condEl) {
                var condLabel = card.querySelector('label[for="cond_' + oldIdx + '"]');
                condEl.id = 'cond_' + newIdx;
                if (condLabel) condLabel.htmlFor = 'cond_' + newIdx;
            }

            card.dataset.idx = newIdx;
        });

        // Keep qIdx ahead so new additions don't collide
        qIdx = document.querySelectorAll('.question-card').length;
        updateNumbers();
        // Rebuild condition dropdowns — indices changed so all option values need updating
        refreshAllConditionDropdowns();
    }

    /* ── SortableJS drag-and-drop on questions container ────────────── */
    Sortable.create(document.getElementById('questions-container'), {
        handle:     '.drag-handle',
        animation:  150,
        ghostClass: 'border-primary',
        onEnd: function () { reindexCards(); },
    });

    /* Pre-populate existing questions (edit mode) */
    existingQuestions.forEach(function (q) { addQuestion(q); });
    syncEmptyMsg();

    /* ── Post-load: pre-select condition dropdowns in edit mode ──────── */
    // addQuestion() calls refreshAllConditionDropdowns() per card, but the
    // target card may not exist yet when an early card is added.
    // Do one final pass after all cards are rendered.
    existingQuestions.forEach(function (q, i) {
        if (q.depends_on_idx === null || q.depends_on_idx === undefined) return;
        var cards = document.querySelectorAll('.question-card');
        var card  = cards[i];
        if (!card) return;
        var sel = card.querySelector('.cond-question-select');
        if (!sel) return;
        var opt = sel.querySelector('option[value="' + q.depends_on_idx + '"]');
        if (opt) opt.selected = true;
        var hidIdx = card.querySelector('.cond-hidden-idx');
        if (hidIdx) hidIdx.value = q.depends_on_idx;
    });
})();
</script>
