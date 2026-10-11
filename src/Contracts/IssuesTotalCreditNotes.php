<?php

namespace Kakaw\Contracts;

use Kakaw\Data\Results\CertifiedDocument;
use Kakaw\Data\TotalCreditNote;

interface IssuesTotalCreditNotes
{
    public function creditNote(TotalCreditNote $creditNote): CertifiedDocument;
}
