<?php

declare(strict_types=1);

namespace App\ReturnFlow;

final readonly class ResultDto {}

final class ReturnFlowHandler
{
    public function direct(): ResultDto
    {
        return new ResultDto();
    }

    public function variable(): ResultDto
    {
        $result = new ResultDto();

        return $result;
    }

    public function scalar(): string
    {
        return 'ignored for now';
    }
}
