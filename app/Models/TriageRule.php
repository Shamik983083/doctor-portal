<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TriageRule extends Model
{
    protected $fillable = [
        'type',
        'label',
        'operator',
        'value',
        'triage_result',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPES = [
        'bmi_threshold' => 'BMI Threshold',
        'age_threshold' => 'Age Threshold',
        'keyword'       => 'Red Flag Keyword',
        'offering'      => 'Elevated Offering',
    ];

    public const OPERATORS = [
        'gte'      => '≥ (at or above)',
        'lte'      => '≤ (at or below)',
        'gt'       => '> (above)',
        'lt'       => '< (below)',
        'contains' => 'contains',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function operatorSymbol(): string
    {
        return match ($this->operator) {
            'gte'      => '≥',
            'lte'      => '≤',
            'gt'       => '>',
            'lt'       => '<',
            'contains' => 'contains',
            default    => $this->operator,
        };
    }
}
