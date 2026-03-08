<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Audits\Check;

use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Tests\Fixtures\Checks\AlwaysFailsCheck;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class UsingCheckAudit extends AuditCase
{
    protected string $model = User::class;

    public function checkAlwaysFails(): Audit
    {
        return $this->auditUsing(AlwaysFailsCheck::class);
    }
}
