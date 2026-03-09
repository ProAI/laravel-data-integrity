<?php

use ProAI\DataIntegrity\AuditManager;
use ProAI\DataIntegrity\Tests\Fixtures\Checks\AlwaysFailsCheck;
use ProAI\DataIntegrity\Tests\Fixtures\User;

function auditsPath(): string
{
    return str_replace('\\', '/', realpath(__DIR__.'/Fixtures/Audits'));
}

describe('AuditCommand', function () {

    beforeEach(function () {
        AuditManager::flush();
    });

    describe('passing audits', function () {

        it('exits successfully when all audits pass', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Passing'])
                ->assertExitCode(0);
        });

        it('outputs a passed count in the summary for passing audits', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Passing'])
                ->expectsOutputToContain('passed')
                ->assertExitCode(0);
        });

        it('outputs the passed count in the summary', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Passing'])
                ->expectsOutputToContain('1 passed')
                ->assertExitCode(0);
        });

    });

    describe('failing audits', function () {

        it('reports violations when an audit fails', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Failing'])
                ->expectsOutputToContain('failed')
                ->assertExitCode(0);
        });

        it('outputs each violation reason with auto-prefixed model id', function () {
            $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Failing'])
                ->expectsOutputToContain("User #{$user->id}: always fails")
                ->assertExitCode(0);
        });

        it('outputs the failed count in the summary', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
            User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Failing'])
                ->expectsOutputToContain('2 records')
                ->assertExitCode(0);
        });

        it('suggests --fix when violations are found', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Failing'])
                ->expectsOutputToContain('--fix')
                ->assertExitCode(0);
        });

    });

    describe('--fix option with inline closures', function () {

        it('calls the fix closure when --fix is passed', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'inactive']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Fixable', '--fix' => true]);

            expect(User::first()->status)->toBe('active');
        });

        it('does not call the fix closure without --fix', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'inactive']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Fixable']);

            expect(User::first()->status)->toBe('inactive');
        });

        it('outputs a fixed count when --fix is passed', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'inactive']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Fixable', '--fix' => true])
                ->expectsOutputToContain('Fixed 1 record');
        });

        it('fixes only violations not all records', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);
            User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'inactive']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Fixable', '--fix' => true]);

            expect(User::where('status', 'active')->count())->toBe(2);
        });

    });

    describe('--fix option with IntegrityCheck', function () {

        it('calls the inline fix closure when --fix is passed', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'inactive']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Check', '--fix' => true]);

            expect(User::first()->status)->toBe('active');
        });

        it('detects violations via the check class', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'inactive']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Check'])
                ->expectsOutputToContain('failed')
                ->assertExitCode(0);
        });

    });

    describe('query scoping', function () {

        it('only validates records matching the query constraints', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);
            $inactive = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'inactive']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Scoped'])
                ->expectsOutputToContain("User #{$inactive->id}: is inactive")
                ->assertExitCode(0);
        });

        it('does not validate records excluded by the query', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Scoped'])
                ->expectsOutputToContain('passed')
                ->assertExitCode(0);
        });

    });

    describe('auditUsing with registered alias', function () {

        it('resolves and runs a registered check alias', function () {
            AuditManager::register('always-fails', AlwaysFailsCheck::class);

            User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Registered'])
                ->expectsOutputToContain('failed')
                ->assertExitCode(0);
        });

    });

    describe('--model filter', function () {

        it('only runs audits for the specified model', function () {
            User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active']);

            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Passing', '--model' => 'User'])
                ->expectsOutputToContain('User')
                ->assertExitCode(0);
        });

        it('shows a warning when no audits match the model filter', function () {
            AuditManager::discoverIn(auditsPath());

            $this->artisan('db:audit', ['directory' => 'Passing', '--model' => 'NonExistentModel'])
                ->expectsOutputToContain('No audits found for model')
                ->assertExitCode(0);
        });

    });

    describe('directory scoping', function () {

        it('scopes discovery to a subdirectory', function () {
            AuditManager::discoverIn(
                str_replace('\\', '/', realpath(__DIR__.'/Fixtures/Discovery'))
            );

            User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

            $this->artisan('db:audit', ['directory' => 'Sub'])
                ->expectsOutputToContain('passed')
                ->assertExitCode(0);
        });

    });

    describe('empty state', function () {

        it('outputs a warning when no audits are found', function () {
            AuditManager::discoverIn('non/existent/path');

            $this->artisan('db:audit')
                ->expectsOutputToContain('No audits found')
                ->assertExitCode(0);
        });

        it('outputs a warning mentioning the directory when scoped', function () {
            AuditManager::discoverIn('non/existent/path');

            $this->artisan('db:audit', ['directory' => 'Exams'])
                ->expectsOutputToContain('No audits found in Exams')
                ->assertExitCode(0);
        });

    });

    describe('discoverIn()', function () {

        it('overrides the default discovery path', function () {
            AuditManager::discoverIn('custom/path');

            $this->artisan('db:audit')
                ->expectsOutputToContain('No audits found')
                ->assertExitCode(0);
        });

    });

});
