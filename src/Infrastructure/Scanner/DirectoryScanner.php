<?php

declare(strict_types=1);

namespace PhpFlow\Infrastructure\Scanner;

use PhpFlow\Application\Contract\ProjectScanner;
use PhpFlow\Domain\Project;
use PhpFlow\Domain\SourceFile;
use Symfony\Component\Finder\Finder;

final class DirectoryScanner implements ProjectScanner
{
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
            ->exclude('vendor')
            ->sortByName();

        $sourceFiles = [];

        foreach ($finder as $file) {
            $sourceFiles[] = new SourceFile($file->getRealPath());
        }

        return new Project($realPath, $sourceFiles);
    }
}
