<?php
namespace App\Sync;
interface ExternalSyncClientInterface { public function register(object $input): object; }
