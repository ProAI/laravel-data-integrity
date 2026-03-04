<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Checks;

use Closure;
use Illuminate\Database\Eloquent\Model;
use ProAI\DataIntegrity\IntegrityCheck;

class FixableCheck implements IntegrityCheck
{
    public function validate(Model $model, Closure $fail): void
    {
        if ($model->status !== 'active') {
            $fail(
                "has invalid status '{$model->status}'",
                fn () => $model->update(['status' => 'active'])
            );
        }
    }
}
