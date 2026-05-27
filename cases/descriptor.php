<?php

declare(strict_types=1);

use Google\Protobuf\DescriptorPool;
use Google\Protobuf\DoubleValue;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\fatalContains;
use function Google\Protobuf\Tests\Discrepancy\returned;

return [
    case_('descriptor_pool.lookup_uninitialized_class')
        ->description('DescriptorPool::getDescriptorByClassName() returns null for an uninitialized generated class in php-impl but autoloads/resolves it in native.')
        ->severity('silent')
        ->code(<<<'PHP'
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
return $descriptor !== null;
PHP)
        ->migrationNote('If code relies on descriptor lookup timing, initialize generated metadata explicitly before lookup and do not use null as a portable "not initialized yet" signal.')
        ->goodCode(<<<'PHP'
new DoubleValue(); // ensures metadata is initialized in php-impl
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
PHP)
        ->badCode(<<<'PHP'
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
if ($descriptor === null) {
    // assumes class metadata was not initialized
}
PHP)
        ->probe(static function (): mixed {
            $descriptor = DescriptorPool::getGeneratedPool()
                ->getDescriptorByClassName(DoubleValue::class);
            return $descriptor !== null;
        })
        ->expectPhpImpl(
            returned(false)
        )
        ->expectNative(
            returned(true)
        ),

    case_('descriptor.invalid_field_index')
        ->description('Descriptor::getField($missingIndex) returns null in php-impl but fatals in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
new DoubleValue();
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
return $descriptor->getField(99);
PHP)
        ->migrationNote('Bounds-check descriptor indexes before lookup. Native descriptor accessors treat out-of-range indexes as fatal runtime errors.')
        ->goodCode(<<<'PHP'
if ($index >= 0 && $index < $descriptor->getFieldCount()) {
    $field = $descriptor->getField($index);
}
PHP)
        ->badCode(<<<'PHP'
$field = $descriptor->getField($index);
PHP)
        ->probe(static function (): mixed {
            new DoubleValue();
            $descriptor = DescriptorPool::getGeneratedPool()
                ->getDescriptorByClassName(DoubleValue::class);
            return $descriptor->getField(99);
        })
        ->expectPhpImpl(
            returned(null)
        )
        ->expectNative(
            fatalContains('Cannot get element at 99')
        ),

    case_('descriptor.method_availability')
        ->description('Public descriptor method availability is not identical: php-impl exposes getRealOneofDeclCount(), native does not.')
        ->severity('type difference')
        ->code(<<<'PHP'
new DoubleValue();
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
return [
    'getRealOneofDeclCount' => method_exists($descriptor, 'getRealOneofDeclCount'),
];
PHP)
        ->migrationNote('Avoid descriptor methods that are only present in one implementation. Feature-detect before calling descriptor helper methods.')
        ->goodCode(<<<'PHP'
if (method_exists($descriptor, 'getRealOneofDeclCount')) {
    $count = $descriptor->getRealOneofDeclCount();
}
PHP)
        ->badCode(<<<'PHP'
$count = $descriptor->getRealOneofDeclCount();
PHP)
        ->probe(static function (): mixed {
            new DoubleValue();
            $descriptor = DescriptorPool::getGeneratedPool()
                ->getDescriptorByClassName(DoubleValue::class);
            return [
                'getRealOneofDeclCount' => method_exists($descriptor, 'getRealOneofDeclCount'),
            ];
        })
        ->expectPhpImpl(
            returned(['getRealOneofDeclCount' => true])
        )
        ->expectNative(
            returned(['getRealOneofDeclCount' => false])
        ),
];
