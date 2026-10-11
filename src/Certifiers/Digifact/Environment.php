<?php

namespace Kakaw\Certifiers\Digifact;

enum Environment: string
{
    case Test = 'test';
    case Production = 'production';

    public function baseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://testnucgt.digifact.com/api/',
            self::Production => 'https://nucgt.digifact.com/gt.com.apinuc/api/',
        };
    }
}
