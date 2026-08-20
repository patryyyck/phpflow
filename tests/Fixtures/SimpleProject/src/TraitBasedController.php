<?php

namespace App\Controller;

use App\Message\TraitMessage;
use VendorPackage\HandleTrait;

final class TraitBasedController
{
    use HandleTrait;

    public function run(): mixed
    {
        $query = new TraitMessage();

        return $this->handle($query);
    }
}
