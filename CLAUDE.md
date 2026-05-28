# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A catalogue of executable test cases that document runtime behavior differences between the Composer `google/protobuf` pure-PHP runtime and the native `ext-protobuf` extension. The output is a generated migration report (`REPORT.md`) showing, per case: probe code, what each runtime returned/threw/fatal'd, and GOOD/BAD migration examples.

The `protobuf/` directory is a git submodule pointing at `protocolbuffers/protobuf`. Its `php/src` tree provides the pure-PHP classes used by the `php-impl` runtime (loaded via a custom PSR-4 autoloader in `bootstrap/autoload.php`). The repo will not run without it:

```sh
git submodule update --init --recursive
```

## Commands

- `make check` / `php runner.php` — run every case, print PASS/FAIL per (case, mode), exit non-zero on any failure.
- `make update` — regenerate the checked-in `REPORT.md` (wraps `php runner.php --markdown=REPORT.md`).
- `make lint` — `php -l` every PHP file outside the `protobuf/` submodule.
- `php runner.php --markdown` — print the markdown report to stdout (no file writes, no PASS/FAIL console output).
- `php runner.php map.missing_key repeated.int32_overflow` — run only the listed case IDs.

There is no test runner beyond `runner.php` itself — the discrepancy cases *are* the tests.

## Architecture

### Two-process execution model

`runner.php` never executes case probes in-process. For each case it spawns two child PHP processes via `proc_open`:

- **`php-impl` mode** — invoked as `php -n child.php …` so `ext-protobuf` cannot be loaded; `bootstrap/php_impl.php` asserts the extension is absent and the autoloader pulls classes from `protobuf/php/src`.
- **`native` mode** — invoked with the current PHP configuration; `bootstrap/native.php` asserts `ext-protobuf` *is* loaded, so the same `Google\Protobuf\…` class names resolve to the extension's implementations.

This isolation is the only reliable way to exercise both runtimes inside one run, because the two implementations share class names and cannot coexist in a single process. Adding code that runs probes in-process (in `runner.php`) breaks this guarantee — always go through `child.php`.

`child.php` is a sandbox: it installs an error handler and shutdown handler that capture warnings, fatals, exceptions, stdout, and the probe's return value into a single JSON observation written to stdout. `runner.php` then parses that JSON (`decode_observation` finds the first `{` and JSON-decodes from there, so probes are free to `echo` before returning) and matches it against the case's expectation rules.

### Cases and the fluent builder

Each case is a `CaseDefinition` (see `src/CaseDefinition.php`) built with a fluent API. Files in `cases/*.php` return arrays of these; `cases/index.php` aggregates them into a single registry keyed by ID. **A new case file must be added to the `cases/index.php` include list** — there is no glob.

Pattern:

```php
case_('group.short_name')
    ->description('One-sentence summary used in REPORT.md.')
    ->severity('fatal'|'throw'|'warning'|…)   // free-form label shown in the report
    ->code(<<<'PHP' …source shown in the report… PHP)
    ->probe(static function (): mixed { …actually executed by child.php… })
    ->migrationNote('…')
    ->goodCode('…')->badCode('…')             // both rendered in the Migration Example block
    ->expectPhpImpl(returned([…]), warningContains('…'))
    ->expectNative(fatalContains('…')),
```

Expectation helpers (`returned`, `warningContains`, `exceptionContains`, `fatalContains`, `stderrContains`, `exitCode`) live alongside `CaseDefinition` in the `Google\Protobuf\Tests\Discrepancy` namespace. The `code()` string is purely for display in `REPORT.md`; only `probe()` is executed. Keep them in sync by hand.

`returned()` values are passed through `normalize_value()`, which keeps scalars/arrays as-is but converts objects to `['__type' => …, '__string' => …]`. Match that shape if a probe returns objects.

### Report rendering

`render_markdown()` in `runner.php` produces `REPORT.md`. When both modes return arrays it skips the summary table and shows only the raw JSON observations side-by-side; otherwise it renders a 2-row outcome table followed by a collapsed raw-JSON `<details>` block. This is intentional — array diffs read better as raw JSON than as a one-line table cell.
