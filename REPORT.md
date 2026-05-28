# Protobuf PHP Implementation Discrepancy Report

This report is generated from executable discrepancy cases. Each case runs once with the userland PHP implementation and once with the native `ext-protobuf` implementation.

## `class_shape.final_classes`

**Severity:** `fatal`

**Description:** Several runtime classes are non-final in php-impl but final internal classes in native.

### Probe Code

```php
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
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "Google\\Protobuf\\Internal\\MapField": false,
        "Google\\Protobuf\\RepeatedField": false,
        "Google\\Protobuf\\DescriptorPool": false,
        "Google\\Protobuf\\Descriptor": false,
        "Google\\Protobuf\\FieldDescriptor": false,
        "Google\\Protobuf\\EnumDescriptor": false,
        "Google\\Protobuf\\EnumValueDescriptor": false,
        "Google\\Protobuf\\OneofDescriptor": false,
        "Google\\Protobuf\\Timestamp": false
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "Google\\Protobuf\\Internal\\MapField": true,
        "Google\\Protobuf\\RepeatedField": true,
        "Google\\Protobuf\\DescriptorPool": true,
        "Google\\Protobuf\\Descriptor": true,
        "Google\\Protobuf\\FieldDescriptor": true,
        "Google\\Protobuf\\EnumDescriptor": true,
        "Google\\Protobuf\\EnumValueDescriptor": true,
        "Google\\Protobuf\\OneofDescriptor": true,
        "Google\\Protobuf\\Timestamp": true
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Do not subclass protobuf runtime/container/descriptor/well-known classes. Use composition or helper functions; native marks many of these classes final.

### Migration Example

```php
// GOOD
final class LabelMapView
{
    public function __construct(private MapField $labels) {}
}

// BAD
class CustomMapField extends MapField
{
}
```

## `class_shape.container_methods`

**Severity:** `type difference`

**Description:** Container introspection methods exist only in php-impl, while RepeatedField::append() exists only in native.

### Probe Code

```php
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
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "map.getKeyType": true,
        "map.getValueType": true,
        "map.getValueClass": true,
        "map.getLegacyValueClass": true,
        "repeated.getType": true,
        "repeated.getClass": true,
        "repeated.append": false,
        "gpbutil.checkBytes": false
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "map.getKeyType": false,
        "map.getValueType": false,
        "map.getValueClass": false,
        "map.getLegacyValueClass": false,
        "repeated.getType": false,
        "repeated.getClass": false,
        "repeated.append": true,
        "gpbutil.checkBytes": true
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Avoid runtime-container introspection methods and native-only `append()` in portable code. Use ArrayAccess append (`$field[] = $value`) and generated field descriptors instead.

### Migration Example

```php
// GOOD
$repeated[] = $value;
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);

// BAD
$type = $repeated->getType();
$repeated->append($value);
```

## `descriptor_pool.lookup_uninitialized_class`

**Severity:** `silent`

**Description:** DescriptorPool::getDescriptorByClassName() returns null for an uninitialized generated class in php-impl but autoloads/resolves it in native.

### Probe Code

```php
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
return $descriptor !== null;
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned bool: false.` |
| `native` | `0` | `Returned bool: true.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": false,
    "return_type": "bool",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": true,
    "return_type": "bool",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

If code relies on descriptor lookup timing, initialize generated metadata explicitly before lookup and do not use null as a portable "not initialized yet" signal.

### Migration Example

```php
// GOOD
new DoubleValue(); // ensures metadata is initialized in php-impl
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);

// BAD
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
if ($descriptor === null) {
    // assumes class metadata was not initialized
}
```

## `descriptor.invalid_field_index`

**Severity:** `fatal`

**Description:** Descriptor::getField($missingIndex) returns null in php-impl but fatals in native.

### Probe Code

```php
new DoubleValue();
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
return $descriptor->getField(99);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned null: NULL.` |
| `native` | `255` | `Fatal: Cannot get element at 99. ` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": null,
    "return_type": "null",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Cannot get element at 99.\n",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/cases/descriptor.php",
        "line": 68
    },
    "stderr": "PHP Fatal error:  Cannot get element at 99.\n in /Users/joe.cai/git/php-protobuf-migration-checks/cases/descriptor.php on line 68"
}
```

</details>

### Migration Note

Bounds-check descriptor indexes before lookup. Native descriptor accessors treat out-of-range indexes as fatal runtime errors.

### Migration Example

```php
// GOOD
if ($index >= 0 && $index < $descriptor->getFieldCount()) {
    $field = $descriptor->getField($index);
}

// BAD
$field = $descriptor->getField($index);
```

## `descriptor.method_availability`

**Severity:** `type difference`

**Description:** Public descriptor method availability is not identical: php-impl exposes getRealOneofDeclCount(), native does not.

### Probe Code

```php
new DoubleValue();
$descriptor = DescriptorPool::getGeneratedPool()
    ->getDescriptorByClassName(DoubleValue::class);
return [
    'getRealOneofDeclCount' => method_exists($descriptor, 'getRealOneofDeclCount'),
];
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "getRealOneofDeclCount": true
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "getRealOneofDeclCount": false
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Avoid descriptor methods that are only present in one implementation. Feature-detect before calling descriptor helper methods.

### Migration Example

```php
// GOOD
if (method_exists($descriptor, 'getRealOneofDeclCount')) {
    $count = $descriptor->getRealOneofDeclCount();
}

