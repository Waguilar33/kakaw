<?php

use Illuminate\Support\Facades\Blade;
use Kakaw\Contracts\Certifier;
use Kakaw\Contracts\IssuesTotalCreditNotes;
use Kakaw\KakawManager;

function docsPath(string $relative): string
{
    return dirname(__DIR__, 2).'/'.$relative;
}

function docsContents(string $relative): string
{
    return (string) file_get_contents(docsPath($relative));
}

it('names FEL, Guatemala, Laravel and SAT in the readme', function (string $term) {
    expect(docsContents('README.md'))->toContain($term);
})->with(['FEL', 'Guatemala', 'Laravel', 'SAT']);

it('ships a Boost guideline under 60 lines', function () {
    $path = docsPath('resources/boost/guidelines/core.blade.php');

    expect($path)->toBeFile()
        ->and(count(file($path)))->toBeLessThan(60);
});

it('ships a skill whose name matches its folder and which has a description', function () {
    $contents = docsContents('resources/boost/skills/kakaw-development/SKILL.md');

    preg_match('/\A---\R(.*?)\R---/s', $contents, $frontmatter);

    preg_match('/^name:\s*(.+)$/m', $frontmatter[1] ?? '', $name);
    preg_match('/^description:\s*(.+)$/m', $frontmatter[1] ?? '', $description);

    expect(trim($name[1] ?? ''))->toBe('kakaw-development')
        ->and(trim($description[1] ?? ''))->not->toBeEmpty();
});

it('only calls Kakaw methods that exist', function () {
    $available = collect([KakawManager::class, Certifier::class, IssuesTotalCreditNotes::class])
        ->flatMap(fn (string $class): array => get_class_methods($class))
        ->unique()
        ->all();

    $documents = [
        'README.md',
        'resources/boost/guidelines/core.blade.php',
        'resources/boost/skills/kakaw-development/SKILL.md',
    ];

    foreach ($documents as $document) {
        preg_match_all('/Kakaw::(\w+)\(/', docsContents($document), $matches);

        expect($matches[1])->not->toBeEmpty();

        foreach (array_unique($matches[1]) as $method) {
            expect(in_array($method, $available, true))->toBeTrue("{$document} calls Kakaw::{$method}()");
        }
    }
});

it('only names environment keys that the config reads', function () {
    preg_match_all('/KAKAW_[A-Z_]+/', docsContents('README.md'), $matches);

    $config = docsContents('config/kakaw.php');

    expect($matches[0])->not->toBeEmpty();

    foreach (array_unique($matches[0]) as $key) {
        expect($config)->toContain($key);
    }
});

it('ships a boost guideline that renders as blade', function () {
    $rendered = Blade::render(docsContents('resources/boost/guidelines/core.blade.php'));

    expect($rendered)->toContain('Kakaw::certify')->not->toContain('@verbatim');
});
