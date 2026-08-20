<?php

declare(strict_types=1);

namespace PhpFlow\Ast;

final class VendorSymbolLocator
{
    /** @var array<string, list<string>>|null */
    private ?array $psr4 = null;

    public function __construct(private readonly string $projectPath)
    {
    }

    public function locate(string $className): ?string
    {
        $vendorDirectory = $this->projectPath.'/vendor';

        if (!is_dir($vendorDirectory)) {
            return null;
        }

        foreach ($this->psr4Mappings() as $prefix => $directories) {
            if (!str_starts_with($className, $prefix)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($className, strlen($prefix))).'.php';

            foreach ($directories as $directory) {
                $candidate = $directory.'/'.$relative;

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function psr4Mappings(): array
    {
        if ($this->psr4 !== null) {
            return $this->psr4;
        }

        $file = $this->projectPath.'/vendor/composer/autoload_psr4.php';

        if (!is_file($file)) {
            return $this->psr4 = [];
        }

        /** @var array<string, string|list<string>> $mappings */
        $mappings = require $file;
        $normalized = [];

        foreach ($mappings as $prefix => $directories) {
            $normalized[$prefix] = array_values((array) $directories);
        }

        // Longest prefix first.
        uksort($normalized, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $this->psr4 = $normalized;
    }
}
