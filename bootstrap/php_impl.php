<?php

declare(strict_types=1);

if (extension_loaded('protobuf')) {
    throw new RuntimeException(
        'php-impl mode must run without ext-protobuf. Use php -n for this mode.'
    );
}

require __DIR__ . '/autoload.php';
