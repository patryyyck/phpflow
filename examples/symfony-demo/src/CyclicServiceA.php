<?php
namespace App\ServiceCycle;
final readonly class CyclicServiceA
{
    public function __construct(private CyclicServiceB $b) {}
    public function run(): void { $this->b->run(); }
}
