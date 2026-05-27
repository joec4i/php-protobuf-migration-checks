<?php

declare(strict_types=1);

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\RepeatedField;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\exceptionContains;
use function Google\Protobuf\Tests\Discrepancy\fatalContains;
use function Google\Protobuf\Tests\Discrepancy\returned;
use function Google\Protobuf\Tests\Discrepancy\warningContains;

return [
    case_('repeated.missing_index')
        ->description('Reading a missing RepeatedField index returns null in php-impl but fatals in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
return $values[0];
PHP)
        ->migrationNote('Do not read a repeated field index unless `isset($values[$i])` is true.')
        ->goodCode(<<<'PHP'
if (isset($message->getIds()[$i])) {
    $id = $message->getIds()[$i];
}
PHP)
        ->badCode(<<<'PHP'
$id = $message->getIds()[$i];
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            return $values[0];
        })
        ->expectPhpImpl(
            returned(null),
            warningContains('Undefined array key')
        )
        ->expectNative(
            fatalContains("Element at 0 doesn't exist")
        ),

    case_('repeated.offset_exists_non_int')
        ->description('`isset($repeated[$key])` with a non-integer key returns false in php-impl but throws in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
return isset($values['missing']);
PHP)
        ->migrationNote('Only use integer offsets with RepeatedField. Cast or validate external offsets before using ArrayAccess.')
        ->goodCode(<<<'PHP'
if (is_int($offset) && isset($values[$offset])) {
    $value = $values[$offset];
}
PHP)
        ->badCode(<<<'PHP'
if (isset($values[$request['offset']])) {
    $value = $values[$request['offset']];
}
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            return isset($values['missing']);
        })
        ->expectPhpImpl(
            returned(false)
        )
        ->expectNative(
            exceptionContains('must be of type int, string given')
        ),

    case_('repeated.explicit_index_at_count')
        ->description('Assigning `$repeated[count($repeated)] = $value` fatals in php-impl but appends in native.')
        ->severity('silent')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
$values[] = 1;
$values[1] = 2;
return iterator_to_array($values);
PHP)
        ->migrationNote('Append with `$repeated[] = $value`; do not assign explicit indexes except to replace an existing element.')
        ->goodCode(<<<'PHP'
$values[] = 2;
PHP)
        ->badCode(<<<'PHP'
$values[count($values)] = 2;
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            $values[] = 1;
            $values[1] = 2;
            return iterator_to_array($values);
        })
        ->expectPhpImpl(
            fatalContains('Cannot modify element at the given index')
        )
        ->expectNative(
            returned([1, 2])
        ),

    case_('repeated.null_string_value')
        ->description('Appending null to a string RepeatedField coerces to an empty string in php-impl but throws in native.')
        ->severity('throw')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::STRING);
$values[] = null;
return iterator_to_array($values);
PHP)
        ->migrationNote('Normalize nullable strings before appending. Native does not apply php-impl `strval(null)` coercion.')
        ->goodCode(<<<'PHP'
if ($label !== null) {
    $labels[] = $label;
}
PHP)
        ->badCode(<<<'PHP'
$labels[] = $label;
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::STRING);
            $values[] = null;
            return iterator_to_array($values);
        })
        ->expectPhpImpl(
            returned([''])
        )
        ->expectNative(
            exceptionContains("Cannot convert '' to string")
        ),

    case_('repeated.null_bool_value')
        ->description('Appending null to a bool RepeatedField coerces to false in php-impl but throws in native.')
        ->severity('throw')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::BOOL);
$values[] = null;
return iterator_to_array($values);
PHP)
        ->migrationNote('Normalize nullable booleans explicitly before appending. Native does not apply php-impl `boolval(null)` coercion.')
        ->goodCode(<<<'PHP'
if ($enabled !== null) {
    $flags[] = (bool) $enabled;
}
PHP)
        ->badCode(<<<'PHP'
$flags[] = $enabled;
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::BOOL);
            $values[] = null;
            return iterator_to_array($values);
        })
        ->expectPhpImpl(
            returned([false])
        )
        ->expectNative(
            exceptionContains("Cannot convert '' to bool")
        ),

    case_('repeated.integer_scientific_string')
        ->description('php-impl accepts scientific-notation strings for integer fields, while native rejects them.')
        ->severity('throw')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
