<?php

declare(strict_types=1);

use Google\Protobuf\Tests\Discrepancy\CaseDefinition;

require_once __DIR__ . '/src/CaseDefinition.php';

/** @var array<string, CaseDefinition> $cases */
$cases = require __DIR__ . '/cases/index.php';

[$requested, $markdownPath, $markdownToStdout] = parse_arguments($argv, $cases);
$emitConsole = !$markdownToStdout;

$failures = 0;
$runs = [];

foreach ($requested as $caseId) {
    if (!isset($cases[$caseId])) {
        if ($emitConsole) {
            fwrite(STDERR, "Unknown case: {$caseId}\n");
        }
        $failures++;
        continue;
    }

    $case = $cases[$caseId];
    $results = [
        'php-impl' => run_child('php-impl', $caseId),
        'native' => run_child('native', $caseId),
    ];

    $casePassed = true;
    foreach ($results as $mode => $result) {
        $modePassed = check_expectations($result, $case->expectationsFor($mode), $details);
        $casePassed = $casePassed && $modePassed;

        if ($emitConsole) {
            printf(
                "%s %-8s %-24s exit=%s status=%s %s\n",
                $modePassed ? 'PASS' : 'FAIL',
                $mode,
                $caseId,
                (string) $result['exit_code'],
                $result['observation']['status'] ?? 'no-json',
                $details
            );

            if (!$modePassed) {
                print_diagnostics($result);
            }
        }
    }

    $runs[$caseId] = [
        'case' => $case,
        'results' => $results,
        'passed' => $casePassed,
    ];

    if (!$casePassed) {
        $failures++;
    }
}

if ($markdownToStdout || $markdownPath !== null) {
    $markdown = render_markdown($runs);
    if ($markdownToStdout) {
        echo $markdown;
    } else {
        file_put_contents($markdownPath, $markdown);
        if ($emitConsole) {
            echo "Wrote markdown report to {$markdownPath}\n";
        }
    }
}

exit($failures === 0 ? 0 : 1);

/** @param array<string, CaseDefinition> $cases */
function parse_arguments(array $argv, array $cases): array
{
    $caseIds = [];
    $markdownPath = null;
    $markdownToStdout = false;

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--markdown') {
            $markdownToStdout = true;
            continue;
        }

        if (str_starts_with($arg, '--markdown=')) {
            $markdownPath = substr($arg, strlen('--markdown='));
            continue;
        }

        $caseIds[] = $arg;
    }

    if ($caseIds === []) {
        $caseIds = array_keys($cases);
    }

    return [$caseIds, $markdownPath, $markdownToStdout];
}

