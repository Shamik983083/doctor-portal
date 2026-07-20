<?php

namespace App\Services\Ai;

/**
 * An AI assist adapter turns a built prompt into a DRAFT string and returns a
 * normalized result. Adapters MUST NOT perform any real network call unless AI
 * assist is both enabled and BAA-confirmed (see config/ai.php); the resolver
 * enforces this.
 *
 * An adapter never decides, never signs and never sends. It returns text that a
 * human is required to review before it lands anywhere.
 */
interface AiAssistAdapter
{
    /**
     * @param  array $prompt  { instructions:string, input:string, context:string, prompt_id:?string }
     * @return array          { ok:bool, code:int, text:string, error:?string, model:?string, usage:?array }
     */
    public function draft(array $prompt): array;

    public function key(): string;
}
