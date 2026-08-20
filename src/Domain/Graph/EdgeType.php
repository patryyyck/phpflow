<?php

declare(strict_types=1);

namespace PhpFlow\Domain\Graph;

enum EdgeType: string
{
    case INVOKES = 'invokes';
    case DISPATCHES = 'dispatches';
    case HANDLED_BY = 'handled_by';
    case CALLS = 'calls';
}
