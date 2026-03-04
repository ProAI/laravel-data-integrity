<?php

use ProAI\DataIntegrity\Tests\Fixtures\Checks\AlwaysFailsCheck;
use ProAI\DataIntegrity\Tests\Fixtures\Checks\FixableCheck;
use ProAI\DataIntegrity\Tests\Fixtures\User;

describe('IntegrityCheck', function () {

    describe('validate()', function () {

        it('receives a model and a fail closure', function () {
            $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);

            $check = new AlwaysFailsCheck;
            $failures = [];

            $fail = function (string $reason, ?Closure $fix = null) use (&$failures) {
                $failures[] = ['reason' => $reason, 'fix' => $fix];
            };

            $check->validate($user, $fail);

            expect($failures)->toHaveCount(1);
            expect($failures[0]['reason'])->toContain('always fails');
        });

        it('reports a fix closure when provided', function () {
            $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'inactive']);

            $check = new FixableCheck;
            $failures = [];

            $fail = function (string $reason, ?Closure $fix = null) use (&$failures) {
                $failures[] = ['reason' => $reason, 'fix' => $fix];
            };

            $check->validate($user, $fail);

            expect($failures)->toHaveCount(1);
            expect($failures[0]['fix'])->toBeInstanceOf(Closure::class);

            ($failures[0]['fix'])();

            expect($user->fresh()->status)->toBe('active');
        });

        it('does not fail when model passes validation', function () {
            $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);

            $check = new FixableCheck;
            $failures = [];

            $fail = function (string $reason, ?Closure $fix = null) use (&$failures) {
                $failures[] = ['reason' => $reason, 'fix' => $fix];
            };

            $check->validate($user, $fail);

            expect($failures)->toBeEmpty();
        });

    });

});
