<?php

declare(strict_types=1);

namespace App\ImportedResolution\Domain;

interface ExternalClientInterface
{
    public function call(): void;
}
