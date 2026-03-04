<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Discovery\Sub;

use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class SubAudit extends AuditCase
{
    protected $model = User::class;

    public function checkSub(): Audit
    {
        return $this->audit()
            ->description('sub audit')
            ->validate(function ($model, $fail) {});
    }
}