$values[] = '1e2';
return iterator_to_array($values);
PHP)
        ->migrationNote('Pass integer fields as integers or plain decimal strings. Do not rely on PHP `is_numeric()` accepting exponent notation.')
        ->goodCode(<<<'PHP'
$ids[] = 100;
PHP)
        ->badCode(<<<'PHP'
$ids[] = '1e2';
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            $values[] = '1e2';
            return iterator_to_array($values);
        })
        ->expectPhpImpl(
            returned([100])
        )
        ->expectNative(
            exceptionContains("Cannot convert '1e2' to integer")
        ),

    case_('repeated.int32_overflow')
        ->description('Out-of-range int32 values remain oversized in php-impl containers but wrap to int32 in native containers.')
        ->severity('silent')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
$values[] = 2147483648;
return iterator_to_array($values);
PHP)
        ->migrationNote('Validate int32 ranges before assignment. Native stores the C int32 value immediately; php-impl may leave an oversized PHP integer in memory.')
        ->goodCode(<<<'PHP'
if ($id < -2147483648 || $id > 2147483647) {
    throw new InvalidArgumentException('id is outside int32 range');
}
$ids[] = $id;
PHP)
        ->badCode(<<<'PHP'
$ids[] = $id;
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            $values[] = 2147483648;
            return iterator_to_array($values);
        })
        ->expectPhpImpl(
            returned([2147483648])
        )
        ->expectNative(
            returned([-2147483648])
        ),

    case_('repeated.uint64_max_string')
        ->description('A uint64 string above PHP_INT_MAX is saturated by php-impl on 64-bit PHP but rejected by native.')
        ->severity('throw')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::UINT64);
$values[] = '18446744073709551615';
return iterator_to_array($values);
PHP)
        ->migrationNote('Treat uint64 values above PHP_INT_MAX as a compatibility hotspot. Add explicit tests before moving uint64-heavy code to native.')
        ->goodCode(<<<'PHP'
// GOOD: keep uint64 boundary behavior covered by implementation-specific tests.
$message->setCounter($counter);
PHP)
        ->badCode(<<<'PHP'
// BAD: assuming every decimal uint64 string accepted by php-impl is accepted by native.
$counters[] = '18446744073709551615';
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::UINT64);
            $values[] = '18446744073709551615';
            return iterator_to_array($values);
        })
        ->expectPhpImpl(
            returned([9223372036854775807])
        )
        ->expectNative(
            exceptionContains("Cannot convert '18446744073709551615' to integer")
        ),

    case_('repeated.iterator_current_invalid')
        ->description('Calling RepeatedFieldIter::current() when invalid returns null with a warning in php-impl but fatals in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
$iterator = $values->getIterator();
return $iterator->current();
PHP)
        ->migrationNote('Only call iterator `current()` after `valid()` is true. Normal `foreach` usage is safe.')
        ->goodCode(<<<'PHP'
$iterator = $values->getIterator();
if ($iterator->valid()) {
    $value = $iterator->current();
}
PHP)
        ->badCode(<<<'PHP'
$value = $values->getIterator()->current();
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            $iterator = $values->getIterator();
            return $iterator->current();
        })
        ->expectPhpImpl(
            returned(null),
            warningContains('Undefined array key')
        )
        ->expectNative(
            fatalContains("Element at 0 doesn't exist")
        ),

    case_('repeated.unset_missing_index')
        ->description('unset($repeated[$missingIndex]) is fatal in both implementations but with different error messages.')
        ->severity('fatal')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
unset($values[0]);
return 'ok';
PHP)
        ->migrationNote('Do not unset missing repeated-field indexes. Use generated setters or rebuild the RepeatedField instead of sparse ArrayAccess removal.')
        ->goodCode(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
foreach ($sourceValues as $value) {
    $values[] = $value;
}
PHP)
        ->badCode(<<<'PHP'
unset($values[$index]);
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            unset($values[0]);
            return 'ok';
        })
        ->expectPhpImpl(
            fatalContains('Cannot remove element at the given index')
        )
        ->expectNative(
            fatalContains('Cannot remove element at 0')
        ),

    case_('repeated.offset_set_string_key')
        ->description('Assigning with a string numeric offset fatals in php-impl but appends/stores in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$values = new RepeatedField(GPBType::INT32);
$values['0'] = 1;
return iterator_to_array($values);
PHP)
        ->migrationNote('Cast repeated-field offsets to integers before assignment. Native accepts string numeric keys that php-impl rejects.')
        ->goodCode(<<<'PHP'
$values[(int) $offset] = $value;
PHP)
        ->badCode(<<<'PHP'
$values[$offset] = $value;
PHP)
        ->probe(static function (): mixed {
            $values = new RepeatedField(GPBType::INT32);
            $values['0'] = 1;
            return iterator_to_array($values);
        })
        ->expectPhpImpl(
            fatalContains('Cannot modify element at the given index')
        )
        ->expectNative(
            returned([1])
        ),
];
