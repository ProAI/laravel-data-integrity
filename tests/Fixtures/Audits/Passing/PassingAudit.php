<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Audits\Passing;

use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class PassingAudit extends AuditCase
{
    protected string $model = User::class;

    public function checkPassing(): Audit
    {
        return $this->audit()
            ->description('passing audit')
            ->validate(function ($model, $fail) {
                // never fails
            });
    }
}
