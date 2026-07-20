<?php

namespace Tests\Unit;

use App\Services\Ai\AiAssistManager;
use App\Services\Ai\MockAiAdapter;
use RuntimeException;
use Tests\TestCase;

/**
 * The gate that keeps PHI away from a model provider until a human has said the
 * BAA is executed, plus the local composition that keeps the product working
 * with no model at all.
 *
 * Database-free for the same reason as EhrTenantSegregationTest: this repo has
 * one model factory, so anything that needed fixtures could not run.
 */
class AiAssistGateTest extends TestCase
{
    public function test_the_openai_adapter_is_unreachable_until_the_baa_is_confirmed(): void
    {
        config(['ai.adapter' => 'openai', 'ai.enabled' => true, 'ai.baa_confirmed' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/baa_confirmed/');

        (new AiAssistManager())->resolve();
    }

    public function test_enabling_ai_alone_does_not_open_the_gate(): void
    {
        config(['ai.adapter' => 'openai', 'ai.enabled' => true, 'ai.baa_confirmed' => false]);
        $this->assertFalse((new AiAssistManager())->liveDraftingEnabled());

        config(['ai.enabled' => false, 'ai.baa_confirmed' => true]);
        $this->assertFalse((new AiAssistManager())->liveDraftingEnabled());

        config(['ai.enabled' => true, 'ai.baa_confirmed' => true]);
        $this->assertTrue((new AiAssistManager())->liveDraftingEnabled());
    }

    public function test_the_mock_adapter_is_always_safe_and_never_counts_as_live(): void
    {
        config(['ai.adapter' => 'mock', 'ai.enabled' => true, 'ai.baa_confirmed' => true]);

        $this->assertSame('mock', (new AiAssistManager())->resolve()->key());
        $this->assertFalse((new AiAssistManager())->liveDraftingEnabled(),
            'the mock must never be reported as live drafting, or the UI would claim a model ran');
    }

    /** The provider must always be told when a draft did not come from the model. */
    public function test_a_reason_is_given_whenever_live_drafting_is_off(): void
    {
        config(['ai.adapter' => 'openai', 'ai.enabled' => true, 'ai.baa_confirmed' => false]);
        $this->assertStringContainsString('BAA', (new AiAssistManager())->disabledReason());

        config(['ai.adapter' => 'openai', 'ai.enabled' => false]);
        $this->assertNotNull((new AiAssistManager())->disabledReason());

        config(['ai.adapter' => 'mock']);
        $this->assertNotNull((new AiAssistManager())->disabledReason());

        config(['ai.adapter' => 'openai', 'ai.enabled' => true, 'ai.baa_confirmed' => true]);
        $this->assertNull((new AiAssistManager())->disabledReason(), 'null means live, and must not be a message');
    }

    public function test_the_mock_adapter_returns_the_grounded_local_composition(): void
    {
        $result = (new MockAiAdapter())->draft([
            'context'  => 'clinical_note',
            'input'    => 'irrelevant',
            'fallback' => 'Approved Semaglutide, 3 months, weekly.',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('Approved Semaglutide, 3 months, weekly.', $result['text']);
    }

    public function test_the_mock_adapter_refuses_an_empty_composition_rather_than_returning_blank(): void
    {
        $result = (new MockAiAdapter())->draft(['context' => 'clinical_note', 'fallback' => '   ']);

        $this->assertFalse($result['ok']);
        $this->assertSame('', $result['text']);
    }
}
