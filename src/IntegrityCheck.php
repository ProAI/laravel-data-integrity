<?php

namespace ProAI\DataIntegrity;

use Closure;
use Illuminate\Database\Eloquent\Model;

interface IntegrityCheck
{
    /**
     * Validate a single model instance.
     *
     * @return void
     */
    public function validate(Model $model, Closure $fail);
}
