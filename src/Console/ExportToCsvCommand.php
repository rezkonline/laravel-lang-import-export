<?php

namespace LangImportExport\Console;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
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
    						{--g|group= : The names of translation files to export (default - group from config).} 
    						{--exclude= : The names of translation files to exclude (default - group from config).} 
    						{--o|output= : Filename of exported translation, :locale, :target is replaced (default - export_path from config).} 
    						{--z|zip= : Zip all files.}
    						{--e|ext= : Type of files extension (available extensions Xls, Xlsx, Ods, Csv, Html, Tcpdf, Mpdf, Dompdf).}
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
        $exportLocales = $this->option('locale') ?: config('lang_import_export.export_locale');
        $targetLocales = $this->option('target') ?: config('lang_import_export.export_target');
        $fileExtensions = $this->option('ext') ?: config('lang_import_export.export_default_extension');
        foreach ($this->strToArray($exportLocales) as $exportLocale) {
            foreach ($this->strToArray($targetLocales, [null]) as $targetLocale) {
                $translations = $this->getTranslations($exportLocale, $targetLocale);
                $wordCount = $this->getTranslatableWordCount($translations);
                $fileName = $this->getOutputFileName($exportLocale, $wordCount, $fileExtensions, $targetLocale);
                $this->saveTranslations($translations, $fileName, $fileExtensions);
                $this->info(strtoupper($exportLocale) . strtoupper($targetLocale ?: '') . ' Translations saved to: ' . $this->getOutputFileName($exportLocale, $wordCount, $fileExtensions, $targetLocale));
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
        $group = $this->option('group') ?: config('lang_import_export.groups');
        $exclude = $this->option('exclude') ?: config('lang_import_export.exclude_groups');
        $from = LangListService::loadLangList($locale, $group, $exclude);
        if ($target) {
            $targetList = LangListService::loadLangList($target, $group, $exclude);
            foreach ($targetList as $group => $translations) {
                foreach ($translations as $key => $v) {
                    unset($from[$group][$key]);
                }
            }
        }
        return $from;
    }

    /**
     * @param $locale
     * @param null $target
     * @return mixed
     */
    private function getOutputFileName($locale, $wordCount, $fileExtension, $target = null)
    {
        $fileName = $this->option('output') ?: config('lang_import_export.export_path');
        $fileName = str_replace(':locale', $locale, $fileName);
        $fileName = str_replace(':target', $target, $fileName);
        $fileName = str_replace(':wordcount', $wordCount, $fileName);
        $fileName = str_replace(':ext', $fileExtension, $fileName);
        return $fileName;
    }

    /**
     * @param $translations
     */
    private function getTranslatableWordCount($translations)
    {
        $wordCount = 0;
        foreach ($translations as $group => $files) {
            foreach ($files as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $wordCount += str_word_count($value);
            }
        }
        return $wordCount;
    }

    /**
     * @param $translations
     * @param $fileName
     * @param $fileExtension
     */
    private function saveTranslations($translations, $fileName, $fileExtension)
    {
        $data = [];
        foreach ($translations as $group => $files) {
            foreach ($files as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $data[] = [$group, $key, $value];
            }
        }
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($data);
        $writer = IOFactory::createWriter($spreadsheet, ucfirst(strtolower($fileExtension)));
        $writer->save($fileName);
        $this->files[] = $fileName;
    }
}
