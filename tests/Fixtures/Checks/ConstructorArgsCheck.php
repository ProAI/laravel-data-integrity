<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Checks;

use Closure;
use Illuminate\Database\Eloquent\Model;
use ProAI\DataIntegrity\IntegrityCheck;

class ConstructorArgsCheck implements IntegrityCheck
{
    public function __construct(
        public readonly string $expectedStatus,
    ) {}

    public function validate(Model $model, Closure $fail): void
    {
        if ($model->status !== $this->expectedStatus) {
            $fail("does not have status '{$this->expectedStatus}'");
        }
    }
}
