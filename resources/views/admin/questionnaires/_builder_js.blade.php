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
    }

    /* Mode radio toggle */
    document.querySelectorAll('input[name=mode]').forEach(function (r) {
        r.addEventListener('change', applyModeToAllCards);
    });

    /* Add Question button */
    document.getElementById('add-question').addEventListener('click', function () {
        addQuestion(null);
    });

    /* Pre-populate existing questions (edit mode) */
    existingQuestions.forEach(function (q) { addQuestion(q); });
    syncEmptyMsg();
})();
</script>