// BAD
$count = $descriptor->getRealOneofDeclCount();
```

## `gpbutil.check_int32_noop_native`

**Severity:** `silent`

**Description:** GPBUtil::checkInt32() coerces by reference in php-impl but is a no-op placeholder in native.

### Probe Code

```php
$value = '123';
GPBUtil::checkInt32($value);
return $value;
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned int: 123.` |
| `native` | `0` | `Returned string: '123'.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": 123,
    "return_type": "int",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": "123",
    "return_type": "string",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Do not call GPBUtil check methods as application validators. They are internal generated-code hooks and primitive checks are no-ops under native.

### Migration Example

```php
// GOOD
if (!is_int($value)) {
    throw new InvalidArgumentException('expected int32');
}
$message->setId($value);

// BAD
GPBUtil::checkInt32($value);
$message->setId($value);
```

## `gpbutil.check_string_null_noop_native`

**Severity:** `silent`

**Description:** GPBUtil::checkString() coerces null to an empty string in php-impl but leaves null unchanged in native.

### Probe Code

```php
$value = null;
GPBUtil::checkString($value, true);
return $value;
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned string: ''.` |
| `native` | `0` | `Returned null: NULL.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": "",
    "return_type": "string",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": null,
    "return_type": "null",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Normalize nullable strings in application code; GPBUtil does not provide portable coercion.

### Migration Example

```php
// GOOD
$value = $value ?? '';
$message->setName($value);

// BAD
GPBUtil::checkString($value, true);
$message->setName($value);
```

## `gpbutil.check_bool_null_noop_native`

**Severity:** `silent`

**Description:** GPBUtil::checkBool() coerces null to false in php-impl but leaves null unchanged in native.

### Probe Code

```php
$value = null;
GPBUtil::checkBool($value);
return $value;
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned bool: false.` |
| `native` | `0` | `Returned null: NULL.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": false,
    "return_type": "bool",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": null,
    "return_type": "null",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Normalize nullable booleans in application code; GPBUtil does not provide portable coercion.

### Migration Example

```php
// GOOD
$value = $value !== null ? (bool) $value : false;
$message->setEnabled($value);

// BAD
GPBUtil::checkBool($value);
$message->setEnabled($value);
```

## `gpbutil.check_message_noop_native`

**Severity:** `silent`

**Description:** GPBUtil::checkMessage() rejects the wrong type in php-impl but is a no-op in native.

### Probe Code

```php
$value = 'not a message';
GPBUtil::checkMessage($value, DoubleValue::class);
return $value;
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Expect Google\Protobuf\DoubleValue.` |
| `native` | `0` | `Returned string: 'not a message'.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Expect Google\\Protobuf\\DoubleValue.",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": "not a message",
    "return_type": "string",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Use `instanceof` for application validation. Native generated setters perform their own checks, but direct GPBUtil calls do not.

### Migration Example

```php
// GOOD
if (!$value instanceof DoubleValue && $value !== null) {
    throw new InvalidArgumentException('expected DoubleValue');
}

// BAD
GPBUtil::checkMessage($value, DoubleValue::class);
```

## `gpbutil.check_repeated_field_array_normalization`

**Severity:** `type difference`

**Description:** GPBUtil::checkRepeatedField() converts arrays to RepeatedField in php-impl but returns the original array in native.

### Probe Code

```php
$value = [1, 2];
$checked = GPBUtil::checkRepeatedField($value, GPBType::INT32);
return [
    'type' => get_debug_type($checked),
    'values' => is_array($checked) ? $checked : iterator_to_array($checked),
];
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "type": "Google\\Protobuf\\RepeatedField",
        "values": [
            1,
            2
        ]
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "type": "array",
        "values": [
            1,
            2
        ]
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Do not use GPBUtil::checkRepeatedField() to normalize application arrays. Assign arrays through generated setters or construct RepeatedField explicitly.

### Migration Example

```php
// GOOD
$field = new RepeatedField(GPBType::INT32);
foreach ($values as $value) {
    $field[] = $value;
}
$message->setIds($field);

// BAD
$message->setIds(GPBUtil::checkRepeatedField($values, GPBType::INT32));
```

## `gpbutil.check_map_field_array_normalization`

**Severity:** `type difference`

**Description:** GPBUtil::checkMapField() converts arrays to MapField in php-impl but returns the original array in native.

### Probe Code

```php
$value = ['a' => 1];
$checked = GPBUtil::checkMapField($value, GPBType::STRING, GPBType::INT32);
return [
    'type' => get_debug_type($checked),
    'values' => is_array($checked) ? $checked : iterator_to_array($checked),
];
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "type": "Google\\Protobuf\\Internal\\MapField",
        "values": {
            "a": 1
        }
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "type": "array",
        "values": {
            "a": 1
        }
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Do not use GPBUtil::checkMapField() to normalize application arrays. Assign arrays through generated setters or construct MapField explicitly.

### Migration Example

```php
// GOOD
$field = new MapField(GPBType::STRING, GPBType::INT32);
foreach ($values as $key => $value) {
    $field[$key] = $value;
}
$message->setLabels($field);

