<?php

use ProAI\DataIntegrity\AuditDiscovery;
use ProAI\DataIntegrity\Tests\Fixtures\Discovery\DiscoverableAudit;
use ProAI\DataIntegrity\Tests\Fixtures\Discovery\Sub\SubAudit;

describe('AuditDiscovery', function () {

    describe('discover()', function () {

        beforeEach(function () {
            $this->path = __DIR__.'/Fixtures/Discovery';
        });

        it('discovers concrete Audit subclasses', function () {
            $discovery = new AuditDiscovery($this->path);

            $classes = $discovery->discover();

            expect($classes)->toContain(DiscoverableAudit::class);
        });

        it('discovers audits in subdirectories', function () {
            $discovery = new AuditDiscovery($this->path);

            $classes = $discovery->discover();

            expect($classes)->toContain(SubAudit::class);
        });

        it('excludes abstract Audit subclasses', function () {
            $discovery = new AuditDiscovery($this->path);

            $classes = $discovery->discover();

            expect($classes)->not->toContain(\ProAI\DataIntegrity\Tests\Fixtures\Discovery\AbstractAudit::class);
        });

        it('excludes classes that do not extend Audit', function () {
            $discovery = new AuditDiscovery($this->path);

            $classes = $discovery->discover();

            expect($classes)->not->toContain(\ProAI\DataIntegrity\Tests\Fixtures\Discovery\NotAnAudit::class);
        });

        it('scopes discovery to a subdirectory', function () {
            $discovery = new AuditDiscovery($this->path);

            $classes = $discovery->discover('Sub');

            expect($classes)
                ->toContain(SubAudit::class)
                ->not->toContain(DiscoverableAudit::class);
        });

        it('returns an empty collection for a non-existent subdirectory', function () {
            $discovery = new AuditDiscovery($this->path);

            $classes = $discovery->discover('NonExistent');

            expect($classes)->toBeEmpty();
        });

        it('returns an empty collection for a non-existent base path', function () {
            $discovery = new AuditDiscovery('/does/not/exist');

            $classes = $discovery->discover();

            expect($classes)->toBeEmpty();
        });

        it('returns a collection of fully-qualified class name strings', function () {
            $discovery = new AuditDiscovery($this->path);

            $classes = $discovery->discover('Sub');

            expect($classes->first())->toBe(SubAudit::class);
        });

    });

});
