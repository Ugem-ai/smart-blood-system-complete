<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ChapterInventoryUpdated
{
    use Dispatchable;

    public function __construct(
        public array $payload
    ) {
    }
}
