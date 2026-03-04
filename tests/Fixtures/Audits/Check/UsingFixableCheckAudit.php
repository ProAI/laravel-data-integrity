<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Audits\Check;

use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\Tests\Fixtures\Checks\FixableCheck;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class UsingFixableCheckAudit extends AuditCase
{
    protected $model = User::class;

    public function checkFixable(): Audit
    {
        return $this->auditUsing(FixableCheck::class);
    }
}
