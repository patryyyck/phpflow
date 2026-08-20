<?php

declare(strict_types=1);

namespace PhpFlow\Infrastructure\Messenger;

use PhpFlow\Domain\Analysis\MessageRouting;
use PhpFlow\Domain\Analysis\MessengerTransport;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final readonly class MessengerRoutingReader
{
    public function __construct(
        private PhpMessengerRoutingReader $phpReader = new PhpMessengerRoutingReader(),
    ) {
    }

    /**
     * @return list<MessageRouting>
     */
    public function read(string $projectPath): array
    {
        $packagesDirectory = $projectPath.'/config/packages';

        if (!is_dir($packagesDirectory)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->in($packagesDirectory)
            ->depth('== 0')
            ->name('*.yaml')
            ->name('*.yml')
            ->name('*.php')
            ->sortByName();

        $result = [];

        foreach ($finder as $file) {
            $extension = strtolower($file->getExtension());

            $items = $extension === 'php'
                ? $this->phpReader->read($file->getRealPath())
                : $this->readYaml($file->getRealPath());

            foreach ($items as $item) {
                $result[] = new MessageRouting(
                    $item->message(),
                    $item->transports(),
                    $file->getRelativePathname(),
                );
            }
        }

        return $result;
    }

    /** @return list<MessengerTransport> */
    public function transports(string $projectPath): array
    {
        $packagesDirectory = $projectPath.'/config/packages';
        if (!is_dir($packagesDirectory)) return [];

        $finder = (new Finder())
            ->files()->in($packagesDirectory)->depth('== 0')
            ->name('*.php')->sortByName();

        $result = [];
        foreach ($finder as $file) {
            foreach ($this->phpReader->readTransports($file->getRealPath()) as $transport) {
                $result[] = $transport;
            }
        }
        return $result;
    }

    /**
     * @return list<MessageRouting>
     */
    private function readYaml(string $file): array
    {
        $config = Yaml::parseFile($file);
        $entries = $config['framework']['messenger']['routing'] ?? [];

        if (!is_array($entries)) {
            return [];
        }

        $result = [];

        foreach ($entries as $message => $target) {
            if (!is_string($message)) {
                continue;
            }

            $transports = $this->yamlTransports($target);

            if ($transports !== []) {
                $result[] = new MessageRouting(
                    ltrim($message, '\\'),
                    $transports,
                );
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function yamlTransports(mixed $target): array
    {
        if (is_string($target)) {
            return [$target];
        }

        if (!is_array($target)) {
            return [];
        }

        if (isset($target['senders']) && is_array($target['senders'])) {
            return array_values(array_filter($target['senders'], 'is_string'));
        }

        if (array_is_list($target)) {
            return array_values(array_filter($target, 'is_string'));
        }

        return [];
    }
}
