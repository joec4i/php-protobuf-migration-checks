<?php

declare(strict_types=1);

use Google\Protobuf\DoubleValue;
use Google\Protobuf\StringValue;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\exceptionContains;
use function Google\Protobuf\Tests\Discrepancy\returned;
use function Google\Protobuf\Tests\Discrepancy\warningContains;

return [
    case_('message.pure_only_public_methods')
        ->description('Several public Message helper methods exist in php-impl but are not available on native message objects.')
        ->severity('fatal')
        ->code(<<<'PHP'
$message = new DoubleValue(['value' => 1.5]);
return [
    '__debugInfo' => method_exists($message, '__debugInfo'),
    'byteSize' => method_exists($message, 'byteSize'),
    'jsonByteSize' => method_exists($message, 'jsonByteSize'),
    'parseFromStream' => method_exists($message, 'parseFromStream'),
    'serializeToStream' => method_exists($message, 'serializeToStream'),
];
PHP)
        ->migrationNote('Use portable public APIs: `serializeToString()`, `mergeFromString()`, `serializeToJsonString()`, `mergeFromJsonString()`, `clear()`, and `discardUnknownFields()`. Do not call php-impl-only stream/size/debug helpers.')
        ->goodCode(<<<'PHP'
$bytes = $message->serializeToString();
$json = $message->serializeToJsonString();
PHP)
        ->badCode(<<<'PHP'
$size = $message->byteSize();
$debug = $message->__debugInfo();
PHP)
        ->probe(static function (): mixed {
            $message = new DoubleValue(['value' => 1.5]);
            return [
                '__debugInfo' => method_exists($message, '__debugInfo'),
                'byteSize' => method_exists($message, 'byteSize'),
                'jsonByteSize' => method_exists($message, 'jsonByteSize'),
                'parseFromStream' => method_exists($message, 'parseFromStream'),
                'serializeToStream' => method_exists($message, 'serializeToStream'),
            ];
        })
        ->expectPhpImpl(
            returned([
                '__debugInfo' => true,
                'byteSize' => true,
                'jsonByteSize' => true,
                'parseFromStream' => true,
                'serializeToStream' => true,
            ])
        )
        ->expectNative(
            returned([
                '__debugInfo' => false,
                'byteSize' => false,
                'jsonByteSize' => false,
                'parseFromStream' => false,
                'serializeToStream' => false,
            ])
        ),

    case_('message.call_debug_info')
        ->description('Calling __debugInfo() returns structured debug data in php-impl but is an undefined method in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$message = new DoubleValue(['value' => 1.5]);
return $message->__debugInfo();
PHP)
        ->migrationNote('Do not call `__debugInfo()` directly for protobuf messages under native. Build explicit diagnostic arrays from getters instead.')
        ->goodCode(<<<'PHP'
$debug = [
    'value' => $message->getValue(),
];
PHP)
        ->badCode(<<<'PHP'
$debug = $message->__debugInfo();
PHP)
        ->probe(static function (): mixed {
            $message = new DoubleValue(['value' => 1.5]);
            return $message->__debugInfo();
        })
        ->expectPhpImpl(
            returned(['value' => 1.5])
        )
        ->expectNative(
            exceptionContains('Call to undefined method Google\Protobuf\DoubleValue::__debugInfo()')
        ),

    case_('message.call_byte_size')
        ->description('Message::byteSize() is public in php-impl but unavailable in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$message = new DoubleValue();
return $message->byteSize();
PHP)
        ->migrationNote('Avoid `byteSize()` in migration-safe code. If size is needed, serialize and measure the byte string.')
        ->goodCode(<<<'PHP'
$size = strlen($message->serializeToString());
PHP)
        ->badCode(<<<'PHP'
$size = $message->byteSize();
PHP)
        ->probe(static function (): mixed {
            $message = new DoubleValue();
            return $message->byteSize();
        })
        ->expectPhpImpl(
            returned(0)
        )
        ->expectNative(
            exceptionContains('Call to undefined method Google\Protobuf\DoubleValue::byteSize()')
        ),

    case_('message.merge_from_wrong_class')
        ->description('mergeFrom() with a different message class emits a non-fatal notice in php-impl but throws a TypeError in native.')
        ->severity('throw')
        ->code(<<<'PHP'
$target = new DoubleValue();
$target->mergeFrom(new StringValue());
return $target->getValue();
PHP)
        ->migrationNote('Only call `mergeFrom()` with the exact same generated message class. Validate dynamic message types before merging.')
        ->goodCode(<<<'PHP'
if (get_class($target) !== get_class($source)) {
    throw new InvalidArgumentException('cannot merge different message classes');
}
$target->mergeFrom($source);
PHP)
        ->badCode(<<<'PHP'
$target->mergeFrom($source);
PHP)
        ->probe(static function (): mixed {
            $target = new DoubleValue();
            $target->mergeFrom(new StringValue());
            return $target->getValue();
        })
        ->expectPhpImpl(
            returned(0),
            warningContains('Cannot merge messages with different class')
        )
        ->expectNative(
            exceptionContains('must be of type Google\Protobuf\DoubleValue, Google\Protobuf\StringValue given')
        ),

    case_('message.constructor_non_array')
        ->description('Message constructors reject non-array data with different exception classes/messages.')
        ->severity('type difference')
        ->code(<<<'PHP'
new DoubleValue('not an array');
return true;
PHP)
        ->migrationNote('Pass only arrays or null to generated message constructors; do not branch on the exact exception type/message for invalid constructor input.')
        ->goodCode(<<<'PHP'
$message = new DoubleValue(['value' => 1.5]);
PHP)
        ->badCode(<<<'PHP'
$message = new DoubleValue($possiblyScalar);
PHP)
        ->probe(static function (): mixed {
            new DoubleValue('not an array');
            return true;
        })
        ->expectPhpImpl(
            exceptionContains('Message constructor must be an array or null')
        )
        ->expectNative(
            exceptionContains('must be of type ?array, string given')
        ),

    case_('message.constructor_unknown_field')
        ->description('Unknown constructor array fields throw different exception classes/messages.')
        ->severity('type difference')
        ->code(<<<'PHP'
new DoubleValue(['bad' => 1]);
return true;
PHP)
        ->migrationNote('Validate constructor array keys against generated field names if invalid input is expected; do not rely on php-impl exception details.')
        ->goodCode(<<<'PHP'
$message = new DoubleValue(['value' => 1.5]);
PHP)
        ->badCode(<<<'PHP'
$message = new DoubleValue($unvalidatedData);
PHP)
        ->probe(static function (): mixed {
            new DoubleValue(['bad' => 1]);
            return true;
        })
        ->expectPhpImpl(
            exceptionContains('Invalid message property: bad')
        )
        ->expectNative(
            exceptionContains('No such field bad')
        ),
];
