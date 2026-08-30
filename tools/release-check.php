<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$failures = [];

$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

require $root.'/src/Version.php';

$expect(\PhpFlow\Version::VERSION === '0.1.0', 'Expected PHPFlow version 0.1.0.');

$schemas = [
    'src/Exporter/JsonExporter.php' => '1.2',
    'src/Exporter/ImpactJsonExporter.php' => '1.0',
    'src/Exporter/GraphDiffJsonExporter.php' => '1.0',
];

foreach ($schemas as $file => $expectedVersion) {
    $source = file_get_contents($root.'/'.$file);
    $expect(
        is_string($source)
        && preg_match("/SCHEMA_VERSION\s*=\s*'".preg_quote($expectedVersion, '/')."'/", $source) === 1,
        sprintf('Expected %s schema version %s.', $file, $expectedVersion),
    );
}

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
$expect(is_array($composer), 'composer.json must contain valid JSON.');
$expect(($composer['name'] ?? null) === 'phpflow/phpflow', 'Unexpected Composer package name.');
$expect(($composer['license'] ?? null) === 'MIT', 'Expected MIT Composer license.');
$expect(($composer['bin'] ?? null) === ['bin/phpflow'], 'Expected bin/phpflow Composer binary.');

$makefile = (string) file_get_contents($root.'/Makefile');
$expect(
    str_contains($makefile, '.DEFAULT_GOAL := help'),
    'Expected `make` to show first-run help by default.',
);
$expect(
    preg_match('/^setup:\R/m', $makefile) === 1,
    'Expected a `make setup` first-run target.',
);
$expect(
    preg_match('/^demo:\R/m', $makefile) === 1,
    'Expected a `make demo` first-run target.',
);
$expect(
    str_contains($makefile, 'composer install --no-interaction --prefer-dist'),
    'Expected setup to install locked Composer dependencies.',
);

$requiredFiles = [
    '.github/ISSUE_TEMPLATE/bug_report.yml',
    '.github/ISSUE_TEMPLATE/feature_request.yml',
    '.github/ISSUE_TEMPLATE/unsupported_pattern.yml',
    '.github/pull_request_template.md',
    '.github/workflows/ci.yml',
    'CHANGELOG.md',
    'CODE_OF_CONDUCT.md',
    'CONTRIBUTING.md',
    'LICENSE',
    'README.md',
    'RELEASE.md',
    'SECURITY.md',
    'docs/CLI.md',
    'docs/SUPPORT.md',
    'examples/symfony-demo/README.md',
    'examples/symfony-demo/composer.json',
];

foreach ($requiredFiles as $file) {
    $expect(is_file($root.'/'.$file), sprintf('Missing release file: %s.', $file));
}

$forbidden = [
    'YOUR_GITHUB_USERNAME',
    '0.1.0-dev',
    '<repository-url>',
    'BEGIN OPENSSH PRIVATE KEY',
    'BEGIN RSA PRIVATE KEY',
    'BEGIN EC PRIVATE KEY',
];

$releaseFiles = [
    '.github/pull_request_template.md',
    'CHANGELOG.md',
    'CODE_OF_CONDUCT.md',
    'CONTRIBUTING.md',
    'README.md',
    'RELEASE.md',
    'SECURITY.md',
    'composer.json',
    'docs/CLI.md',
    'docs/SUPPORT.md',
    'src/Version.php',
];

foreach ($releaseFiles as $file) {
    $contents = (string) file_get_contents($root.'/'.$file);

    foreach ($forbidden as $needle) {
        $expect(
            !str_contains($contents, $needle),
            sprintf('Forbidden release trace "%s" found in %s.', $needle, $file),
        );
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, '[FAIL] '.$failure.PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, "PHPFlow v0.1.0 release checks passed.\n");
