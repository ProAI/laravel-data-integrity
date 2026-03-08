<?php

namespace ProAI\DataIntegrity\Tests\Fixtures\Audits\Fixable;

use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\Tests\Fixtures\User;

class FixableAudit extends AuditCase
{
    protected string $model = User::class;

    public function checkFixable(): Audit
    {
        return $this->audit()
            ->description('fixable audit')
            ->validate(function ($model, $fail) {
                if ($model->status !== 'active') {
                    $fail(
                        "has invalid status '{$model->status}'",
                        fn () => $model->update(['status' => 'active'])
                    );
                }
            });
    }
}