/** @return array{exit_code:int, stdout:string, stderr:string, observation:?array} */
function run_child(string $mode, string $caseId): array
{
    $php = PHP_BINARY;
    $args = $mode === 'php-impl'
        ? [$php, '-n', __DIR__ . '/child.php', '--mode=' . $mode, '--case=' . $caseId]
        : [$php, __DIR__ . '/child.php', '--mode=' . $mode, '--case=' . $caseId];

    $cmd = implode(' ', array_map('escapeshellarg', $args));
    $proc = proc_open($cmd, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, __DIR__);

    if (!is_resource($proc)) {
        throw new RuntimeException("Failed to start child process for {$mode} {$caseId}");
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($proc);

    return [
        'exit_code' => $exitCode,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'observation' => decode_observation(is_string($stdout) ? $stdout : ''),
    ];
}

function decode_observation(string $stdout): ?array
{
    $length = strlen($stdout);
    for ($i = 0; $i < $length; $i++) {
        if ($stdout[$i] !== '{') {
            continue;
        }

        $decoded = json_decode(substr($stdout, $i), true);
        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }

    return null;
}

/**
 * @param array{exit_code:int, stdout:string, stderr:string, observation:?array} $result
 * @param list<array<string, mixed>> $rules
 */
function check_expectations(array $result, array $rules, ?string &$details): bool
{
    $observation = $result['observation'] ?? [
        'status' => 'no-json',
        'return' => null,
        'return_type' => null,
        'warnings' => [],
        'exception' => null,
        'fatal' => null,
    ];
    $observation['__exit_code'] = $result['exit_code'];
    $observation['__stderr'] = $result['stderr'];

    $messages = [];
    foreach ($rules as $rule) {
        if (!rule_matches($observation, $rule)) {
            $messages[] = 'missing ' . describe_rule($rule);
        }
    }

    if ($messages !== []) {
        $details = implode('; ', $messages);
        return false;
    }

    $details = summarize_observation($observation);
    return true;
}

/** @param array<string, mixed> $observation */
function rule_matches(array $observation, array $rule): bool
{
    return match ($rule['kind']) {
        'returned' => ($observation['status'] ?? null) === 'returned'
            && (($observation['return'] ?? null) === $rule['value']),
        'warning_contains' => contains_in_records($observation['warnings'] ?? [], $rule['message']),
        'exception_contains' => ($observation['status'] ?? null) === 'threw'
            && str_contains((string) ($observation['exception']['message'] ?? ''), $rule['message']),
        'fatal_contains' => ($observation['status'] ?? null) === 'fatal'
            && str_contains((string) ($observation['fatal']['message'] ?? ''), $rule['message']),
        'stderr_contains' => str_contains((string) ($observation['__stderr'] ?? ''), $rule['message']),
        'exit_code' => ($observation['__exit_code'] ?? null) === $rule['value'],
        default => false,
    };
}

/** @param list<array<string, mixed>> $records */
function contains_in_records(array $records, string $needle): bool
{
    foreach ($records as $record) {
        if (str_contains((string) ($record['message'] ?? ''), $needle)) {
            return true;
        }
    }

    return false;
}

/** @param array<string, mixed> $rule */
function describe_rule(array $rule): string
{
    return match ($rule['kind']) {
        'returned' => 'return=' . var_export($rule['value'], true),
        'warning_contains' => 'warning containing "' . $rule['message'] . '"',
        'exception_contains' => 'exception containing "' . $rule['message'] . '"',
        'fatal_contains' => 'fatal containing "' . $rule['message'] . '"',
        'stderr_contains' => 'stderr containing "' . $rule['message'] . '"',
        'exit_code' => 'exit=' . $rule['value'],
        default => 'unknown rule',
    };
}

/** @param array<string, mixed> $observation */
function summarize_observation(array $observation): string
{
    $status = $observation['status'] ?? 'unknown';
    if ($status === 'returned') {
        return 'return=' . var_export($observation['return'] ?? null, true);
    }

    if ($status === 'fatal') {
        return 'fatal="' . ($observation['fatal']['message'] ?? '') . '"';
    }

    if ($status === 'threw') {
        return 'exception="' . ($observation['exception']['message'] ?? '') . '"';
    }

    return '';
}

/** @param array{exit_code:int, stdout:string, stderr:string, observation:?array} $result */
function print_diagnostics(array $result): void
{
    if ($result['stdout'] !== '') {
        echo "stdout:\n{$result['stdout']}\n";
    }

    if ($result['stderr'] !== '') {
        echo "stderr:\n{$result['stderr']}\n";
    }
}

/** @param array<string, array{case:CaseDefinition, results:array<string, array>, passed:bool}> $runs */
function render_markdown(array $runs): string
{
    $out = "# Protobuf PHP Implementation Discrepancy Report\n\n";
    $out .= "This report is generated from executable discrepancy cases. Each case runs once with the userland PHP implementation and once with the native `ext-protobuf` implementation.\n\n";

    foreach ($runs as $caseId => $run) {
        /** @var CaseDefinition $case */
        $case = $run['case'];
        $out .= "## `{$caseId}`\n\n";
        $out .= "**Severity:** `{$case->getSeverity()}`\n\n";
        $out .= "**Description:** {$case->getDescription()}\n\n";

        $out .= "### Probe Code\n\n";
        $out .= fenced('php', $case->getCode()) . "\n\n";

        if (both_modes_returned_arrays($run['results'])) {
            $out .= "### Raw Output Comparison\n\n";
            $out .= render_raw_output_comparison($run['results']);
        } else {
            $out .= "### Output Comparison\n\n";
            $out .= render_output_table($run['results']) . "\n\n";

            $out .= "<details>\n<summary>Raw Output</summary>\n\n";
            $out .= render_raw_output_comparison($run['results']);
            $out .= "</details>\n\n";
        }

        if ($case->getMigrationNote() !== '') {
            $out .= "### Migration Note\n\n";
            $out .= $case->getMigrationNote() . "\n\n";
        }

        if ($case->getBadCode() !== '' || $case->getGoodCode() !== '') {
            $out .= "### Migration Example\n\n";
            $out .= fenced('php', migration_example_code($case)) . "\n\n";
        }
    }

    return $out;
}

/** @param array<string, array{exit_code:int, stdout:string, stderr:string, observation:?array}> $results */
function both_modes_returned_arrays(array $results): bool
{
    foreach (['php-impl', 'native'] as $mode) {
        $result = $results[$mode] ?? null;
        $observation = $result['observation'] ?? null;
        if (
            !is_array($result)
            || !is_array($observation)
            || $result['exit_code'] !== 0
            || ($observation['status'] ?? null) !== 'returned'
            || ($observation['return_type'] ?? null) !== 'array'
        ) {
            return false;
        }
    }

    return true;
}

/** @param array<string, array{exit_code:int, stdout:string, stderr:string, observation:?array}> $results */
function render_raw_output_comparison(array $results): string
{
    $out = "#### php-impl JSON\n\n";
    $out .= fenced('json', report_output($results['php-impl'])) . "\n\n";
    $out .= "#### native JSON\n\n";
    $out .= fenced('json', report_output($results['native'])) . "\n\n";
    return $out;
}

function migration_example_code(CaseDefinition $case): string
{
    $sections = [];

    if ($case->getGoodCode() !== '') {
        $sections[] = "// GOOD\n" . $case->getGoodCode();
    }

    if ($case->getBadCode() !== '') {
        $sections[] = "// BAD\n" . $case->getBadCode();
    }

    return implode("\n\n", $sections);
}

/** @param array<string, array{exit_code:int, stdout:string, stderr:string, observation:?array}> $results */
function render_output_table(array $results): string
{
    $rows = [
        '| Runtime | Exit | Outcome |',
        '|---|---:|---|',
    ];

    foreach (['php-impl', 'native'] as $mode) {
        $result = $results[$mode];
        $observation = $result['observation'] ?? [];
        $rows[] = sprintf(
            '| `%s` | `%s` | %s |',
            $mode,
            (string) $result['exit_code'],
            markdown_code_cell(format_outcome_cell($observation, $result))
        );
    }

    return implode("\n", $rows);
}

/** @param array<string, mixed> $observation */
function format_return_cell(array $observation): string
{
    if (($observation['status'] ?? null) !== 'returned') {
        return '';
    }

    return ($observation['return_type'] ?? 'unknown') . ': ' . var_export($observation['return'] ?? null, true);
}

/**
 * @param array<string, mixed> $observation
 * @param array{exit_code:int, stdout:string, stderr:string, observation:?array} $result
 */
function format_outcome_cell(array $observation, array $result): string
{
    if ($observation === []) {
        $stderr = trim($result['stderr']);
        return $stderr === ''
            ? 'No harness JSON.'
            : 'No harness JSON; stderr: ' . $stderr;
    }

    $fatal = $observation['fatal'] ?? null;
    if (is_array($fatal)) {
        return 'Fatal: ' . ($fatal['message'] ?? '');
    }

    $exception = $observation['exception'] ?? null;
    if (is_array($exception)) {
        return 'Exception: ' . ($exception['message'] ?? '');
    }

    $warnings = $observation['warnings'] ?? [];
    if ($warnings !== []) {
        return implode('; ', array_map(
            static fn (array $warning): string => 'Warning: ' . ($warning['message'] ?? ''),
            $warnings
        ));
    }

    $prefix = '';
    if (($observation['status'] ?? null) === 'returned') {
        $return = format_return_cell($observation);
        $prefix = $return === '' ? 'Returned.' : 'Returned ' . $return . '.';
    }

    return $prefix === '' ? 'ok' : $prefix;
}

/** @param array{exit_code:int, stdout:string, stderr:string, observation:?array} $result */
function report_output(array $result): string
{
    $observation = $result['observation'] ?? [];
    $payload = [
        'exit_code' => $result['exit_code'],
        'status' => $observation['status'] ?? 'no-json',
        'return' => $observation['return'] ?? null,
        'return_type' => $observation['return_type'] ?? null,
        'warnings' => $observation['warnings'] ?? [],
        'exception' => $observation['exception'] ?? null,
        'fatal' => $observation['fatal'] ?? null,
    ];

    if (trim($result['stderr']) !== '') {
        $payload['stderr'] = trim($result['stderr']);
    }

    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function fenced(string $language, string $content): string
{
    if ($content === '') {
        $content = '(empty)';
    }

    return "```{$language}\n{$content}\n```";
}

function markdown_code_cell(string $value): string
{
    if ($value === '') {
        return '';
    }

    return '`' . markdown_inline($value) . '`';
}

function markdown_inline(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = str_replace('|', '\\|', $value);
    return str_replace('`', '\\`', $value);
}
