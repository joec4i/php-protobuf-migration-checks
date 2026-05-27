# PHP Protobuf Migration Checks

Executable checks and markdown reporting for migrating PHP code from the
Composer `google/protobuf` pure-PHP runtime to the native `ext-protobuf`
runtime.

## Context

The native `ext-protobuf` extension is intended to be a drop-in replacement for
the Composer `google/protobuf` package, but it is not perfectly drop-in
compatible in practice. The two runtimes expose different behavior for some
public methods, array operators, validation helpers, descriptor APIs, and edge
input handling.

This repository catalogs those runtime behavior discrepancies as executable
test cases. Each case records:

- The probe code.
- The pure-PHP `php-impl` result.
- The native `ext-protobuf` result.
- A migration note with GOOD/BAD usage examples.

The generated migration report is checked in as [REPORT.md](REPORT.md).

## Setup

```sh
git submodule update --init --recursive
```

The `protobuf/` submodule is expected to contain the upstream protobuf repo.

## Run

Run every case:

```sh
php runner.php
```

Generate markdown to stdout:

```sh
php runner.php --markdown
```

Write markdown to a file:

```sh
php runner.php --markdown=discrepancies.md
```

Update the checked-in report:

```sh
make update
```

Run selected cases:

```sh
php runner.php map.missing_key repeated.int32_overflow
```

`php-impl` mode is executed with `php -n` so the protobuf extension is not
loaded. `native` mode uses the current PHP configuration and requires
`ext-protobuf`.
