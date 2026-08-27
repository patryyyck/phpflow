<?php

declare(strict_types=1);

namespace PhpFlow\Tests\Exporter;

use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Domain\Graph\NodeType;
use PhpFlow\Exporter\JsonExporter;
use PHPUnit\Framework\TestCase;

final class JsonDisplayLabelTest extends TestCase
{
    public function testItKeepsCanonicalLabelAndExportsShortDisplayLabelWithSourceMetadata(): void
    {
        $graph = new Graph();
        $graph->setSymbolFiles([
            'App\\Application\\Command\\SyncCompanyHandler' => 'src/Application/Command/SyncCompanyHandler.php',
        ]);
        $graph->addNode(new Node(
            'handler:sync',
            NodeType::HANDLER,
            'App\\Application\\Command\\SyncCompanyHandler::__invoke',
        ));

        $data = json_decode(
            (new JsonExporter())->export($graph),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame('1.2', $data['schemaVersion']);
        self::assertSame(
            'App\\Application\\Command\\SyncCompanyHandler::__invoke',
            $data['nodes'][0]['label'],
        );
        self::assertSame(
            'SyncCompanyHandler::__invoke',
            $data['nodes'][0]['displayLabel'],
        );
        self::assertSame(
            [
                'class' => 'App\\Application\\Command\\SyncCompanyHandler',
                'shortName' => 'SyncCompanyHandler',
                'namespace' => 'App\\Application\\Command',
                'method' => '__invoke',
                'file' => 'src/Application/Command/SyncCompanyHandler.php',
            ],
            $data['nodes'][0]['metadata']['callable'],
        );
    }
}
