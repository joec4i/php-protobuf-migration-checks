<?php

declare(strict_types=1);

use Google\Protobuf\Timestamp;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\exceptionContains;
use function Google\Protobuf\Tests\Discrepancy\returned;

return [
    case_('timestamp.from_datetime_immutable')
        ->description('Timestamp::fromDateTime() accepts only DateTime in php-impl but accepts DateTimeInterface in native.')
        ->severity('silent')
        ->code(<<<'PHP'
$timestamp = new Timestamp();
$timestamp->fromDateTime(new DateTimeImmutable('2020-01-01T00:00:00Z'));
return $timestamp->getSeconds();
PHP)
        ->migrationNote('Prefer `DateTimeInterface` at application boundaries, but if code must run on both implementations, convert DateTimeImmutable to DateTime before calling fromDateTime().')
        ->goodCode(<<<'PHP'
$datetime = $input instanceof DateTimeImmutable
    ? DateTime::createFromImmutable($input)
    : $input;
$timestamp->fromDateTime($datetime);
PHP)
        ->badCode(<<<'PHP'
$timestamp->fromDateTime(new DateTimeImmutable('2020-01-01T00:00:00Z'));
PHP)
        ->probe(static function (): mixed {
            $timestamp = new Timestamp();
            $timestamp->fromDateTime(new DateTimeImmutable('2020-01-01T00:00:00Z'));
            return $timestamp->getSeconds();
        })
        ->expectPhpImpl(
            exceptionContains('must be of type DateTime, DateTimeImmutable given')
        )
        ->expectNative(
            returned(1577836800)
        ),
];
