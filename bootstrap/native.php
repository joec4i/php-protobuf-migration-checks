<?php

declare(strict_types=1);

if (!extension_loaded('protobuf')) {
    throw new RuntimeException(
        'native mode requires ext-protobuf. Run with a PHP binary/configuration that loads protobuf.'
    );
}

require __DIR__ . '/autoload.php';
