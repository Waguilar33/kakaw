<?php

namespace Kakaw\Facades;

use Illuminate\Support\Facades\Facade;
use Kakaw\KakawManager;

/**
 * @method static \Kakaw\Contracts\Certifier certifier(?string $name = null)
 * @method static \Kakaw\Contracts\Certifier build(array<string, mixed> $config)
 * @method static \Kakaw\Data\Issuer issuer()
 * @method static void verifyCredentials()
 * @method static \Kakaw\Data\Results\CertifiedDocument certify(\Kakaw\Data\Document $document)
 * @method static \Kakaw\Data\Results\AnnulledDocument annul(\Kakaw\Data\Annulment $annulment)
 * @method static \Kakaw\Data\Results\CertifiedDocument creditNote(\Kakaw\Data\TotalCreditNote $creditNote)
 * @method static \Kakaw\Data\Results\Taxpayer|null taxpayer(string $taxId)
 * @method static \Kakaw\Data\Results\RetrievedDocument|null document(string $authorization)
 * @method static \Kakaw\KakawManager extend(string $driver, \Closure $callback)
 *
 * @see KakawManager
 */
class Kakaw extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return KakawManager::class;
    }
}
