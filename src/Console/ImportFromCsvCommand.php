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
    						{--g|group= : The name of translation file to imported (default - all files).} 
    						{--D|delimiter=, : Field delimiter (optional, default - ",").} 
    						{--E|enclosure=" : Field enclosure (optional, default - \'"\').} 
    						{--escape=" : Field escape (optional, default - \'"\').}
    						{--X|excel : Set file encoding from Excel (optional, default - UTF-8).}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Imports the CSV file and write content into language files.";

    /**
     * Parameters provided to command.
     *
     * @var array
     */
    protected $parameters = [];


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->getParameters();
        $fileName = $this->parameters['input'];
        if (!$this->parameters['locale']) {
            $locale = pathinfo($fileName, PATHINFO_FILENAME);
            $this->parameters['locale'] = $locale;
            if (file_exists(resource_path("lang/$locale"))) {
                $this->info("Detected locale $locale");
            } else {
                throw new \Exception("Could not detect locale of $fileName");
            }
        }
        $translations = $this->readTranslations($fileName);
        LangListService::writeLangList($this->parameters['locale'], $this->parameters['group'], $translations);
    }

    /**
     * Fetch command parameters (arguments and options) and analyze them.
     *
     * @return void
     */
    private function getParameters()
    {
        $parameters = [
            'input' => $this->argument('input'),
            'locale' => $this->option('locale'),
            'group' => $this->option('group'),
            'delimiter' => $this->option('delimiter'),
            'enclosure' => $this->option('enclosure'),
            'escape' => $this->option('escape'),
            'excel' => $this->option('excel'),
        ];
        $parameters = array_filter($parameters, function ($var) {
            return !is_null($var);
        });
        $this->parameters = array_merge(config('lang_import_export.import'), $parameters);
    }



    /**
     * Get translations from CSV file.
     *
     * @return array
     */
    private function readTranslations($fileName)
    {
        if (($input = fopen($fileName, 'r')) === false) {
            throw new \Exception('File can not be opened ' . $fileName);
        }
        $translations = $this->readFile($input);
        fclose($input);

        return $translations;
    }

    /**
     * Read content of file.
     *
     * @param FilePointer $input
     * @return array
     * @throws \Exception
     */
    private function readFile($input)
    {
        if ($this->parameters['excel']) {
            $this->adjustFromExcel();
        }

        $translations = [];
        $confirmed = false;
        while (($data = fgetcsv($input, 0, $this->parameters['delimiter'], $this->parameters['enclosure'],
                $this->parameters['escape'])) !== false) {
            if (isset($translations[$data[0]]) == false) {
                $translations[$data[0]] = [];
            }

            $columns = sizeof($data);
            if ($columns < 3) {
                throw new \Exception("File has only $columns column/s");
            }
            if ($columns > 3 &&
                !($confirmed || $confirmed = $this->confirm("File contains $columns columns, continue?"))
            ) {
                throw new \Exception('Canceled by user');
            }

            $translations[$data[0]][$data[1]] = $data[2];
        }

        return $translations;
    }

    /**
     * Adjust file to Excel format.
     *
     * @return void
     */
    private function adjustFromExcel()
    {
        $data = file_get_contents($this->parameters['input']);
        file_put_contents($this->parameters['input'], mb_convert_encoding($data, 'UTF-8', 'UTF-16'));
    }
}
