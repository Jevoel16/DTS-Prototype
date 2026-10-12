<?php

namespace App\Events;

use App\Models\Document;

class IntegrityCheckFailed
{
    public $document;
    public $reason;

    public function __construct(Document $document, string $reason)
    {
        $this->document = $document;
        $this->reason = $reason;
    }
}
