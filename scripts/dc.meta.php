<?php

return [
    "description" => "run docker-compose",
    "help" => "run docker-compose in the context of the application",
    "options" => [
        'f|file: File',
    ],
    "argumentResolver" => function($options) {
        return [$options['file'] ?? '-'];
    }
];
