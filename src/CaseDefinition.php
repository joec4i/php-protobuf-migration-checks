<?php

declare(strict_types=1);

namespace Google\Protobuf\Tests\Discrepancy;

final class CaseDefinition
{
    private string $description = '';
    private string $severity = '';
    private string $code = '';
    private string $migrationNote = '';
    private string $badCode = '';
    private string $goodCode = '';
    private $probe;

    /** @var array<string, list<array<string, mixed>>> */
    private array $expectations = [];

    public function __construct(private readonly string $id)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function severity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function code(string $code): self
    {
        $this->code = trim($code);
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function migrationNote(string $migrationNote): self
    {
        $this->migrationNote = trim($migrationNote);
        return $this;
    }

    public function getMigrationNote(): string
    {
        return $this->migrationNote;
    }

    public function badCode(string $badCode): self
    {
        $this->badCode = trim($badCode);
        return $this;
    }

    public function getBadCode(): string
    {
        return $this->badCode;
    }

    public function goodCode(string $goodCode): self
    {
        $this->goodCode = trim($goodCode);
        return $this;
    }

    public function getGoodCode(): string
    {
        return $this->goodCode;
    }

    public function probe(callable $probe): self
    {
        $this->probe = $probe;
        return $this;
    }

    public function expectPhpImpl(array ...$rules): self
    {
        $this->expectations['php-impl'] = $rules;
        return $this;
    }

    public function expectNative(array ...$rules): self
    {
        $this->expectations['native'] = $rules;
        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function expectationsFor(string $mode): array
    {
        return $this->expectations[$mode] ?? [];
    }

    public function run(): mixed
    {
        if ($this->probe === null) {
            throw new \LogicException("Case {$this->id} has no probe.");
        }

        return ($this->probe)();
    }
}

function case_(string $id): CaseDefinition
{
    return new CaseDefinition($id);
}

function returned(mixed $value): array
{
    return ['kind' => 'returned', 'value' => normalize_value($value)];
}

function warningContains(string $message): array
{
    return ['kind' => 'warning_contains', 'message' => $message];
}

function exceptionContains(string $message): array
{
    return ['kind' => 'exception_contains', 'message' => $message];
}

function fatalContains(string $message): array
{
    return ['kind' => 'fatal_contains', 'message' => $message];
}

function stderrContains(string $message): array
{
    return ['kind' => 'stderr_contains', 'message' => $message];
}

function exitCode(int $exitCode): array
{
    return ['kind' => 'exit_code', 'value' => $exitCode];
}

function normalize_value(mixed $value): mixed
{
    if (is_scalar($value) || $value === null) {
        return $value;
    }

    if (is_array($value)) {
        return array_map(__NAMESPACE__ . '\\normalize_value', $value);
    }

    return [
        '__type' => get_debug_type($value),
        '__string' => method_exists($value, '__toString') ? (string) $value : null,
    ];
}
