<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

/**
 * A `Foo::class` read from source, kept apart from a plain string literal: a
 * service id such as `'app.provider'` does not name an application class.
 */
final readonly class ClassReference
{
    public function __construct(public string $name)
    {
    }
}
