<?php

namespace App\Controller;

use App\Message\ExternalMessage;
use VendorPackage\ExternalController;

final class VendorBasedController extends ExternalController
{
    public function run(): mixed
    {
        $message = new ExternalMessage();

        return $this->execute($message);
    }
}