// BAD
$message->setLabels(GPBUtil::checkMapField($values, GPBType::STRING, GPBType::INT32));
```

## `map.missing_key`

**Severity:** `fatal`

**Description:** Reading a missing MapField key returns null in php-impl but fatals in native.

### Probe Code

```php
$map = new MapField(GPBType::STRING, GPBType::INT32);
return $map['missing'];
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Warning: Undefined array key "missing"` |
| `native` | `255` | `Fatal: Given key doesn't exist.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": null,
    "return_type": "null",
    "warnings": [
        {
            "type": 2,
            "message": "Undefined array key \"missing\"",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/Internal/MapField.php",
            "line": 120
        }
    ],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Given key doesn't exist.",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/cases/map.php",
        "line": 37
    },
    "stderr": "PHP Fatal error:  Given key doesn't exist. in /Users/joe.cai/git/php-protobuf-migration-checks/cases/map.php on line 37"
}
```

</details>

### Migration Note

Do not use a map read as a presence check. Native MapField treats missing keys as a fatal runtime error, so test for key existence before reading.

### Migration Example

```php
// GOOD
$labels = $message->getLabels();
if (isset($labels['customer_id'])) {
    $value = $labels['customer_id'];
}

// BAD
$value = $message->getLabels()['customer_id'];
```

## `map.null_string_key`

**Severity:** `throw`

**Description:** Using null as a string MapField key coerces to an empty string in php-impl but throws in native.

### Probe Code

```php
$map = new MapField(GPBType::STRING, GPBType::INT32);
$map[null] = 7;
return iterator_to_array($map);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   '' => 7, ).` |
| `native` | `0` | `Exception: Cannot convert '' to string` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "": 7
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '' to string",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Normalize map keys before assignment. Native does not apply php-impl `strval(null)` coercion for string keys.

### Migration Example

```php
// GOOD
if ($key === null) {
    throw new InvalidArgumentException('map key is required');
}
$labels[(string) $key] = $value;

// BAD
$labels[$key] = $value;
```

## `map.null_string_value`

**Severity:** `throw`

**Description:** Assigning null to a string MapField value coerces to an empty string in php-impl but throws in native.

### Probe Code

```php
$map = new MapField(GPBType::STRING, GPBType::STRING);
$map['name'] = null;
return iterator_to_array($map);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   'name' => '', ).` |
| `native` | `0` | `Exception: Cannot convert '' to string` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "name": ""
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '' to string",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Normalize nullable map values explicitly before assignment. Native does not apply php-impl `strval(null)` coercion.

### Migration Example

```php
// GOOD
if ($name !== null) {
    $names[$id] = $name;
}

// BAD
$names[$id] = $name;
```

## `map.string_iteration_order`

**Severity:** `silent`

**Description:** String-keyed MapField iteration preserves insertion order in php-impl but uses native map order under ext-protobuf.

### Probe Code

```php
$map = new MapField(GPBType::STRING, GPBType::INT32);
foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $i => $key) {
    $map[$key] = $i;
}
return array_keys(iterator_to_array($map)) === ['a', 'b', 'c', 'd', 'e', 'f'];
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned bool: true.` |
| `native` | `0` | `Returned bool: false.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": true,
    "return_type": "bool",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": false,
    "return_type": "bool",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Do not rely on map iteration or serialized JSON object member order. Sort keys before order-sensitive comparisons or output.

### Migration Example

```php
// GOOD
$labels = iterator_to_array($message->getLabels());
ksort($labels);
foreach ($labels as $key => $value) {
    // deterministic processing
}

// BAD
foreach ($message->getLabels() as $key => $value) {
    // assumes insertion order
}
```

## `map.iterator_current_invalid`

**Severity:** `fatal`

**Description:** Calling MapFieldIter::current() when invalid returns false in php-impl but aborts the native child process.

### Probe Code

```php
$map = new MapField(GPBType::STRING, GPBType::INT32);
$iterator = $map->getIterator();
return $iterator->current();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned bool: false.` |
| `native` | `6` | `No harness JSON; stderr: Assertion failed: (!upb_strtable_done(i)), function upb_strtable_iter_value, file php-upb.c, line 4000.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": false,
    "return_type": "bool",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 6,
    "status": "no-json",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": null,
    "fatal": null,
    "stderr": "Assertion failed: (!upb_strtable_done(i)), function upb_strtable_iter_value, file php-upb.c, line 4000."
}
```

</details>

### Migration Note

Only call map iterator `current()` or `key()` after `valid()` is true. Normal `foreach` usage is safe.

### Migration Example

```php
// GOOD
$iterator = $map->getIterator();
if ($iterator->valid()) {
    $value = $iterator->current();
}

// BAD
$value = $map->getIterator()->current();
```

## `message.pure_only_public_methods`

**Severity:** `fatal`

**Description:** Several public Message helper methods exist in php-impl but are not available on native message objects.

### Probe Code

