<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Discovery;

use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class DiscoverableAudit extends AuditCase
{
    protected string $model = User::class;

    public function checkDiscoverable(): Audit
    {
        return $this->audit()
            ->description('discoverable audit')
            ->validate(function ($model, $fail) {});
    }
}
