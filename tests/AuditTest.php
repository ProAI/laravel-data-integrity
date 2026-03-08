<?php

use ProAI\DataIntegrity\Audit;
use ProAI\DataIntegrity\AuditManager;
use ProAI\DataIntegrity\Tests\Fixtures\User;

describe('Audit', function () {

    afterEach(function () {
        AuditManager::flush();
    });

    it('sets model and description', function () {
        $audit = (new Audit(model: User::class))->description('test');

        expect($audit->getModel())->toBe(User::class);
        expect($audit->getDescription())->toBe('test');
    });

    it('has sensible defaults', function () {
        $audit = new Audit(model: User::class);

        expect($audit->getDescription())->toBeNull();
        expect($audit->getQueryCallback())->toBeNull();
        expect($audit->getChunkSize())->toBe(1000);
        expect($audit->getBeforeCallback())->toBeNull();
        expect($audit->getAfterCallback())->toBeNull();
        expect($audit->getValidateCallback())->toBeNull();
    });

    it('sets properties via fluent methods', function () {
        $query = fn ($q) => $q;
        $before = fn ($chunk) => $chunk->pluck('id');
        $after = fn ($chunk) => null;
        $validate = fn ($model, $fail) => null;

        $audit = (new Audit(model: User::class))
            ->description('full')
            ->query($query)
            ->chunkSize(50)
            ->before($before)
            ->after($after)
            ->validate($validate);

        expect($audit->getQueryCallback())->toBe($query);
        expect($audit->getChunkSize())->toBe(50);
        expect($audit->getBeforeCallback())->toBe($before);
        expect($audit->getAfterCallback())->toBe($after);
        expect($audit->getValidateCallback())->toBe($validate);
    });

    it('returns itself from fluent methods', function () {
        $audit = new Audit(model: User::class);

        expect($audit->description('test'))->toBe($audit);
        expect($audit->query(fn ($q) => $q))->toBe($audit);
        expect($audit->validate(fn ($m, $f) => null))->toBe($audit);
        expect($audit->chunkSize(50))->toBe($audit);
        expect($audit->before(fn ($c) => null))->toBe($audit);
        expect($audit->after(fn ($c) => null))->toBe($audit);
    });

});