```php
$message = new DoubleValue(['value' => 1.5]);
return [
    '__debugInfo' => method_exists($message, '__debugInfo'),
    'byteSize' => method_exists($message, 'byteSize'),
    'jsonByteSize' => method_exists($message, 'jsonByteSize'),
    'parseFromStream' => method_exists($message, 'parseFromStream'),
    'serializeToStream' => method_exists($message, 'serializeToStream'),
];
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "__debugInfo": true,
        "byteSize": true,
        "jsonByteSize": true,
        "parseFromStream": true,
        "serializeToStream": true
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "__debugInfo": false,
        "byteSize": false,
        "jsonByteSize": false,
        "parseFromStream": false,
        "serializeToStream": false
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Use portable public APIs: `serializeToString()`, `mergeFromString()`, `serializeToJsonString()`, `mergeFromJsonString()`, `clear()`, and `discardUnknownFields()`. Do not call php-impl-only stream/size/debug helpers.

### Migration Example

```php
// GOOD
$bytes = $message->serializeToString();
$json = $message->serializeToJsonString();

// BAD
$size = $message->byteSize();
$debug = $message->__debugInfo();
```

## `message.call_debug_info`

**Severity:** `fatal`

**Description:** Calling __debugInfo() returns structured debug data in php-impl but is an undefined method in native.

### Probe Code

```php
$message = new DoubleValue(['value' => 1.5]);
return $message->__debugInfo();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   'value' => 1.5, ).` |
| `native` | `0` | `Exception: Call to undefined method Google\Protobuf\DoubleValue::__debugInfo()` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": {
        "value": 1.5
    },
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Error",
        "message": "Call to undefined method Google\\Protobuf\\DoubleValue::__debugInfo()",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Do not call `__debugInfo()` directly for protobuf messages under native. Build explicit diagnostic arrays from getters instead.

### Migration Example

```php
// GOOD
$debug = [
    'value' => $message->getValue(),
];

// BAD
$debug = $message->__debugInfo();
```

## `message.call_byte_size`

**Severity:** `fatal`

**Description:** Message::byteSize() is public in php-impl but unavailable in native.

### Probe Code

```php
$message = new DoubleValue();
return $message->byteSize();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned int: 0.` |
| `native` | `0` | `Exception: Call to undefined method Google\Protobuf\DoubleValue::byteSize()` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": 0,
    "return_type": "int",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Error",
        "message": "Call to undefined method Google\\Protobuf\\DoubleValue::byteSize()",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Avoid `byteSize()` in migration-safe code. If size is needed, serialize and measure the byte string.

### Migration Example

```php
// GOOD
$size = strlen($message->serializeToString());

// BAD
$size = $message->byteSize();
```

## `message.merge_from_wrong_class`

**Severity:** `throw`

**Description:** mergeFrom() with a different message class emits a non-fatal notice in php-impl but throws a TypeError in native.

### Probe Code

```php
$target = new DoubleValue();
$target->mergeFrom(new StringValue());
return $target->getValue();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Warning: Cannot merge messages with different class.` |
| `native` | `0` | `Exception: Google\Protobuf\Internal\Message::mergeFrom(): Argument #1 ($data) must be of type Google\Protobuf\DoubleValue, Google\Protobuf\StringValue given` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": 0,
    "return_type": "float",
    "warnings": [
        {
            "type": 1024,
            "message": "Cannot merge messages with different class.",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/Internal/Message.php",
            "line": 695
        }
    ],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\Internal\\Message::mergeFrom(): Argument #1 ($data) must be of type Google\\Protobuf\\DoubleValue, Google\\Protobuf\\StringValue given",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Only call `mergeFrom()` with the exact same generated message class. Validate dynamic message types before merging.

### Migration Example

```php
// GOOD
if (get_class($target) !== get_class($source)) {
    throw new InvalidArgumentException('cannot merge different message classes');
}
$target->mergeFrom($source);

// BAD
$target->mergeFrom($source);
```

## `message.constructor_non_array`

**Severity:** `type difference`

**Description:** Message constructors reject non-array data with different exception classes/messages.

### Probe Code

```php
new DoubleValue('not an array');
return true;
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Message constructor must be an array or null.` |
| `native` | `0` | `Exception: Google\Protobuf\DoubleValue::__construct(): Argument #1 ($data) must be of type ?array, string given` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "InvalidArgumentException",
        "message": "Message constructor must be an array or null.",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\DoubleValue::__construct(): Argument #1 ($data) must be of type ?array, string given",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Pass only arrays or null to generated message constructors; do not branch on the exact exception type/message for invalid constructor input.

### Migration Example

```php
// GOOD
$message = new DoubleValue(['value' => 1.5]);

// BAD
$message = new DoubleValue($possiblyScalar);
```

## `message.constructor_unknown_field`

**Severity:** `type difference`

**Description:** Unknown constructor array fields throw different exception classes/messages.

### Probe Code

```php
new DoubleValue(['bad' => 1]);
return true;
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Invalid message property: bad` |
| `native` | `0` | `Exception: No such field bad` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "UnexpectedValueException",
        "message": "Invalid message property: bad",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "No such field bad",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Validate constructor array keys against generated field names if invalid input is expected; do not rely on php-impl exception details.

### Migration Example

```php
// GOOD
$message = new DoubleValue(['value' => 1.5]);

// BAD
$message = new DoubleValue($unvalidatedData);
```

## `message.setValue_string`

**Severity:** `type difference`

**Description:** Generated scalar setters reject string input in php-impl but coerce compatible strings in native.

### Probe Code

