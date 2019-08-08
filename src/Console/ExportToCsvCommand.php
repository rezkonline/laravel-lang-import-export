<?php

namespace LangImportExport\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use LangImportExport\Facades\LangListService;

class ExportToCsvCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:export 
    						{--l|locale= : The locales to be exported. Separated by comma (default - base locale from config).} 
    						{--t|target= : Target languages, only missing keys are exported. Separated by comma.} 
    						{--g|group= : The name of translation file to export (default - base group from config).} 
    						{--o|output= : Filename of exported translation, :locale, :target is replaced (default - export_path from config).} 
    						{--z|zip= : Zip all files.}
    						{--X|excel : Set file encoding for Excel (optional, default - UTF-8).}
    						{--D|delimiter=, : Field delimiter (optional, default - ",").} 
    						{--E|enclosure=" : Field enclosure (optional, default - \'"\').} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Exports language files to CSV file";

    /**
     * List of files created by the export
     * @var array
     */
    protected $files = [];

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        foreach ($this->strToArray($this->option('locale')) as $locale) {
            foreach ($this->strToArray($this->option('target'), [null]) as $target) {
                $translations = $this->getTranslations($locale, $target);
                $this->saveTranslations($locale, $target, $translations);
                $this->info(strtoupper($locale) . strtoupper($target ?: '') . ' Translations saved to: ' . $this->getOutputFileName($locale, $target));
            }
        }
        if ($zipName = $this->option('zip')) {
            $this->info('Creating archive...');
            $zip = new \ZipArchive;
            if (!$zip->open($zipName, \ZipArchive::CREATE)) {
                throw new \Exception("Failed to open $zipName");
            }
            foreach ($this->files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            $this->info('Cleaning up the files...');
            foreach ($this->files as $file) {
                unlink($file);
            }
        }
    }

    private function strToArray($string, $fallback = [])
    {
        if (!$string) {
            return $fallback;
        }
        return array_filter(array_map('trim', explode(',', $string)));
    }

    /**
     * Get translations from localization files.
     *
     * @param $locale
     * @param null $target
     * @return array
     */
    private function getTranslations($locale, $target = null)
    {
        $group = $this->option('group') ?: config('lang_import_export.base_group');
        $from = LangListService::loadLangList($locale, $group);
        if ($target) {
            $targetList = LangListService::loadLangList($target, $group);
            foreach ($targetList as $group => $translations) {
                foreach ($translations as $key => $v) {
                    unset($from[$group][$key]);
                }
            }
        }
        return $from;
    }

    /**
     * Save fetched translations to file.
     *
     * @param $locale
     * @param $target
     * @param $translations
     * @return void
     */
    private function saveTranslations($locale, $target, $translations)
    {
        $output = $this->openFile($locale, $target);

        $this->saveTranslationsToFile($output, $translations);

        $this->closeFile($output);
    }

    /**
     * Open specified file (if not possible, open default one).
     *
     * @param $locale
     * @param $target
     * @throws \Exception
     * @return resource
     */
    private function openFile($locale, $target)
    {
        $fileName = $this->getOutputFileName($locale, $target);

        if (!($output = fopen($fileName, 'w'))) {
            throw new \Exception("$fileName failed to open");
        }
        $this->files[] = $fileName;

        fputs($output, "\xEF\xBB\xBF");

        return $output;
    }

    /**
     * Save content of translation files to specified file.
     *
     * @param resource $output
     * @param array $translations
     * @return void
     */
    private function saveTranslationsToFile($output, $translations)
    {
        foreach ($translations as $group => $files) {
            foreach ($files as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $this->writeFile($output, $group, $key, $value);
            }
        }
    }

    /**
     * Put content of file to specified file with CSV parameters.
     *
     * @param FilePointerResource $output
     * @param string $group
     * @param string $key
     * @param string $value
     * @return void
     *
     */
    private function writeFile()
    {
        $data = func_get_args();
        $output = array_shift($data);
        fputcsv($output, $data, $this->option('delimiter'), $this->option('enclosure'));
    }

    /**
     * Close output file and check if adjust file to Excel format.
     *
     * @param FilePointerResource $output
     * @return void
     */
    private function closeFile($output)
    {
        fclose($output);

        if ($this->option('excel')) {
            $this->adjustToExcel();
        }
    }

    /**
     * Adjust file to Excel format.
     *
     * @return void
     *
     */
    private function adjustToExcel()
    {
        $data = file_get_contents($this->option('output'));
        file_put_contents($this->option('output'),
            chr(255) . chr(254) . mb_convert_encoding($data, 'UTF-16LE', 'UTF-8'));
    }

    /**
     * @param $locale
     * @param null $target
     * @return mixed
     */
    private function getOutputFileName($locale, $target = null)
    {
        $fileName = $this->option('output');
        $fileName = str_replace(':locale', $locale, $fileName);
        $fileName = str_replace(':target', $target, $fileName);
        return $fileName;
    }
}
