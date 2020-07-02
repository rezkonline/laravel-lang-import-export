<?php
return [
    /**
     * In practice export_locale value is 'en' and export_target is a list of all locales supported by your website
     */
    'base_locale' => 'en',
    'groups' => null, // all groups or comma separated list (e.g. translation file names)
    'exclude_groups' => null, // comma separated list
    'export_locale' => 'en',// locale list separated by comma
    'export_target' => null,// target locale list separated by comma
    'export_path' => storage_path(':locale:target.:ext'),
    'export_default_extension' => 'csv',
    'import_validate_placeholders' => false, // validate placeholders by default
    'import_validate_html' => false, // validate html by default
];
