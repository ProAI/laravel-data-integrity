<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Audits\Registered;

use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class RegisteredCheckAudit extends AuditCase
{
    protected $model = User::class;

    public function checkRegistered(): Audit
    {
        return $this->auditUsing('always-fails');
    }
}
