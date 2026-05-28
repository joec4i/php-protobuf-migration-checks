<?php

declare(strict_types=1);

use Google\Protobuf\Any;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\exceptionContains;
use function Google\Protobuf\Tests\Discrepancy\fatalContains;

return [
    case_('wkt.any_unpack_missing_prefix')
        ->description('Any::unpack() rejects a type_url without the expected prefix in both runtimes, but the php-impl error message has a misspelling ("qulified") that native does not.')
        ->severity('type difference')
        ->code(<<<'PHP'
$any = new Any();
$any->setTypeUrl('google.protobuf.DoubleValue');
$any->unpack();
return 'unreachable';
PHP)
        ->migrationNote('Do not match on Any::unpack() exception messages. Validate type_url prefixes yourself before unpacking if you need stable error wording.')
        ->goodCode(<<<'PHP'
if (!str_starts_with($any->getTypeUrl(), 'type.googleapis.com/')) {
    throw new InvalidArgumentException('unexpected type_url');
}
$message = $any->unpack();
PHP)
        ->badCode(<<<'PHP'
try {
    $message = $any->unpack();
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'qulified')) {
        // brittle: php-impl typo that native does not emit
    }
}
PHP)
        ->probe(static function (): mixed {
            $any = new Any();
            $any->setTypeUrl('google.protobuf.DoubleValue');
            $any->unpack();
            return 'unreachable';
        })
        ->expectPhpImpl(
            exceptionContains('type.googleapis.com/fully-qulified')
        )
        ->expectNative(
            exceptionContains('type.googleapis.com/fully-qualified')
        ),

    case_('wkt.any_unpack_unregistered')
        ->description('Any::unpack() against a type_url whose message is not in the descriptor pool throws different messages in each runtime.')
        ->severity('type difference')
        ->code(<<<'PHP'
$any = new Any();
$any->setTypeUrl('type.googleapis.com/example.NotRegistered');
$any->unpack();
return 'unreachable';
PHP)
        ->migrationNote('When you cannot guarantee the target message class is loaded, gate unpack() on `is($class)` rather than catching exceptions by message.')
        ->goodCode(<<<'PHP'
if ($any->is(DoubleValue::class)) {
    $message = $any->unpack();
}
PHP)
        ->badCode(<<<'PHP'
try {
    $message = $any->unpack();
} catch (Exception $e) {
    if (str_contains($e->getMessage(), "hasn't been added to descriptor pool")) {
        // wording differs between runtimes
    }
}
PHP)
        ->probe(static function (): mixed {
            $any = new Any();
            $any->setTypeUrl('type.googleapis.com/example.NotRegistered');
            $any->unpack();
            return 'unreachable';
        })
        ->expectPhpImpl(
            exceptionContains("Class example.NotRegistered hasn't been added to descriptor pool")
        )
        ->expectNative(
            exceptionContains("Specified message in any hasn't been added to descriptor pool")
        ),

    case_('wkt.any_pack_non_message')
        ->description('Any::pack() with a non-Message argument fatals in php-impl via trigger_error(E_USER_ERROR) but throws a TypeError in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$any = new Any();
$any->pack('not a message');
return 'unreachable';
PHP)
        ->migrationNote('Always pass a Message instance to Any::pack(). php-impl produces a non-catchable fatal; native throws a catchable TypeError. Do not rely on either failure shape.')
        ->goodCode(<<<'PHP'
if ($payload instanceof Message) {
    $any->pack($payload);
}
PHP)
        ->badCode(<<<'PHP'
$any->pack($payload);
PHP)
        ->probe(static function (): mixed {
            $any = new Any();
            $any->pack('not a message');
            return 'unreachable';
        })
        ->expectPhpImpl(
            fatalContains('Given parameter is not a message instance')
        )
        ->expectNative(
            exceptionContains('must be of type object, string given')
        ),
];
