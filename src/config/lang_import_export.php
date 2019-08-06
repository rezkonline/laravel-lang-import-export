<?php
return [
    'export' => [
        // * for all or comma separated
        'locale' => 'en',
        'target' => null,
        'group' => null,
        'excel' => null,
        'zip' => null,
        'output' => storage_path(':locale:target.csv'),
    ],
    'import' => [
        'input' => null,
        'locale' => null,
        'group' => null,
        'delimiter' => null,
        'enclosure' => null,
        'escape' => null,
        'excel' => null,
    ]
];
