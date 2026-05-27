<?php

declare(strict_types=1);

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\MapField;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\exceptionContains;
use function Google\Protobuf\Tests\Discrepancy\fatalContains;
use function Google\Protobuf\Tests\Discrepancy\returned;
use function Google\Protobuf\Tests\Discrepancy\stderrContains;
use function Google\Protobuf\Tests\Discrepancy\warningContains;

return [
    case_('map.missing_key')
        ->description('Reading a missing MapField key returns null in php-impl but fatals in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
$map = new MapField(GPBType::STRING, GPBType::INT32);
return $map['missing'];
PHP)
        ->migrationNote(<<<'TEXT'
Do not use a map read as a presence check. Native MapField treats missing keys as a fatal runtime error, so test for key existence before reading.
TEXT)
        ->badCode(<<<'PHP'
$value = $message->getLabels()['customer_id'];
PHP)
        ->goodCode(<<<'PHP'
$labels = $message->getLabels();
if (isset($labels['customer_id'])) {
    $value = $labels['customer_id'];
}
PHP)
        ->probe(static function (): mixed {
            $map = new MapField(GPBType::STRING, GPBType::INT32);
            return $map['missing'];
        })
        ->expectPhpImpl(
            returned(null),
            warningContains('Undefined array key')
        )
        ->expectNative(
            fatalContains("Given key doesn't exist")
        ),

    case_('map.null_string_key')
        ->description('Using null as a string MapField key coerces to an empty string in php-impl but throws in native.')
        ->severity('throw')
        ->code(<<<'PHP'
$map = new MapField(GPBType::STRING, GPBType::INT32);
$map[null] = 7;
return iterator_to_array($map);
PHP)
        ->migrationNote('Normalize map keys before assignment. Native does not apply php-impl `strval(null)` coercion for string keys.')
        ->goodCode(<<<'PHP'
if ($key === null) {
    throw new InvalidArgumentException('map key is required');
}
$labels[(string) $key] = $value;
PHP)
        ->badCode(<<<'PHP'
$labels[$key] = $value;
PHP)
        ->probe(static function (): mixed {
            $map = new MapField(GPBType::STRING, GPBType::INT32);
            $map[null] = 7;
            return iterator_to_array($map);
        })
        ->expectPhpImpl(
            returned(['' => 7])
        )
        ->expectNative(
            exceptionContains("Cannot convert '' to string")
        ),

    case_('map.null_string_value')
        ->description('Assigning null to a string MapField value coerces to an empty string in php-impl but throws in native.')
        ->severity('throw')
        ->code(<<<'PHP'
$map = new MapField(GPBType::STRING, GPBType::STRING);
$map['name'] = null;
return iterator_to_array($map);
PHP)
        ->migrationNote('Normalize nullable map values explicitly before assignment. Native does not apply php-impl `strval(null)` coercion.')
        ->goodCode(<<<'PHP'
if ($name !== null) {
    $names[$id] = $name;
}
PHP)
        ->badCode(<<<'PHP'
$names[$id] = $name;
PHP)
        ->probe(static function (): mixed {
            $map = new MapField(GPBType::STRING, GPBType::STRING);
            $map['name'] = null;
            return iterator_to_array($map);
        })
        ->expectPhpImpl(
            returned(['name' => ''])
        )
        ->expectNative(
            exceptionContains("Cannot convert '' to string")
        ),

    case_('map.string_iteration_order')
        ->description('String-keyed MapField iteration preserves insertion order in php-impl but uses native map order under ext-protobuf.')
        ->severity('silent')
        ->code(<<<'PHP'
$map = new MapField(GPBType::STRING, GPBType::INT32);
foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $i => $key) {
    $map[$key] = $i;
}
return array_keys(iterator_to_array($map)) === ['a', 'b', 'c', 'd', 'e', 'f'];
PHP)
        ->migrationNote('Do not rely on map iteration or serialized JSON object member order. Sort keys before order-sensitive comparisons or output.')
        ->goodCode(<<<'PHP'
$labels = iterator_to_array($message->getLabels());
ksort($labels);
foreach ($labels as $key => $value) {
    // deterministic processing
}
PHP)
        ->badCode(<<<'PHP'
foreach ($message->getLabels() as $key => $value) {
    // assumes insertion order
}
PHP)
        ->probe(static function (): mixed {
            $map = new MapField(GPBType::STRING, GPBType::INT32);
            foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $i => $key) {
                $map[$key] = $i;
            }
            return array_keys(iterator_to_array($map)) === ['a', 'b', 'c', 'd', 'e', 'f'];
        })
        ->expectPhpImpl(
            returned(true)
        )
        ->expectNative(
            returned(false)
        ),

    case_('map.iterator_current_invalid')
        ->description('Calling MapFieldIter::current() when invalid returns false in php-impl but aborts the native child process.')
        ->severity('fatal')
        ->code(<<<'PHP'
$map = new MapField(GPBType::STRING, GPBType::INT32);
$iterator = $map->getIterator();
return $iterator->current();
PHP)
        ->migrationNote('Only call map iterator `current()` or `key()` after `valid()` is true. Normal `foreach` usage is safe.')
        ->goodCode(<<<'PHP'
$iterator = $map->getIterator();
if ($iterator->valid()) {
    $value = $iterator->current();
}
PHP)
        ->badCode(<<<'PHP'
$value = $map->getIterator()->current();
PHP)
        ->probe(static function (): mixed {
            $map = new MapField(GPBType::STRING, GPBType::INT32);
            $iterator = $map->getIterator();
            return $iterator->current();
        })
        ->expectPhpImpl(
            returned(false)
        )
        ->expectNative(
            stderrContains('Assertion failed')
        ),
];
