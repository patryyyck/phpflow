<?php
namespace App\ServiceCycle;
final readonly class CyclicServiceB
{
    public function __construct(private CyclicServiceA $a) {}
    public function run(): void { $this->a->run(); }
}
