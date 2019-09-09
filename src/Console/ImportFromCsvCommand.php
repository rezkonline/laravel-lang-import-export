<?php

namespace LangImportExport\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use LangImportExport\Facades\LangListService;

class ImportFromCsvCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:import
    						{input : Filename of file to be imported with translation files.}
							{--l|locale= : The locale to be imported (default - parsed from file name).} 
    						{--g|group= : The name of translation file to imported (default - base group from config).} 
    						{--p|placeholders : Search for missing placeholders in imported keys (see config file for default value).} 
    						{--html : Validate html in imported keys (see config file for default value).} 
    						{--column-map= : Map columns if other columns are used for notes (e.g. "A,B,D").}
    						';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Imports the CSV file and write content into language files.";

    public function handle()
    {
        $fileName = $this->argument('input');
        $locale = $this->option('locale');
        if (!$locale) {
            $locale = pathinfo($fileName, PATHINFO_FILENAME);
            if (file_exists(resource_path("lang/$locale"))) {
                $this->info("Detected locale $locale");
            } else {
                $this->error("Could not detect locale of $fileName");
                return 1;
            }
        }
        $translations = $this->readTranslations($fileName);
        $group = $this->option('group');
        LangListService::writeLangList($locale, $group, $translations);
        if ($this->option('placeholders') || config('lang_import_export.import_validate_placeholders')) {
            $baseTranslations = LangListService::loadLangList(config('lang_import_export.base_locale'), $group);
            foreach (LangListService::validatePlaceholders($translations, $baseTranslations) as $errors) {
                $this->warn("resources/lang/$locale/{$errors['group']}.php {$errors['key']} is missing \"{$errors['placeholder']}\".");
                $this->info($errors['translation'], 'v');
                $this->info($errors['baseTranslation'], 'vv');
            }
        }
        if ($this->option('html') || config('lang_import_export.import_validate_html')) {
            $baseTranslations = LangListService::loadLangList(config('lang_import_export.base_locale'), $group);
            foreach (LangListService::validateHTML($translations, $baseTranslations) as $errors) {
                $this->warn("resources/lang/$locale/{$errors['group']}.php {$errors['key']} is missing `{$errors['tag']}` html tag.");
                $this->info($errors['translation'], 'v');
                $this->info($errors['baseTranslation'], 'vv');
            }
        }
    }


    /**
     * Get translations from CSV file.
     *
     * @return array
     * @throws \Exception
     */
    private function readTranslations($fileName)
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileName);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, false, false, true);

        $translations = [];
        $map = explode(',', $this->option('column-map') ?: 'A,B,C');
        foreach ($rows as $data) {
            if (isset($translations[$data[$map[0]]]) == false) {
                $translations[$data[$map[0]]] = [];
            }
            $columns = count($data);
            if ($columns < 3) {
                throw new \Exception("File has only $columns column/s");
            }
            if ($columns > 3 && !$this->option('column-map')) {
                throw new \Exception("File has $columns columns");
            }
            $translations[$data[$map[0]]][$data[$map[1]]] = $data[$map[2]];
        }

        return $translations;
    }
}
