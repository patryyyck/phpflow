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

$requiredFiles = [
    'CHANGELOG.md',
    'LICENSE',
    'README.md',
    'RELEASE.md',
    'docs/CLI.md',
    'docs/SUPPORT.md',
];

foreach ($requiredFiles as $file) {
    $expect(is_file($root.'/'.$file), sprintf('Missing release file: %s.', $file));
}

$forbidden = [
    'YOUR_GITHUB_USERNAME',
    '0.1.0-dev',
    'BEGIN OPENSSH PRIVATE KEY',
    'BEGIN RSA PRIVATE KEY',
    'BEGIN EC PRIVATE KEY',
];

$releaseFiles = [
    'CHANGELOG.md',
    'README.md',
    'RELEASE.md',
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
