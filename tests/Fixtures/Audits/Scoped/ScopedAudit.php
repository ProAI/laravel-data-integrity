<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Audits\Scoped;

use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class ScopedAudit extends AuditCase
{
    protected $model = User::class;

    public function checkScoped(): Audit
    {
        return $this->audit()
            ->description('scoped audit')
            ->query(fn ($query) => $query->where('status', 'inactive'))
            ->validate(function ($model, $fail) {
                $fail('is inactive');
            });
    }
}
