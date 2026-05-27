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

    case_('message.setValue_string')
        ->description('Generated scalar setters reject string input in php-impl but coerce compatible strings in native.')
        ->severity('type difference')
        ->code(<<<'PHP'
$message = new DoubleValue();
$message->setValue('1.5');
return $message->getValue();
PHP)
        ->migrationNote('Cast numeric strings before calling generated scalar setters. Native may coerce strings that php-impl rejects at the type level.')
        ->goodCode(<<<'PHP'
$message->setValue((float) $value);
PHP)
        ->badCode(<<<'PHP'
$message->setValue($value);
PHP)
        ->probe(static function (): mixed {
            $message = new DoubleValue();
            $message->setValue('1.5');
            return $message->getValue();
        })
        ->expectPhpImpl(
            exceptionContains('must be of type float, string given')
        )
        ->expectNative(
            returned(1.5)
        ),

    case_('message.setValue_null')
        ->description('Generated scalar setters reject null in php-impl but attempt conversion in native.')
        ->severity('throw')
        ->code(<<<'PHP'
$message = new DoubleValue();
$message->setValue(null);
return $message->getValue();
PHP)
        ->migrationNote('Normalize nullable scalars before calling generated setters. Null handling is not portable across implementations.')
        ->goodCode(<<<'PHP'
if ($value !== null) {
    $message->setValue($value);
}
PHP)
        ->badCode(<<<'PHP'
$message->setValue($value);
PHP)
        ->probe(static function (): mixed {
            $message = new DoubleValue();
            $message->setValue(null);
            return $message->getValue();
        })
        ->expectPhpImpl(
            exceptionContains('must be of type float, null given')
        )
        ->expectNative(
            exceptionContains("Cannot convert '' to double")
        ),

    case_('message.mergeFromString_garbage')
        ->description('mergeFromString() silently accepts invalid wire bytes in php-impl but throws in native.')
        ->severity('throw')
        ->code(<<<'PHP'
$message = new DoubleValue();
$message->mergeFromString("\xff\xff");
return 'parsed';
PHP)
        ->migrationNote('Treat mergeFromString() input as untrusted only when you can handle parse failures. Native rejects malformed wire payloads that php-impl may ignore.')
        ->goodCode(<<<'PHP'
try {
    $message->mergeFromString($bytes);
} catch (Exception $e) {
    throw new InvalidArgumentException('invalid protobuf payload', 0, $e);
}
PHP)
        ->badCode(<<<'PHP'
$message->mergeFromString($bytes);
PHP)
        ->probe(static function (): mixed {
            $message = new DoubleValue();
            $message->mergeFromString("\xff\xff");
            return 'parsed';
        })
        ->expectPhpImpl(
            returned('parsed')
        )
        ->expectNative(
            exceptionContains('Error occurred during parsing')
        ),

    case_('message.mergeFromJsonString_unknown_field')
        ->description('mergeFromJsonString() with an unknown field fails differently: php-impl throws while coercing parsed data, native rejects during JSON parsing.')
        ->severity('type difference')
        ->code(<<<'PHP'
$message = new DoubleValue();
$message->mergeFromJsonString('{"value":1.5,"unknown":true}');
return $message->serializeToJsonString();
PHP)
        ->migrationNote('Do not rely on unknown JSON fields being ignored. Strip or validate JSON payloads before mergeFromJsonString().')
        ->goodCode(<<<'PHP'
$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
unset($data['unknown']);
$message->mergeFromJsonString(json_encode($data, JSON_THROW_ON_ERROR));
PHP)
        ->badCode(<<<'PHP'
$message->mergeFromJsonString($jsonWithUnknownFields);
PHP)
        ->probe(static function (): mixed {
            $message = new DoubleValue();
            $message->mergeFromJsonString('{"value":1.5,"unknown":true}');
            return $message->serializeToJsonString();
        })
        ->expectPhpImpl(
            exceptionContains('must be of type float, array given')
        )
        ->expectNative(
            exceptionContains('Error occurred during parsing')
        ),
];
