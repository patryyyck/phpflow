<?php

declare(strict_types=1);

namespace PhpFlow\Infrastructure\Scanner;

use PhpFlow\Application\Contract\ProjectScanner;
use PhpFlow\Domain\Project;
use PhpFlow\Domain\SourceFile;
use Symfony\Component\Finder\Finder;

final class DirectoryScanner implements ProjectScanner
{
    /**
     * Directories that never contain application source.
     *
     * `var/` holds Symfony generated artifacts (container dumps, proxies, logs).
     * Parsing them is both expensive and misleading: a generated container
     * re-declares routes and services that would surface as phantom flows.
     *
     * @var list<string>
     */
    private const array EXCLUDED_DIRECTORIES = ['vendor', 'var'];

    public function scan(string $path): Project
    {
        $realPath = realpath($path);

        if ($realPath === false || !is_dir($realPath)) {
            throw new \InvalidArgumentException(sprintf('Project directory "%s" does not exist.', $path));
        }

        $finder = (new Finder())
            ->files()
            ->in($realPath)
            ->name('*.php')
            ->exclude(self::EXCLUDED_DIRECTORIES)
            ->sortByName();

        $sourceFiles = [];

        foreach ($finder as $file) {
            $sourceFiles[] = new SourceFile($file->getRealPath());
        }

        return new Project($realPath, $sourceFiles);
    }
}
