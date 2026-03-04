<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Audits\Failing;

use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class FailingAudit extends AuditCase
{
    protected $model = User::class;

    public function checkAlwaysFails(): Audit
    {
        return $this->audit()
            ->description('failing audit')
            ->validate(function ($model, $fail) {
                $fail('always fails');
            });
    }
}
