<?php

namespace App\Services\Routing;

/**
 * Tunable coefficients for the intelligent routing score.
 *
 * Ported value-for-value from MA-DOCPORTAL's DEFAULT_ROUTING_WEIGHTS
 * (`packages/domain/src/routing.ts`). The defaults are not invented here: they are
 * MA's, so an INTELLIGENT policy activated in MEDAXIS with no overrides behaves
 * exactly as it would in MA.
 *
 * Every coefficient is overridable per policy version, which is what makes the
 * scoring tunable without a deploy.
 */
final class RoutingWeights
{
    public const DEFAULTS = [
        'greenCase'                 => 1,
        'yellowCase'                => 3,
        'redCase'                   => 2,
        'unansweredMessage'         => 2,
        'messageOver12HoursPenalty' => 25,
        'medianDecisionMinute'      => 0.02,
        'dailyVolume'               => 0.25,
        'allocationPenalty'         => 5,
    ];

    public const LABELS = [
        'greenCase'                 => 'Open green case',
        'yellowCase'                => 'Open yellow case',
        'redCase'                   => 'Open red case',
        'unansweredMessage'         => 'Unanswered message',
        'messageOver12HoursPenalty' => 'Message older than 12 hours',
        'medianDecisionMinute'      => 'Per minute of median decision time',
        'dailyVolume'               => 'Per case already assigned today',
        'allocationPenalty'         => 'Allocation penalty multiplier',
    ];

    private array $weights;

    public function __construct(array $overrides = [])
    {
        // Unknown keys are ignored rather than merged in: a typo in a stored
        // policy must not silently introduce a coefficient nothing reads, and it
        // must not remove one the score depends on either.
        $weights = self::DEFAULTS;

        foreach (self::DEFAULTS as $key => $default) {
            if (array_key_exists($key, $overrides) && is_numeric($overrides[$key])) {
                $weights[$key] = (float) $overrides[$key];
            }
        }

        $this->weights = $weights;
    }

    public function get(string $key): float
    {
        return (float) ($this->weights[$key] ?? self::DEFAULTS[$key] ?? 0);
    }

    public function toArray(): array
    {
        return $this->weights;
    }
}
