<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/CaseDefinition.php';

$cases = [];

foreach ([
    __DIR__ . '/class_shape.php',
    __DIR__ . '/descriptor.php',
    __DIR__ . '/gpbutil.php',
    __DIR__ . '/map.php',
    __DIR__ . '/message.php',
    __DIR__ . '/repeated.php',
    __DIR__ . '/timestamp.php',
    __DIR__ . '/wkt.php',
] as $caseFile) {
    foreach (require $caseFile as $case) {
        $cases[$case->id()] = $case;
    }
}

return $cases;