```php
$message = new DoubleValue();
$message->setValue('1.5');
return $message->getValue();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Google\Protobuf\DoubleValue::setValue(): Argument #1 ($var) must be of type float, string given, called in /Users/joe.cai/git/php-protobuf-migration-checks/cases/message.php on line 215` |
| `native` | `0` | `Returned float: 1.5.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\DoubleValue::setValue(): Argument #1 ($var) must be of type float, string given, called in /Users/joe.cai/git/php-protobuf-migration-checks/cases/message.php on line 215",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": 1.5,
    "return_type": "float",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Cast numeric strings before calling generated scalar setters. Native may coerce strings that php-impl rejects at the type level.

### Migration Example

```php
// GOOD
$message->setValue((float) $value);

// BAD
$message->setValue($value);
```

## `message.setValue_null`

**Severity:** `throw`

**Description:** Generated scalar setters reject null in php-impl but attempt conversion in native.

### Probe Code

```php
$message = new DoubleValue();
$message->setValue(null);
return $message->getValue();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Google\Protobuf\DoubleValue::setValue(): Argument #1 ($var) must be of type float, null given, called in /Users/joe.cai/git/php-protobuf-migration-checks/cases/message.php on line 244` |
| `native` | `0` | `Exception: Cannot convert '' to double` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\DoubleValue::setValue(): Argument #1 ($var) must be of type float, null given, called in /Users/joe.cai/git/php-protobuf-migration-checks/cases/message.php on line 244",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '' to double",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Normalize nullable scalars before calling generated setters. Null handling is not portable across implementations.

### Migration Example

```php
// GOOD
if ($value !== null) {
    $message->setValue($value);
}

// BAD
$message->setValue($value);
```

## `message.mergeFromString_garbage`

**Severity:** `throw`

**Description:** mergeFromString() silently accepts invalid wire bytes in php-impl but throws in native.

### Probe Code

```php
$message = new DoubleValue();
$message->mergeFromString("\xff\xff");
return 'parsed';
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned string: 'parsed'.` |
| `native` | `0` | `Exception: Error occurred during parsing` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": "parsed",
    "return_type": "string",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Error occurred during parsing",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Treat mergeFromString() input as untrusted only when you can handle parse failures. Native rejects malformed wire payloads that php-impl may ignore.

### Migration Example

```php
// GOOD
try {
    $message->mergeFromString($bytes);
} catch (Exception $e) {
    throw new InvalidArgumentException('invalid protobuf payload', 0, $e);
}

// BAD
$message->mergeFromString($bytes);
```

## `message.mergeFromJsonString_unknown_field`

**Severity:** `type difference`

**Description:** mergeFromJsonString() with an unknown field fails differently: php-impl throws while coercing parsed data, native rejects during JSON parsing.

### Probe Code

```php
$message = new DoubleValue();
$message->mergeFromJsonString('{"value":1.5,"unknown":true}');
return $message->serializeToJsonString();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Google\Protobuf\DoubleValue::setValue(): Argument #1 ($var) must be of type float, array given, called in /Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/Internal/Message.php on line 1158` |
| `native` | `0` | `Exception: Error occurred during parsing: Error parsing JSON @1:0: Expected number or string` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\DoubleValue::setValue(): Argument #1 ($var) must be of type float, array given, called in /Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/Internal/Message.php on line 1158",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Error occurred during parsing: Error parsing JSON @1:0: Expected number or string",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Do not rely on unknown JSON fields being ignored. Strip or validate JSON payloads before mergeFromJsonString().

### Migration Example

```php
// GOOD
$data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
unset($data['unknown']);
$message->mergeFromJsonString(json_encode($data, JSON_THROW_ON_ERROR));

// BAD
$message->mergeFromJsonString($jsonWithUnknownFields);
```

## `repeated.missing_index`

**Severity:** `fatal`

**Description:** Reading a missing RepeatedField index returns null in php-impl but fatals in native.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
return $values[0];
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Warning: Undefined array key 0` |
| `native` | `255` | `Fatal: Element at 0 doesn't exist. ` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": null,
    "return_type": "null",
    "warnings": [
        {
            "type": 2,
            "message": "Undefined array key 0",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/RepeatedField.php",
            "line": 102
        }
    ],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Element at 0 doesn't exist.\n",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/cases/repeated.php",
        "line": 33
    },
    "stderr": "PHP Fatal error:  Element at 0 doesn't exist.\n in /Users/joe.cai/git/php-protobuf-migration-checks/cases/repeated.php on line 33"
}
```

</details>

### Migration Note

Do not read a repeated field index unless `isset($values[$i])` is true.

### Migration Example

```php
// GOOD
if (isset($message->getIds()[$i])) {
    $id = $message->getIds()[$i];
}

// BAD
$id = $message->getIds()[$i];
```

## `repeated.offset_exists_non_int`

**Severity:** `fatal`

**Description:** `isset($repeated[$key])` with a non-integer key returns false in php-impl but throws in native.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
return isset($values['missing']);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned bool: false.` |
| `native` | `0` | `Exception: Google\Protobuf\RepeatedField::offsetExists(): Argument #1 ($index) must be of type int, string given` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": false,
    "return_type": "bool",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\RepeatedField::offsetExists(): Argument #1 ($index) must be of type int, string given",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Only use integer offsets with RepeatedField. Cast or validate external offsets before using ArrayAccess.

### Migration Example

```php
// GOOD
if (is_int($offset) && isset($values[$offset])) {
    $value = $values[$offset];
}

// BAD
if (isset($values[$request['offset']])) {
    $value = $values[$request['offset']];
}
```

