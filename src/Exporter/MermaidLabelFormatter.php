<?php

declare(strict_types=1);

namespace PhpFlow\Exporter;

use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;

final class MermaidLabelFormatter
{
    public function format(Node $node): string
    {
        return match ($node->type()) {
            NodeType::ROUTE => $node->label(),
            NodeType::CONTROLLER,
            NodeType::HANDLER => $this->shortenCallable($node->label()),
            NodeType::MESSAGE => $this->shortenClass($node->label()),
            NodeType::REPOSITORY,
            NodeType::SERVICE => $this->shortenCallable($node->label()),
            NodeType::HTTP_ENDPOINT,
            NodeType::DATABASE,
            NodeType::MAIL,
            NodeType::FILESYSTEM,
            NodeType::CACHE,
            NodeType::EXCEPTION,
            NodeType::CONDITION,
            NodeType::RETURN_VALUE,
            NodeType::HTTP_RESPONSE,
            NodeType::CONTINUATION,
            NodeType::CONTROL_BRANCH,
            NodeType::LOOP,
            NodeType::LOOP_CONTROL => $node->label(),
        };
    }

    private function shortenCallable(string $callable): string
    {
        [$class, $method] = array_pad(explode('::', $callable, 2), 2, null);

        $shortClass = $this->shortenClass($class);

        return $method === null
            ? $shortClass
            : $shortClass.'::'.$method;
    }

    private function shortenClass(string $class): string
    {
        $class = ltrim($class, '\\');

        if ($class === '') {
            return $class;
        }

        $parts = explode('\\', $class);

        return end($parts) ?: $class;
    }
}
