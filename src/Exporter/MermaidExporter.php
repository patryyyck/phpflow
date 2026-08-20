<?php

declare(strict_types=1);

namespace PhpFlow\Exporter;

use PhpFlow\Domain\Graph\Edge;
use PhpFlow\Domain\Graph\EdgeType;
use PhpFlow\Domain\Graph\Graph;
use PhpFlow\Domain\Graph\Node;
use PhpFlow\Application\DetectGraphCycles;
use PhpFlow\Domain\Graph\NodeType;

final class MermaidExporter
{
    public function __construct(
        private readonly MermaidLabelFormatter $labelFormatter = new MermaidLabelFormatter(),
        private readonly DetectGraphCycles $cycleDetector = new DetectGraphCycles(),
    ) {
    }

    public function export(Graph $graph): string
    {
        $cycleNodes = array_fill_keys(
            $this->cycleDetector->cycleNodeIds($graph),
            true,
        );

        $lines = [
            "%%{init: {'theme': 'dark', 'themeVariables': {",
            "  'background': '#0b0f14',",
            "  'primaryTextColor': '#f8fafc',",
            "  'lineColor': '#cbd5e1',",
            "  'edgeLabelBackground': '#111827',",
            "  'fontFamily': 'Inter, ui-sans-serif, system-ui, sans-serif'",
            "}}}%%",
            'flowchart TD',
            '',
        ];

        foreach ($graph->nodes() as $node) {
            $lines[] = sprintf(
                '    %s%s:::type_%s',
                $this->nodeId($node),
                $this->nodeShape($node),
                $node->type()->value,
            );

            if (isset($cycleNodes[$node->id()])) {
                $lines[] = sprintf(
                    '    class %s cycle_node;',
                    $this->nodeId($node),
                );
            }
        }

        if ($graph->edges() !== []) {
            $lines[] = '';
        }

        foreach ($this->orderedEdges($graph) as $edge) {
            $lines[] = $this->edgeLine($edge);
        }

        $lines[] = '';
        $lines[] = '    subgraph phpflow_legend["Legend"]';
        $lines[] = '        direction LR';
        $lines[] = '        legend_route(["🌐 Route"]):::type_route';
        $lines[] = '        legend_controller["▣ Controller"]:::type_controller';
        $lines[] = '        legend_message{{"✉ Message"}}:::type_message';
        $lines[] = '        legend_handler[["⚙ Handler"]]:::type_handler';
        $lines[] = '        legend_repository[("🗄 Repository")]:::type_repository';
        $lines[] = '        legend_http(["🌍 HTTP endpoint"]):::type_http_endpoint';
        $lines[] = '        legend_service["🔧 Service"]:::type_service';
        $lines[] = '        legend_database[("💾 Database effect")]:::type_database';
        $lines[] = '        legend_mail(["✉️ Mail"]):::type_mail';
        $lines[] = '        legend_filesystem[["📁 Filesystem"]]:::type_filesystem';
        $lines[] = '        legend_cache[("⚡ Cache")]:::type_cache';
        $lines[] = '        legend_exception{{"⚠ Exception"}}:::type_exception';
        $lines[] = '        legend_condition{"◇ Condition"}:::type_condition';
        $lines[] = '        legend_return(["↩ Return value"]):::type_return_value';
        $lines[] = '        legend_http_response(["⇠ HTTP response"]):::type_http_response';
        $lines[] = '        legend_continuation["▶ CONTINUE"]:::type_continuation';
        $lines[] = '        legend_control_branch{"⎇ Control branch"}:::type_control_branch';
        $lines[] = '        legend_loop[["↻ Loop"]]:::type_loop';
        $lines[] = '        legend_loop_control{{"↪ Break / Continue"}}:::type_loop_control';
        $lines[] = '        legend_sync_a["A"]:::legend_hidden -->|sync| legend_sync_b["B"]:::legend_hidden';
        $lines[] = '        legend_async_a["A"]:::legend_hidden -.->|async| legend_async_b["B"]:::legend_hidden';
        $lines[] = '        legend_cycle["↻ Cycle"]:::cycle_node';
        $lines[] = '    end';
        $lines[] = '';
        $lines[] = '    classDef legend_hidden fill:transparent,stroke:#64748b,color:#f8fafc,stroke-width:1px;';
        $lines[] = '    classDef cycle_node stroke:#ef4444,stroke-width:3px;';
        $lines[] = '    style phpflow_legend fill:#111827,stroke:#475569,color:#f8fafc;';
        $lines[] = '    classDef type_route fill:#2b2136,stroke:#c084fc,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_controller fill:#0f1f38,stroke:#3b82f6,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_message fill:#102719,stroke:#4ade80,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_handler fill:#33200f,stroke:#fb923c,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_repository fill:#1f2937,stroke:#94a3b8,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_http_endpoint fill:#172554,stroke:#60a5fa,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_service fill:#27272a,stroke:#a78bfa,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_database fill:#1c1917,stroke:#facc15,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_mail fill:#3b1628,stroke:#f472b6,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_filesystem fill:#17202a,stroke:#22d3ee,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_cache fill:#271c19,stroke:#fb7185,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_exception fill:#3f1515,stroke:#f87171,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_condition fill:#27272a,stroke:#a1a1aa,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_return_value fill:#132a24,stroke:#2dd4bf,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_http_response fill:#172554,stroke:#38bdf8,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_continuation fill:#15251f,stroke:#34d399,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_control_branch fill:#211c2f,stroke:#818cf8,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_loop fill:#10251f,stroke:#22c55e,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    classDef type_loop_control fill:#2b1d10,stroke:#f59e0b,color:#f8fafc,stroke-width:2px;';
        $lines[] = '    linkStyle default stroke:#cbd5e1,stroke-width:1.5px;';

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function icon(NodeType $type): string
    {
        return match ($type) {
            NodeType::ROUTE => '🌐',
            NodeType::CONTROLLER => '▣',
            NodeType::MESSAGE => '✉',
            NodeType::HANDLER => '⚙',
            NodeType::REPOSITORY => '🗄',
            NodeType::HTTP_ENDPOINT => '🌍',
            NodeType::SERVICE => '🔧',
            NodeType::DATABASE => '💾',
            NodeType::MAIL => '✉️',
            NodeType::FILESYSTEM => '📁',
            NodeType::CACHE => '⚡',
            NodeType::EXCEPTION => '⚠',
            NodeType::CONDITION => '◇',
            NodeType::RETURN_VALUE => '↩',
            NodeType::HTTP_RESPONSE => '⇠',
            NodeType::CONTINUATION => '▶',
            NodeType::CONTROL_BRANCH => '⎇',
            NodeType::LOOP => '↻',
            NodeType::LOOP_CONTROL => '↪',
        };
    }

    private function nodeShape(Node $node): string
    {
        $label = $this->escapeLabel(
            $this->icon($node->type()).' '.$this->labelFormatter->format($node),
        );

        return match ($node->type()) {
            NodeType::ROUTE => sprintf('(["%s"])', $label),
            NodeType::CONTROLLER => sprintf('["%s"]', $label),
            NodeType::MESSAGE => sprintf('{{"%s"}}', $label),
            NodeType::HANDLER => sprintf('[["%s"]]', $label),
            NodeType::REPOSITORY => sprintf('[("%s")]', $label),
            NodeType::HTTP_ENDPOINT => sprintf('(["%s"])', $label),
            NodeType::SERVICE => sprintf('["%s"]', $label),
            NodeType::DATABASE => sprintf('[("%s")]', $label),
            NodeType::MAIL => sprintf('(["%s"])', $label),
            NodeType::FILESYSTEM => sprintf('[["%s"]]', $label),
            NodeType::CACHE => sprintf('[("%s")]', $label),
            NodeType::EXCEPTION => sprintf('{{"%s"}}', $label),
            NodeType::CONDITION => sprintf('{"%s"}', $label),
            NodeType::RETURN_VALUE => sprintf('(["%s"])', $label),
            NodeType::HTTP_RESPONSE => sprintf('(["%s"])', $label),
            NodeType::CONTINUATION => sprintf('["%s"]', $label),
            NodeType::CONTROL_BRANCH => sprintf('{"%s"}', $label),
            NodeType::LOOP => sprintf('[["%s"]]', $label),
            NodeType::LOOP_CONTROL => sprintf('{{"%s"}}', $label),
        };
    }

    /** @return list<Edge> */
    private function orderedEdges(Graph $graph): array
    {
        $edges = $graph->edges();

        // Mermaid uses declaration order as one of its layout hints. Keep
        // source groups stable, but preserve PHP source order inside each
        // source node whenever AST ordering metadata is available.
        $sourceOrder = [];
        foreach ($edges as $index => $edge) {
            $sourceOrder[$edge->source()] ??= $index;
        }

        $indexed = [];
        foreach ($edges as $index => $edge) {
            $indexed[] = [$edge, $index];
        }

        usort(
            $indexed,
            static function (array $left, array $right) use ($sourceOrder): int {
                /** @var Edge $leftEdge */
                $leftEdge = $left[0];
                /** @var Edge $rightEdge */
                $rightEdge = $right[0];

                $sourceComparison =
                    $sourceOrder[$leftEdge->source()]
                    <=> $sourceOrder[$rightEdge->source()];

                if ($sourceComparison !== 0) {
                    return $sourceComparison;
                }

                $leftOrder = $leftEdge->order();
                $rightOrder = $rightEdge->order();

                if ($leftOrder !== null || $rightOrder !== null) {
                    $orderComparison =
                        ($leftOrder ?? PHP_INT_MAX)
                        <=> ($rightOrder ?? PHP_INT_MAX);

                    if ($orderComparison !== 0) {
                        return $orderComparison;
                    }
                }

                return $left[1] <=> $right[1];
            },
        );

        return array_map(
            static fn (array $item): Edge => $item[0],
            $indexed,
        );
    }

    private function edgeLine(Edge $edge): string
    {
        $source = $this->nodeIdFromString($edge->source());
        $target = $this->nodeIdFromString($edge->target());
        $label = $this->escapeLabel($edge->label() ?? $edge->type()->value);

        if (
            $edge->type() === EdgeType::DISPATCHES
            && str_starts_with($label, 'async:')
        ) {
            return sprintf(
                '    %s -.->|%s| %s',
                $source,
                $label,
                $target,
            );
        }

        return sprintf(
            '    %s -->|%s| %s',
            $source,
            $label,
            $target,
        );
    }

    private function nodeId(Node $node): string
    {
        return $this->nodeIdFromString($node->id());
    }

    private function nodeIdFromString(string $id): string
    {
        return 'n_'.substr(hash('sha256', $id), 0, 12);
    }

    private function escapeLabel(string $label): string
    {
        return str_replace(
            ['\\', '"', "\n", "\r"],
            ['\\\\', '\\"', ' ', ' '],
            $label,
        );
    }
}
