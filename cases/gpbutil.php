<?php

declare(strict_types=1);

use Google\Protobuf\DoubleValue;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\exceptionContains;
use function Google\Protobuf\Tests\Discrepancy\returned;

return [
    case_('gpbutil.check_int32_noop_native')
        ->description('GPBUtil::checkInt32() coerces by reference in php-impl but is a no-op placeholder in native.')
        ->severity('silent')
        ->code(<<<'PHP'
$value = '123';
GPBUtil::checkInt32($value);
return $value;
PHP)
        ->migrationNote('Do not call GPBUtil check methods as application validators. They are internal generated-code hooks and primitive checks are no-ops under native.')
        ->goodCode(<<<'PHP'
if (!is_int($value)) {
    throw new InvalidArgumentException('expected int32');
}
$message->setId($value);
PHP)
        ->badCode(<<<'PHP'
GPBUtil::checkInt32($value);
$message->setId($value);
PHP)
        ->probe(static function (): mixed {
            $value = '123';
            GPBUtil::checkInt32($value);
            return $value;
        })
        ->expectPhpImpl(
            returned(123)
        )
        ->expectNative(
            returned('123')
        ),

    case_('gpbutil.check_string_null_noop_native')
        ->description('GPBUtil::checkString() coerces null to an empty string in php-impl but leaves null unchanged in native.')
        ->severity('silent')
        ->code(<<<'PHP'
$value = null;
GPBUtil::checkString($value, true);
return $value;
PHP)
        ->migrationNote('Normalize nullable strings in application code; GPBUtil does not provide portable coercion.')
        ->goodCode(<<<'PHP'
$value = $value ?? '';
$message->setName($value);
PHP)
        ->badCode(<<<'PHP'
GPBUtil::checkString($value, true);
$message->setName($value);
PHP)
        ->probe(static function (): mixed {
            $value = null;
            GPBUtil::checkString($value, true);
            return $value;
        })
        ->expectPhpImpl(
            returned('')
        )
        ->expectNative(
            returned(null)
        ),

    case_('gpbutil.check_bool_null_noop_native')
        ->description('GPBUtil::checkBool() coerces null to false in php-impl but leaves null unchanged in native.')
        ->severity('silent')
        ->code(<<<'PHP'
$value = null;
GPBUtil::checkBool($value);
return $value;
PHP)
        ->migrationNote('Normalize nullable booleans in application code; GPBUtil does not provide portable coercion.')
        ->goodCode(<<<'PHP'
$value = $value !== null ? (bool) $value : false;
$message->setEnabled($value);
PHP)
        ->badCode(<<<'PHP'
GPBUtil::checkBool($value);
$message->setEnabled($value);
PHP)
        ->probe(static function (): mixed {
            $value = null;
            GPBUtil::checkBool($value);
            return $value;
        })
        ->expectPhpImpl(
            returned(false)
        )
        ->expectNative(
            returned(null)
        ),

    case_('gpbutil.check_message_noop_native')
        ->description('GPBUtil::checkMessage() rejects the wrong type in php-impl but is a no-op in native.')
        ->severity('silent')
        ->code(<<<'PHP'
$value = 'not a message';
GPBUtil::checkMessage($value, DoubleValue::class);
return $value;
PHP)
        ->migrationNote('Use `instanceof` for application validation. Native generated setters perform their own checks, but direct GPBUtil calls do not.')
        ->goodCode(<<<'PHP'
if (!$value instanceof DoubleValue && $value !== null) {
    throw new InvalidArgumentException('expected DoubleValue');
}
PHP)
        ->badCode(<<<'PHP'
GPBUtil::checkMessage($value, DoubleValue::class);
PHP)
        ->probe(static function (): mixed {
            $value = 'not a message';
            GPBUtil::checkMessage($value, DoubleValue::class);
            return $value;
        })
        ->expectPhpImpl(
            exceptionContains('Expect Google\Protobuf\DoubleValue')
        )
        ->expectNative(
            returned('not a message')
        ),

    case_('gpbutil.check_repeated_field_array_normalization')
        ->description('GPBUtil::checkRepeatedField() converts arrays to RepeatedField in php-impl but returns the original array in native.')
        ->severity('type difference')
        ->code(<<<'PHP'
$value = [1, 2];
$checked = GPBUtil::checkRepeatedField($value, GPBType::INT32);
return [
    'type' => get_debug_type($checked),
    'values' => is_array($checked) ? $checked : iterator_to_array($checked),
];
PHP)
        ->migrationNote('Do not use GPBUtil::checkRepeatedField() to normalize application arrays. Assign arrays through generated setters or construct RepeatedField explicitly.')
        ->goodCode(<<<'PHP'
$field = new RepeatedField(GPBType::INT32);
foreach ($values as $value) {
    $field[] = $value;
}
$message->setIds($field);
PHP)
        ->badCode(<<<'PHP'
$message->setIds(GPBUtil::checkRepeatedField($values, GPBType::INT32));
PHP)
        ->probe(static function (): mixed {
            $value = [1, 2];
            $checked = GPBUtil::checkRepeatedField($value, GPBType::INT32);
            return [
                'type' => get_debug_type($checked),
                'values' => is_array($checked) ? $checked : iterator_to_array($checked),
            ];
        })
        ->expectPhpImpl(
            returned([
                'type' => 'Google\Protobuf\RepeatedField',
                'values' => [1, 2],
            ])
        )
        ->expectNative(
            returned([
                'type' => 'array',
                'values' => [1, 2],
            ])
        ),

    case_('gpbutil.check_map_field_array_normalization')
        ->description('GPBUtil::checkMapField() converts arrays to MapField in php-impl but returns the original array in native.')
        ->severity('type difference')
        ->code(<<<'PHP'
$value = ['a' => 1];
$checked = GPBUtil::checkMapField($value, GPBType::STRING, GPBType::INT32);
return [
    'type' => get_debug_type($checked),
    'values' => is_array($checked) ? $checked : iterator_to_array($checked),
];
PHP)
        ->migrationNote('Do not use GPBUtil::checkMapField() to normalize application arrays. Assign arrays through generated setters or construct MapField explicitly.')
        ->goodCode(<<<'PHP'
$field = new MapField(GPBType::STRING, GPBType::INT32);
foreach ($values as $key => $value) {
    $field[$key] = $value;
}
$message->setLabels($field);
PHP)
        ->badCode(<<<'PHP'
$message->setLabels(GPBUtil::checkMapField($values, GPBType::STRING, GPBType::INT32));
PHP)
        ->probe(static function (): mixed {
            $value = ['a' => 1];
            $checked = GPBUtil::checkMapField($value, GPBType::STRING, GPBType::INT32);
            return [
                'type' => get_debug_type($checked),
                'values' => is_array($checked) ? $checked : iterator_to_array($checked),
            ];
        })
        ->expectPhpImpl(
            returned([
                'type' => 'Google\Protobuf\Internal\MapField',
                'values' => ['a' => 1],
            ])
        )
        ->expectNative(
            returned([
                'type' => 'array',
                'values' => ['a' => 1],
            ])
        ),
];
