<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Checks;

use Closure;
use Illuminate\Database\Eloquent\Model;
use ProAI\DataIntegrity\IntegrityCheck;

class AlwaysFailsCheck implements IntegrityCheck
{
    public function validate(Model $model, Closure $fail): void
    {
        $fail('always fails');
    }
}