## `repeated.explicit_index_at_count`

**Severity:** `silent`

**Description:** Assigning `$repeated[count($repeated)] = $value` fatals in php-impl but appends in native.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
$values[] = 1;
$values[1] = 2;
return iterator_to_array($values);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `255` | `Fatal: Cannot modify element at the given index` |
| `native` | `0` | `Returned array: array (   0 => 1,   1 => 2, ).` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [
        {
            "type": 8192,
            "message": "Passing E_USER_ERROR to trigger_error() is deprecated since 8.4, throw an exception or call exit with a string message instead",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/RepeatedField.php",
            "line": 170
        }
    ],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Cannot modify element at the given index",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/RepeatedField.php",
        "line": 170
    }
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        1,
        2
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Append with `$repeated[] = $value`; do not assign explicit indexes except to replace an existing element.

### Migration Example

```php
// GOOD
$values[] = 2;

// BAD
$values[count($values)] = 2;
```

## `repeated.null_string_value`

**Severity:** `throw`

**Description:** Appending null to a string RepeatedField coerces to an empty string in php-impl but throws in native.

### Probe Code

```php
$values = new RepeatedField(GPBType::STRING);
$values[] = null;
return iterator_to_array($values);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   0 => '', ).` |
| `native` | `0` | `Exception: Cannot convert '' to string` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        ""
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '' to string",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Normalize nullable strings before appending. Native does not apply php-impl `strval(null)` coercion.

### Migration Example

```php
// GOOD
if ($label !== null) {
    $labels[] = $label;
}

// BAD
$labels[] = $label;
```

## `repeated.null_bool_value`

**Severity:** `throw`

**Description:** Appending null to a bool RepeatedField coerces to false in php-impl but throws in native.

### Probe Code

```php
$values = new RepeatedField(GPBType::BOOL);
$values[] = null;
return iterator_to_array($values);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   0 => false, ).` |
| `native` | `0` | `Exception: Cannot convert '' to bool` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        false
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '' to bool",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Normalize nullable booleans explicitly before appending. Native does not apply php-impl `boolval(null)` coercion.

### Migration Example

```php
// GOOD
if ($enabled !== null) {
    $flags[] = (bool) $enabled;
}

// BAD
$flags[] = $enabled;
```

## `repeated.integer_scientific_string`

**Severity:** `throw`

**Description:** php-impl accepts scientific-notation strings for integer fields, while native rejects them.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
$values[] = '1e2';
return iterator_to_array($values);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   0 => 100, ).` |
| `native` | `0` | `Exception: Cannot convert '1e2' to integer` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        100
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '1e2' to integer",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Pass integer fields as integers or plain decimal strings. Do not rely on PHP `is_numeric()` accepting exponent notation.

### Migration Example

```php
// GOOD
$ids[] = 100;

// BAD
$ids[] = '1e2';
```

## `repeated.int32_overflow`

**Severity:** `silent`

**Description:** Out-of-range int32 values remain oversized in php-impl containers but wrap to int32 in native containers.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
$values[] = 2147483648;
return iterator_to_array($values);
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        2147483648
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        -2147483648
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Validate int32 ranges before assignment. Native stores the C int32 value immediately; php-impl may leave an oversized PHP integer in memory.

### Migration Example

```php
// GOOD
if ($id < -2147483648 || $id > 2147483647) {
    throw new InvalidArgumentException('id is outside int32 range');
}
$ids[] = $id;

// BAD
$ids[] = $id;
```

## `repeated.uint64_max_string`

**Severity:** `throw`

**Description:** A uint64 string above PHP_INT_MAX is saturated by php-impl on 64-bit PHP but rejected by native.

### Probe Code

```php
$values = new RepeatedField(GPBType::UINT64);
$values[] = '18446744073709551615';
return iterator_to_array($values);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   0 => 9223372036854775807, ).` |
| `native` | `0` | `Exception: Cannot convert '18446744073709551615' to integer` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        9223372036854775807
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '18446744073709551615' to integer",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Treat uint64 values above PHP_INT_MAX as a compatibility hotspot. Add explicit tests before moving uint64-heavy code to native.

### Migration Example

```php
// GOOD
// GOOD: keep uint64 boundary behavior covered by implementation-specific tests.
$message->setCounter($counter);

// BAD
// BAD: assuming every decimal uint64 string accepted by php-impl is accepted by native.
$counters[] = '18446744073709551615';
```

## `repeated.int64_max_string`

**Severity:** `throw`

**Description:** An int64 decimal string above PHP_INT_MAX is saturated by php-impl on 64-bit PHP but rejected by native.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT64);
$values[] = '9999999999999999999';
return iterator_to_array($values);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Returned array: array (   0 => 9223372036854775807, ).` |
| `native` | `0` | `Exception: Cannot convert '9999999999999999999' to integer` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        9223372036854775807
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Cannot convert '9999999999999999999' to integer",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Validate int64 string inputs against PHP_INT_MAX before assignment. Native rejects oversized decimal strings that php-impl silently saturates.

### Migration Example

```php
// GOOD
if (bccomp($value, (string) PHP_INT_MAX) > 0) {
    throw new InvalidArgumentException('id exceeds int64 range');
}
$ids[] = $value;

// BAD
$ids[] = $value;
```

## `repeated.iterator_current_invalid`

