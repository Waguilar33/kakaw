<?php

use Kakaw\Exceptions\KakawException;

arch('no debugging calls are left in the package')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('every package exception extends the base exception')
    ->expect('Kakaw\Exceptions')
    ->toExtend(KakawException::class)
    ->ignoring(KakawException::class);
