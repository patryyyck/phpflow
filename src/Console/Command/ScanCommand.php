<?php

declare(strict_types=1);

namespace PhpFlow\Console\Command;

use PhpFlow\Application\BuildFlowGraph;
use PhpFlow\Application\ScanProject;
use PhpFlow\Application\AnalyzeProject;
use PhpFlow\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'scan', description: 'Scans a PHP project.')]
final class ScanCommand extends Command
{
    public function __construct(
        private readonly ScanProject $scanProject,
        private readonly AnalyzeProject $analyzer,
        private readonly BuildFlowGraph $graphBuilder = new BuildFlowGraph(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Path of the PHP project to scan.', '.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');

        if (!is_string($path) || $path === '') {
            throw new \InvalidArgumentException('The project path must be a non-empty string.');
        }

        $project = $this->scanProject->scan($path);
        $analysis = $this->analyzer->analyze($project);
        $statistics = $analysis->statistics();
        $graph = $this->graphBuilder->build($analysis);
        $io = new SymfonyStyle($input, $output);

        $io->title(sprintf('PHPFlow %s', Version::VERSION));
        $io->section('Project');
        $io->definitionList(
            ['Path' => $project->path()],
            ['PHP files' => (string) $project->sourceFileCount()],
            ['Classes' => (string) $statistics->classes()],
            ['Interfaces' => (string) $statistics->interfaces()],
            ['Traits' => (string) $statistics->traits()],
            ['Enums' => (string) $statistics->enums()],
            ['Attributes' => (string) count($analysis->attributes())],
            ['Symfony routes' => (string) count($analysis->routes())],
            ['Message dispatches' => (string) count($analysis->messageDispatches())],
            ['Message handlers' => (string) count($analysis->messageHandlers())],
            ['Message routing rules' => (string) count($analysis->messageRoutings())],
            ['Resolved message routings' => (string) count($analysis->resolvedMessageRoutings())],
            ['Messenger transports' => (string) count($analysis->messengerTransports())],
            ['Repository calls' => (string) count($analysis->repositoryCalls())],
            ['HTTP calls' => (string) count($analysis->httpCalls())],
            ['Service calls' => (string) count($analysis->serviceCalls())],
            ['Application effects' => (string) count($analysis->applicationEffects())],
            ['Thrown exceptions' => (string) count($analysis->thrownExceptions())],
            ['Typed returns' => (string) count($analysis->methodReturns())],
            ['HTTP responses' => (string) count($analysis->httpResponses())],
            ['Unresolved calls' => (string) count($analysis->unresolvedCalls())],
            ['Graph nodes' => (string) count($graph->nodes())],
            ['Graph edges' => (string) count($graph->edges())],
        );

        if ($analysis->routes() !== []) {
            $io->section('Symfony routes');
            $rows = [];

            foreach ($analysis->routes() as $route) {
                $rows[] = [
                    $route->methods() === [] ? '*' : implode('|', $route->methods()),
                    $route->path() ?? '<dynamic>',
                    $route->controller(),
                ];
            }

            $io->table(['Method', 'Path', 'Controller'], $rows);
        }

        if ($analysis->messageDispatches() !== []) {
            $io->section('Message dispatches');
            $rows = [];

            foreach ($analysis->messageDispatches() as $dispatch) {
                $rows[] = [$dispatch->source(), $dispatch->message()];
            }

            $io->table(['Source', 'Message'], $rows);
        }

        if ($analysis->messageHandlers() !== []) {
            $io->section('Message handlers');
            $rows = [];

            foreach ($analysis->messageHandlers() as $handler) {
                $rows[] = [$handler->message(), $handler->handler()];
            }

            $io->table(['Message', 'Handler'], $rows);
        }

        if ($analysis->messengerTransports() !== []) {
            $io->section('Messenger transports');
            $rows = [];
            foreach ($analysis->messengerTransports() as $transport) {
                $rows[] = [
                    $transport->name(),
                    $transport->dsn() ?? '-',
                    $transport->environment() ?? 'default',
                    $transport->source(),
                ];
            }
            $io->table(['Transport', 'DSN', 'Environment', 'Source'], $rows);
        }

        if ($analysis->messageRoutings() !== []) {
            $io->section('Messenger routing rules');
            $rows = [];
            foreach ($analysis->messageRoutings() as $routing) {
                $rows[] = [$routing->message(), implode(', ', $routing->transports()), $routing->source() ?? '-'];
            }
            $io->table(['Message', 'Transport(s)', 'Source'], $rows);
        }

        if ($analysis->resolvedMessageRoutings() !== []) {
            $io->section('Resolved Messenger routing');
            $rows = [];
            foreach ($analysis->resolvedMessageRoutings() as $routing) {
                $rows[] = [$routing->message(), implode(', ', $routing->transports())];
            }
            $io->table(['Concrete message', 'Transport(s)'], $rows);
        }

        if ($analysis->repositoryCalls() !== []) {
            $io->section('Repository calls');
            $rows = [];

            foreach ($analysis->repositoryCalls() as $call) {
                $rows[] = [
                    $call->source(),
                    $call->repository(),
                    $call->method(),
                    $call->implementation() ?? '<unresolved>',
                ];
            }

            $io->table(['Source', 'Repository', 'Method', 'Implementation'], $rows);
        }

        if ($analysis->httpCalls() !== []) {
            $io->section('HTTP calls');
            $rows = [];

            foreach ($analysis->httpCalls() as $call) {
                $rows[] = [
                    $call->source(),
                    $call->client(),
                    $call->method() ?? '?',
                    $call->url() ?? '<dynamic URL>',
                ];
            }

            $io->table(['Source', 'Client', 'Method', 'URL'], $rows);
        }

        if ($analysis->serviceCalls() !== []) {
            $io->section('Service calls');
            $rows = [];
            foreach ($analysis->serviceCalls() as $call) {
                $rows[] = [
                    $call->source(),
                    $call->service().'::'.$call->method(),
                    $call->implementation() ?? '<unresolved>',
                ];
            }
            $io->table(['Source', 'Service call', 'Implementation'], $rows);
        }

        if ($analysis->applicationEffects() !== []) {
            $io->section('Application effects');
            $rows = [];

            foreach ($analysis->applicationEffects() as $effect) {
                $rows[] = [
                    $effect->source(),
                    $effect->kind(),
                    $effect->operation(),
                    $effect->target() ?? '-',
                ];
            }

            $io->table(['Source', 'Kind', 'Operation', 'Target'], $rows);
        }

        if ($analysis->thrownExceptions() !== []) {
            $io->section('Thrown exceptions');
            $rows = [];

            foreach ($analysis->thrownExceptions() as $exception) {
                $rows[] = [
                    $exception->source(),
                    $exception->exception(),
                    $exception->condition() ?? '-',
                ];
            }

            $io->table(['Source', 'Exception', 'Condition'], $rows);
        }

        if ($analysis->httpResponses() !== []) {
            $io->section('HTTP responses');
            $rows = [];

            foreach ($analysis->httpResponses() as $response) {
                $rows[] = [
                    $response->source(),
                    $response->responseType(),
                    $response->statusCode() === null ? '?' : (string) $response->statusCode(),
                    $response->branch() ?? '-',
                ];
            }

            $io->table(['Source', 'Response', 'Status', 'Branch'], $rows);
        }

        if ($analysis->methodReturns() !== []) {
            $io->section('Typed returns');
            $rows = [];

            foreach ($analysis->methodReturns() as $return) {
                $rows[] = [
                    $return->source(),
                    $return->type(),
                    $return->branch() ?? '-',
                ];
            }

            $io->table(['Source', 'Return type', 'Branch'], $rows);
        }

        if ($analysis->unresolvedCalls() !== []) {
            $io->section('Unresolved calls');
            $rows = [];

            foreach ($analysis->unresolvedCalls() as $call) {
                $rows[] = [$call->source(), $call->method(), $call->argumentType() ?? '?'];
            }

            $io->table(['Source', 'Method', 'Known argument type'], $rows);
        }

        $io->success('Done.');

        return Command::SUCCESS;
    }
}