**Severity:** `fatal`

**Description:** Calling RepeatedFieldIter::current() when invalid returns null with a warning in php-impl but fatals in native.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
$iterator = $values->getIterator();
return $iterator->current();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Warning: Undefined array key 0` |
| `native` | `255` | `Fatal: Element at 0 doesn't exist. ` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": null,
    "return_type": "null",
    "warnings": [
        {
            "type": 2,
            "message": "Undefined array key 0",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/Internal/RepeatedFieldIter.php",
            "line": 66
        }
    ],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Element at 0 doesn't exist.\n",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/cases/repeated.php",
        "line": 296
    },
    "stderr": "PHP Fatal error:  Element at 0 doesn't exist.\n in /Users/joe.cai/git/php-protobuf-migration-checks/cases/repeated.php on line 296"
}
```

</details>

### Migration Note

Only call iterator `current()` after `valid()` is true. Normal `foreach` usage is safe.

### Migration Example

```php
// GOOD
$iterator = $values->getIterator();
if ($iterator->valid()) {
    $value = $iterator->current();
}

// BAD
$value = $values->getIterator()->current();
```

## `repeated.unset_missing_index`

**Severity:** `fatal`

**Description:** unset($repeated[$missingIndex]) is fatal in both implementations but with different error messages.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
unset($values[0]);
return 'ok';
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `255` | `Fatal: Cannot remove element at the given index` |
| `native` | `255` | `Fatal: Google\Protobuf\RepeatedField::offsetUnset(): Cannot remove element at 0. ` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [
        {
            "type": 8192,
            "message": "Passing E_USER_ERROR to trigger_error() is deprecated since 8.4, throw an exception or call exit with a string message instead",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/RepeatedField.php",
            "line": 196
        }
    ],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Cannot remove element at the given index",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/RepeatedField.php",
        "line": 196
    }
}
```

#### native JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Google\\Protobuf\\RepeatedField::offsetUnset(): Cannot remove element at 0.\n",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/cases/repeated.php",
        "line": 326
    },
    "stderr": "PHP Fatal error:  Google\\Protobuf\\RepeatedField::offsetUnset(): Cannot remove element at 0.\n in /Users/joe.cai/git/php-protobuf-migration-checks/cases/repeated.php on line 326"
}
```

</details>

### Migration Note

Do not unset missing repeated-field indexes. Use generated setters or rebuild the RepeatedField instead of sparse ArrayAccess removal.

### Migration Example

```php
// GOOD
$values = new RepeatedField(GPBType::INT32);
foreach ($sourceValues as $value) {
    $values[] = $value;
}

// BAD
unset($values[$index]);
```

## `repeated.mutation_during_iteration`

**Severity:** `silent`

**Description:** Appending during foreach is invisible in php-impl (iterator snapshots the container array) but visible in native (iterator reads the live array).

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
$values[] = 1;
$values[] = 2;
$values[] = 3;
$collected = [];
foreach ($values as $i => $v) {
    if ($i === 0) {
        $values[] = 4;
    }
    $collected[] = $v;
}
return $collected;
```

### Raw Output Comparison

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        1,
        2,
        3
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        1,
        2,
        3,
        4
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

### Migration Note

Do not mutate a RepeatedField that is currently being iterated. Snapshot to a plain array with `iterator_to_array()` first if you need to append while reading.

### Migration Example

```php
// GOOD
$snapshot = iterator_to_array($values);
foreach ($snapshot as $v) {
    if (needsExtra($v)) {
        $values[] = computeExtra($v);
    }
}

// BAD
foreach ($values as $v) {
    if (needsExtra($v)) {
        $values[] = computeExtra($v);
    }
}
```

## `repeated.offset_set_string_key`

**Severity:** `fatal`

**Description:** Assigning with a string numeric offset fatals in php-impl but appends/stores in native.

### Probe Code

```php
$values = new RepeatedField(GPBType::INT32);
$values['0'] = 1;
return iterator_to_array($values);
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `255` | `Fatal: Cannot modify element at the given index` |
| `native` | `0` | `Returned array: array (   0 => 1, ).` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [
        {
            "type": 8192,
            "message": "Passing E_USER_ERROR to trigger_error() is deprecated since 8.4, throw an exception or call exit with a string message instead",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/RepeatedField.php",
            "line": 170
        }
    ],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Cannot modify element at the given index",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/RepeatedField.php",
        "line": 170
    }
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": [
        1
    ],
    "return_type": "array",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Cast repeated-field offsets to integers before assignment. Native accepts string numeric keys that php-impl rejects.

### Migration Example

```php
// GOOD
$values[(int) $offset] = $value;

