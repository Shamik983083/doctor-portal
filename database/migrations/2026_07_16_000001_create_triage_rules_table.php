<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triage_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);                        // bmi_threshold | age_threshold | keyword | offering
            $table->string('label');                           // Human-readable name shown in triage reasons log
            $table->string('operator', 10)->default('gte');   // gte | lte | gt | lt | contains
            $table->string('value', 255)->nullable();          // Numeric threshold or keyword/pattern string
            $table->string('triage_result', 10);              // red | yellow
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed rules from the existing config/triage.php defaults
        $now = now()->toDateTimeString();

        DB::table('triage_rules')->insert([
            // ── BMI thresholds ──────────────────────────────────────────
            ['type' => 'bmi_threshold', 'label' => 'Critical BMI (≥ 50)',    'operator' => 'gte', 'value' => '50',           'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 10,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'bmi_threshold', 'label' => 'High BMI (≥ 40)',        'operator' => 'gte', 'value' => '40',           'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 20,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'bmi_threshold', 'label' => 'Low BMI (≤ 18.5)',       'operator' => 'lte', 'value' => '18.5',         'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 30,  'created_at' => $now, 'updated_at' => $now],
            // ── Age thresholds ───────────────────────────────────────────
            ['type' => 'age_threshold', 'label' => 'Minor (< 18)',            'operator' => 'lt',  'value' => '18',           'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 10,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'age_threshold', 'label' => 'Geriatric (≥ 65)',        'operator' => 'gte', 'value' => '65',           'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 20,  'created_at' => $now, 'updated_at' => $now],
            // ── Red-flag keywords — Red ──────────────────────────────────
            ['type' => 'keyword', 'label' => 'Keyword: pregnan',        'operator' => 'contains', 'value' => 'pregnan',        'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 10,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: breastfeed',     'operator' => 'contains', 'value' => 'breastfeed',     'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 20,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: chest pain',     'operator' => 'contains', 'value' => 'chest pain',     'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 30,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: suicide',        'operator' => 'contains', 'value' => 'suicide',        'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 40,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: suicidal',       'operator' => 'contains', 'value' => 'suicidal',       'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 50,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: stroke',         'operator' => 'contains', 'value' => 'stroke',         'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 60,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: heart attack',   'operator' => 'contains', 'value' => 'heart attack',   'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 70,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: thyroid cancer', 'operator' => 'contains', 'value' => 'thyroid cancer', 'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 80,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: medullary',      'operator' => 'contains', 'value' => 'medullary',      'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 90,  'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: pancreatitis',   'operator' => 'contains', 'value' => 'pancreatitis',   'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: anaphyla',       'operator' => 'contains', 'value' => 'anaphyla',       'triage_result' => 'red',    'is_active' => 1, 'sort_order' => 110, 'created_at' => $now, 'updated_at' => $now],
            // ── Red-flag keywords — Yellow ───────────────────────────────
            ['type' => 'keyword', 'label' => 'Keyword: allerg',         'operator' => 'contains', 'value' => 'allerg',         'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 200, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: seizure',        'operator' => 'contains', 'value' => 'seizure',        'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 210, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: diabet',         'operator' => 'contains', 'value' => 'diabet',         'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 220, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: kidney',         'operator' => 'contains', 'value' => 'kidney',         'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 230, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: liver',          'operator' => 'contains', 'value' => 'liver',          'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 240, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: eating disorder','operator' => 'contains', 'value' => 'eating disorder', 'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 250, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: gallbladder',    'operator' => 'contains', 'value' => 'gallbladder',    'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 260, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: insulin',        'operator' => 'contains', 'value' => 'insulin',        'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 270, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: blood thinner',  'operator' => 'contains', 'value' => 'blood thinner',  'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 280, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'keyword', 'label' => 'Keyword: warfarin',       'operator' => 'contains', 'value' => 'warfarin',       'triage_result' => 'yellow', 'is_active' => 1, 'sort_order' => 290, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('triage_rules');
    }
};
