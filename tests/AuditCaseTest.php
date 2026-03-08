<?php

use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\AuditCase;
use ProAI\DataIntegrity\AuditManager;
use ProAI\DataIntegrity\Tests\Fixtures\Checks\AlwaysFailsCheck;
use ProAI\DataIntegrity\Tests\Fixtures\Checks\ConstructorArgsCheck;
use ProAI\DataIntegrity\Tests\Fixtures\User;

describe('AuditCase', function () {

    afterEach(function () {
        AuditManager::flush();
    });

    describe('audit()', function () {

        it('collects an Audit from a check method', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkTest(): Audit
                {
                    return $this->audit()
                        ->description('test audit')
                        ->validate(function ($model, $fail) {});
                }
            };

            $pending = $audit->getAudits();

            expect($pending)->toHaveCount(1);
            expect($pending[0])->toBeInstanceOf(Audit::class);
            expect($pending[0]->getModel())->toBe(User::class);
            expect($pending[0]->getDescription())->toBe('test audit');
            expect($pending[0]->getValidateCallback())->toBeInstanceOf(Closure::class);
        });

        it('sets query callback and chunk size', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkScoped(): Audit
                {
                    return $this->audit()
                        ->description('scoped')
                        ->query(fn ($q) => $q->where('status', 'active'))
                        ->chunkSize(50)
                        ->validate(function ($model, $fail) {});
                }
            };

            $pending = $audit->getAudits()[0];

            expect($pending->getQueryCallback())->toBeInstanceOf(Closure::class);
            expect($pending->getChunkSize())->toBe(50);
        });

        it('sets before callback', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkWithBefore(): Audit
                {
                    return $this->audit()
                        ->description('with before')
                        ->before(fn ($chunk) => $chunk->pluck('id'))
                        ->validate(function ($model, $fail) {});
                }
            };

            expect($audit->getAudits()[0]->getBeforeCallback())->toBeInstanceOf(Closure::class);
        });

        it('sets after callback', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkWithAfter(): Audit
                {
                    return $this->audit()
                        ->description('with after')
                        ->after(fn ($chunk) => null)
                        ->validate(function ($model, $fail) {});
                }
            };

            expect($audit->getAudits()[0]->getAfterCallback())->toBeInstanceOf(Closure::class);
        });

        it('defaults chunk size to 1000', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkDefaultChunk(): Audit
                {
                    return $this->audit()
                        ->description('default chunk')
                        ->validate(function ($model, $fail) {});
                }
            };

            expect($audit->getAudits()[0]->getChunkSize())->toBe(1000);
        });

        it('collects multiple audits from multiple check methods', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkFirst(): Audit
                {
                    return $this->audit()
                        ->description('first')
                        ->validate(fn ($model, $fail) => null);
                }

                public function checkSecond(): Audit
                {
                    return $this->audit()
                        ->description('second')
                        ->validate(fn ($model, $fail) => null);
                }
            };

            expect($audit->getAudits())->toHaveCount(2);
        });

        it('derives description from method name when not provided', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkEmailIsValid(): Audit
                {
                    return $this->audit()
                        ->validate(fn ($model, $fail) => null);
                }
            };

            expect($audit->getAudits()[0]->getDescription())->toBe('email is valid');
        });

    });

    describe('auditUsing()', function () {

        it('creates an Audit with validate callback from check', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkAlwaysFails(): Audit
                {
                    return $this->auditUsing(AlwaysFailsCheck::class);
                }
            };

            $pending = $audit->getAudits()[0];

            expect($pending->getValidateCallback())->toBeInstanceOf(Closure::class);
        });

        it('passes constructor arguments to the check class', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkWithArgs(): Audit
                {
                    return $this->auditUsing(ConstructorArgsCheck::class, ['active']);
                }
            };

            $pending = $audit->getAudits()[0];

            expect($pending->getValidateCallback())->toBeInstanceOf(Closure::class);
        });

        it('derives description from class name', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkAlwaysFails(): Audit
                {
                    return $this->auditUsing(AlwaysFailsCheck::class);
                }
            };

            expect($audit->getAudits()[0]->getDescription())->toBe('always fails check');
        });

        it('resolves a registered alias to the check class', function () {
            AuditManager::register('always-fails', AlwaysFailsCheck::class);

            $audit = new class extends AuditCase
            {
                protected string $model = User::class;

                public function checkAlwaysFails(): Audit
                {
                    return $this->auditUsing('always-fails');
                }
            };

            expect($audit->getAudits()[0]->getValidateCallback())
                ->toBeInstanceOf(Closure::class);
        });

    });

    describe('register()', function () {

        it('registers a named check alias', function () {
            AuditManager::register('my-check', AlwaysFailsCheck::class);

            expect(AuditManager::resolveCheck('my-check'))->toBe(AlwaysFailsCheck::class);
        });

        it('returns the class as-is when not registered', function () {
            expect(AuditManager::resolveCheck(AlwaysFailsCheck::class))
                ->toBe(AlwaysFailsCheck::class);
        });

    });

    describe('flush()', function () {

        it('clears all registered checks', function () {
            AuditManager::register('my-check', AlwaysFailsCheck::class);
            AuditManager::flush();

            expect(AuditManager::resolveCheck('my-check'))->toBe('my-check');
        });

    });

    describe('getModel()', function () {

        it('returns the model class', function () {
            $audit = new class extends AuditCase
            {
                protected string $model = User::class;
            };

            expect($audit->getModel())->toBe(User::class);
        });

    });

});
