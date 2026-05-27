<?php

declare(strict_types=1);

use Google\Protobuf\Descriptor;
use Google\Protobuf\DescriptorPool;
use Google\Protobuf\DoubleValue;
use Google\Protobuf\EnumDescriptor;
use Google\Protobuf\EnumValueDescriptor;
use Google\Protobuf\FieldDescriptor;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\GPBUtil;
use Google\Protobuf\Internal\MapField;
use Google\Protobuf\OneofDescriptor;
use Google\Protobuf\RepeatedField;
use Google\Protobuf\Timestamp;

use function Google\Protobuf\Tests\Discrepancy\case_;
use function Google\Protobuf\Tests\Discrepancy\returned;

return [
    case_('class_shape.final_classes')
        ->description('Several runtime classes are non-final in php-impl but final internal classes in native.')
        ->severity('fatal')
        ->code(<<<'PHP'
return [
    MapField::class => (new ReflectionClass(MapField::class))->isFinal(),
    RepeatedField::class => (new ReflectionClass(RepeatedField::class))->isFinal(),
    DescriptorPool::class => (new ReflectionClass(DescriptorPool::class))->isFinal(),
    Descriptor::class => (new ReflectionClass(Descriptor::class))->isFinal(),
    FieldDescriptor::class => (new ReflectionClass(FieldDescriptor::class))->isFinal(),
    EnumDescriptor::class => (new ReflectionClass(EnumDescriptor::class))->isFinal(),
    EnumValueDescriptor::class => (new ReflectionClass(EnumValueDescriptor::class))->isFinal(),
    OneofDescriptor::class => (new ReflectionClass(OneofDescriptor::class))->isFinal(),
    Timestamp::class => (new ReflectionClass(Timestamp::class))->isFinal(),
];
PHP)
        ->migrationNote('Do not subclass protobuf runtime/container/descriptor/well-known classes. Use composition or helper functions; native marks many of these classes final.')
        ->goodCode(<<<'PHP'
final class LabelMapView
{
    public function __construct(private MapField $labels) {}
}
PHP)
        ->badCode(<<<'PHP'
class CustomMapField extends MapField
{
}
PHP)
        ->probe(static function (): mixed {
            return [
                MapField::class => (new ReflectionClass(MapField::class))->isFinal(),
                RepeatedField::class => (new ReflectionClass(RepeatedField::class))->isFinal(),
                DescriptorPool::class => (new ReflectionClass(DescriptorPool::class))->isFinal(),
                Descriptor::class => (new ReflectionClass(Descriptor::class))->isFinal(),
                FieldDescriptor::class => (new ReflectionClass(FieldDescriptor::class))->isFinal(),
                EnumDescriptor::class => (new ReflectionClass(EnumDescriptor::class))->isFinal(),
                EnumValueDescriptor::class => (new ReflectionClass(EnumValueDescriptor::class))->isFinal(),
                OneofDescriptor::class => (new ReflectionClass(OneofDescriptor::class))->isFinal(),
                Timestamp::class => (new ReflectionClass(Timestamp::class))->isFinal(),
            ];
        })
        ->expectPhpImpl(
            returned([
                MapField::class => false,
                RepeatedField::class => false,
                DescriptorPool::class => false,
                Descriptor::class => false,
                FieldDescriptor::class => false,
                EnumDescriptor::class => false,
                EnumValueDescriptor::class => false,
                OneofDescriptor::class => false,
                Timestamp::class => false,
            ])
        )
        ->expectNative(
            returned([
                MapField::class => true,
                RepeatedField::class => true,
                DescriptorPool::class => true,
                Descriptor::class => true,
                FieldDescriptor::class => true,
                EnumDescriptor::class => true,
                EnumValueDescriptor::class => true,
                OneofDescriptor::class => true,
                Timestamp::class => true,
            ])
        ),

    case_('class_shape.container_methods')
        ->description('Container introspection methods exist only in php-impl, while RepeatedField::append() exists only in native.')
        ->severity('type difference')
        ->code(<<<'PHP'
$map = new MapField(GPBType::STRING, GPBType::INT32);
$repeated = new RepeatedField(GPBType::INT32);
return [
    'map.getKeyType' => method_exists($map, 'getKeyType'),
    'map.getValueType' => method_exists($map, 'getValueType'),
    'map.getValueClass' => method_exists($map, 'getValueClass'),
    'map.getLegacyValueClass' => method_exists($map, 'getLegacyValueClass'),
    'repeated.getType' => method_exists($repeated, 'getType'),
    'repeated.getClass' => method_exists($repeated, 'getClass'),
    'repeated.append' => method_exists($repeated, 'append'),
    'gpbutil.checkBytes' => method_exists(GPBUtil::class, 'checkBytes'),
];
PHP)
        ->migrationNote('Avoid runtime-container introspection methods and native-only `append()` in portable code. Use ArrayAccess append (`$field[] = $value`) and generated field descriptors instead.')
        ->goodCode(<<<'PHP'
$repeated[] = $value;
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
PHP)
        ->badCode(<<<'PHP'
$type = $repeated->getType();
$repeated->append($value);
PHP)
        ->probe(static function (): mixed {
            $map = new MapField(GPBType::STRING, GPBType::INT32);
            $repeated = new RepeatedField(GPBType::INT32);
            return [
                'map.getKeyType' => method_exists($map, 'getKeyType'),
                'map.getValueType' => method_exists($map, 'getValueType'),
                'map.getValueClass' => method_exists($map, 'getValueClass'),
                'map.getLegacyValueClass' => method_exists($map, 'getLegacyValueClass'),
                'repeated.getType' => method_exists($repeated, 'getType'),
                'repeated.getClass' => method_exists($repeated, 'getClass'),
                'repeated.append' => method_exists($repeated, 'append'),
                'gpbutil.checkBytes' => method_exists(GPBUtil::class, 'checkBytes'),
            ];
        })
        ->expectPhpImpl(
            returned([
                'map.getKeyType' => true,
                'map.getValueType' => true,
                'map.getValueClass' => true,
                'map.getLegacyValueClass' => true,
                'repeated.getType' => true,
                'repeated.getClass' => true,
                'repeated.append' => false,
                'gpbutil.checkBytes' => false,
            ])
        )
        ->expectNative(
            returned([
                'map.getKeyType' => false,
                'map.getValueType' => false,
                'map.getValueClass' => false,
                'map.getLegacyValueClass' => false,
                'repeated.getType' => false,
                'repeated.getClass' => false,
                'repeated.append' => true,
                'gpbutil.checkBytes' => true,
            ])
        ),
];
