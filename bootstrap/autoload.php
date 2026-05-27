<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$srcRoot = $repoRoot . '/protobuf/php/src';

spl_autoload_register(static function (string $class) use ($srcRoot): void {
    $prefixes = [
        'Google\\Protobuf\\' => $srcRoot . '/Google/Protobuf/',
        'GPBMetadata\\' => $srcRoot . '/GPBMetadata/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});
