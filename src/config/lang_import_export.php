<?php
return [
    'base_locale' => 'en',
    'export_locale' => 'en',// locale list separated by comma
    'export_target' => null,
    'base_group' => null, // all groups or comma separated list
    'export_path' => storage_path(':locale:target.csv'),
];
