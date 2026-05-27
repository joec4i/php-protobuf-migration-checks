<?php

declare(strict_types=1);

use Google\Protobuf\Tests\Discrepancy\CaseDefinition;

require_once __DIR__ . '/src/CaseDefinition.php';

$emitted = false;
$warnings = [];

function take_buffer(): string
{
    if (ob_get_level() === 0) {
        return '';
    }

    $buffer = ob_get_clean();
    return is_string($buffer) ? $buffer : '';
}

function emit(array $payload): void
{
    global $emitted;
    $emitted = true;
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
}

ob_start();

register_shutdown_function(static function () use (&$warnings): void {
    global $emitted;

    $buffer = take_buffer();
    if ($emitted) {
        echo $buffer;
        return;
    }

    $last = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($last !== null && in_array($last['type'], $fatalTypes, true)) {
        emit([
            'status' => 'fatal',
            'return' => null,
            'return_type' => null,
            'warnings' => $warnings,
            'exception' => null,
            'fatal' => [
                'type' => $last['type'],
                'message' => $last['message'],
                'file' => $last['file'],
                'line' => $last['line'],
            ],
            'stdout' => $buffer,
        ]);
    } elseif ($buffer !== '') {
        echo $buffer;
    }
});

set_error_handler(static function (
    int $errno,
    string $errstr,
    string $file,
    int $line
) use (&$warnings): bool {
    if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return false;
    }

    $warnings[] = [
        'type' => $errno,
        'message' => $errstr,
        'file' => $file,
        'line' => $line,
    ];
    return true;
});

$options = getopt('', ['mode:', 'case:']);
$mode = $options['mode'] ?? null;
$caseId = $options['case'] ?? null;

if ($mode === null || $caseId === null) {
    emit([
        'status' => 'harness_error',
        'message' => 'Usage: child.php --mode=php-impl|native --case=<id>',
    ]);
    exit(2);
}

try {
    if ($mode === 'php-impl') {
        require __DIR__ . '/bootstrap/php_impl.php';
    } elseif ($mode === 'native') {
        require __DIR__ . '/bootstrap/native.php';
    } else {
        throw new RuntimeException("Unknown mode: {$mode}");
    }

    /** @var array<string, CaseDefinition> $registry */
    $registry = require __DIR__ . '/cases/index.php';
    if (!isset($registry[$caseId])) {
        throw new RuntimeException("Unknown case: {$caseId}");
    }

    $value = $registry[$caseId]->run();
    $buffer = take_buffer();

    emit([
        'status' => 'returned',
        'return' => Google\Protobuf\Tests\Discrepancy\normalize_value($value),
        'return_type' => get_debug_type($value),
        'warnings' => $warnings,
        'exception' => null,
        'fatal' => null,
        'stdout' => $buffer,
    ]);
} catch (Throwable $e) {
    $buffer = take_buffer();

    emit([
        'status' => 'threw',
        'return' => null,
        'return_type' => null,
        'warnings' => $warnings,
        'exception' => [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ],
        'fatal' => null,
        'stdout' => $buffer,
    ]);
}
