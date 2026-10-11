<?php

namespace Kakaw\Tests;

use Illuminate\Support\Facades\Http;
use Kakaw\KakawServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [KakawServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    protected function fixture(string $path): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/'.$path);
    }
}
