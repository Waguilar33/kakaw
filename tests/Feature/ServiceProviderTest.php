<?php

use Illuminate\Support\ServiceProvider;
use Kakaw\KakawManager;
use Kakaw\KakawServiceProvider;

it('resolves the manager as a singleton', function () {
    expect(app(KakawManager::class))->toBe(app(KakawManager::class));
});

it('defaults to the digifact certifier', function () {
    expect(config('kakaw.default'))->toBe('digifact');
});

it('publishes the config under the kakaw-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(KakawServiceProvider::class, 'kakaw-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/kakaw.php');
});