// BAD
$values[$offset] = $value;
```

## `timestamp.from_datetime_immutable`

**Severity:** `silent`

**Description:** Timestamp::fromDateTime() accepts only DateTime in php-impl but accepts DateTimeInterface in native.

### Probe Code

```php
$timestamp = new Timestamp();
$timestamp->fromDateTime(new DateTimeImmutable('2020-01-01T00:00:00Z'));
return $timestamp->getSeconds();
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Google\Protobuf\Internal\TimestampBase::fromDateTime(): Argument #1 ($datetime) must be of type DateTime, DateTimeImmutable given, called in /Users/joe.cai/git/php-protobuf-migration-checks/cases/timestamp.php on line 32` |
| `native` | `0` | `Returned int: 1577836800.` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\Internal\\TimestampBase::fromDateTime(): Argument #1 ($datetime) must be of type DateTime, DateTimeImmutable given, called in /Users/joe.cai/git/php-protobuf-migration-checks/cases/timestamp.php on line 32",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "returned",
    "return": 1577836800,
    "return_type": "int",
    "warnings": [],
    "exception": null,
    "fatal": null
}
```

</details>

### Migration Note

Prefer `DateTimeInterface` at application boundaries, but if code must run on both implementations, convert DateTimeImmutable to DateTime before calling fromDateTime().

### Migration Example

```php
// GOOD
$datetime = $input instanceof DateTimeImmutable
    ? DateTime::createFromImmutable($input)
    : $input;
$timestamp->fromDateTime($datetime);

// BAD
$timestamp->fromDateTime(new DateTimeImmutable('2020-01-01T00:00:00Z'));
```

## `wkt.any_unpack_missing_prefix`

**Severity:** `type difference`

**Description:** Any::unpack() rejects a type_url without the expected prefix in both runtimes, but the php-impl error message has a misspelling ("qulified") that native does not.

### Probe Code

```php
$any = new Any();
$any->setTypeUrl('google.protobuf.DoubleValue');
$any->unpack();
return 'unreachable';
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Type url needs to be type.googleapis.com/fully-qulified` |
| `native` | `0` | `Exception: Type url needs to be type.googleapis.com/fully-qualified` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Type url needs to be type.googleapis.com/fully-qulified",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Type url needs to be type.googleapis.com/fully-qualified",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Do not match on Any::unpack() exception messages. Validate type_url prefixes yourself before unpacking if you need stable error wording.

### Migration Example

```php
// GOOD
if (!str_starts_with($any->getTypeUrl(), 'type.googleapis.com/')) {
    throw new InvalidArgumentException('unexpected type_url');
}
$message = $any->unpack();

// BAD
try {
    $message = $any->unpack();
} catch (Exception $e) {
    if (str_contains($e->getMessage(), 'qulified')) {
        // brittle: php-impl typo that native does not emit
    }
}
```

## `wkt.any_unpack_unregistered`

**Severity:** `type difference`

**Description:** Any::unpack() against a type_url whose message is not in the descriptor pool throws different messages in each runtime.

### Probe Code

```php
$any = new Any();
$any->setTypeUrl('type.googleapis.com/example.NotRegistered');
$any->unpack();
return 'unreachable';
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `0` | `Exception: Class example.NotRegistered hasn't been added to descriptor pool` |
| `native` | `0` | `Exception: Specified message in any hasn't been added to descriptor pool` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Class example.NotRegistered hasn't been added to descriptor pool",
        "code": 0
    },
    "fatal": null
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "Exception",
        "message": "Specified message in any hasn't been added to descriptor pool",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

When you cannot guarantee the target message class is loaded, gate unpack() on `is($class)` rather than catching exceptions by message.

### Migration Example

```php
// GOOD
if ($any->is(DoubleValue::class)) {
    $message = $any->unpack();
}

// BAD
try {
    $message = $any->unpack();
} catch (Exception $e) {
    if (str_contains($e->getMessage(), "hasn't been added to descriptor pool")) {
        // wording differs between runtimes
    }
}
```

## `wkt.any_pack_non_message`

**Severity:** `fatal`

**Description:** Any::pack() with a non-Message argument fatals in php-impl via trigger_error(E_USER_ERROR) but throws a TypeError in native.

### Probe Code

```php
$any = new Any();
$any->pack('not a message');
return 'unreachable';
```

### Output Comparison

| Runtime | Exit | Outcome |
|---|---:|---|
| `php-impl` | `255` | `Fatal: Given parameter is not a message instance.` |
| `native` | `0` | `Exception: Google\Protobuf\Any::pack(): Argument #1 ($value) must be of type object, string given` |

<details>
<summary>Raw Output</summary>

#### php-impl JSON

```json
{
    "exit_code": 255,
    "status": "fatal",
    "return": null,
    "return_type": null,
    "warnings": [
        {
            "type": 8192,
            "message": "Passing E_USER_ERROR to trigger_error() is deprecated since 8.4, throw an exception or call exit with a string message instead",
            "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/Internal/AnyBase.php",
            "line": 58
        }
    ],
    "exception": null,
    "fatal": {
        "type": 256,
        "message": "Given parameter is not a message instance.",
        "file": "/Users/joe.cai/git/php-protobuf-migration-checks/protobuf/php/src/Google/Protobuf/Internal/AnyBase.php",
        "line": 58
    }
}
```

#### native JSON

```json
{
    "exit_code": 0,
    "status": "threw",
    "return": null,
    "return_type": null,
    "warnings": [],
    "exception": {
        "class": "TypeError",
        "message": "Google\\Protobuf\\Any::pack(): Argument #1 ($value) must be of type object, string given",
        "code": 0
    },
    "fatal": null
}
```

</details>

### Migration Note

Always pass a Message instance to Any::pack(). php-impl produces a non-catchable fatal; native throws a catchable TypeError. Do not rely on either failure shape.

### Migration Example

```php
// GOOD
if ($payload instanceof Message) {
    $any->pack($payload);
}

// BAD
$any->pack($payload);
```

