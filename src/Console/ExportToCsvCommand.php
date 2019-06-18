<?php

namespace HighSolutions\LangImportExport\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use HighSolutions\LangImportExport\Facades\LangListService;

class ExportToCsvCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:export 
    						{locale? : The locale to be exported (default - default lang of application).} 
    						{group? : The name of translation file to export (default - all files).} 
    						{output? : Filename of exported translation, :locale is replaced (optional, default - :locale-export.csv).} 
    						{--A|append : Append name of group to the name of file (optional, default - empty).}
    						{--X|excel : Set file encoding for Excel (optional, default - UTF-8).}
    						{--D|delimiter=, : Field delimiter (optional, default - ",").} 
    						{--E|enclosure=" : Field enclosure (optional, default - \'"\').} 
    						{--T|target-locale=" : Target language, only missing keys are exported (optional).} ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Exports the language files to CSV file";

	/**
	 * Parameters provided to command.
	 *
	 * @var array
	 */
	protected $parameters = [];

	/**
	 * Default path for file save.
	 *
	 * @var string
	 */
	protected $defaultPath;

	/**
	 * File extension (default .csv).
	 *
	 * @var string
	 */
	protected $ext = '.csv';

	/**
	 * Class constructor.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->defaultPath = base_path(':locale-export') . $this->ext;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function handle()
	{
		$this->getParameters();

		$this->sayItsBeginning();

		foreach (explode(',', $this->parameters['locale']) as $locale) {
            $translations = $this->getTranslations($locale);
            $this->saveTranslations($locale, $translations);
            $this->info(strtoupper($locale) . ' Translations saved to: ' . $this->getOutputFileName($locale));
        }
	}

	/**
	 * Fetch command parameters (arguments and options) and analyze them.
	 *
	 * @return void
	 */
	private function getParameters()
	{
		$this->parameters = [
			'group' => $this->argument('group'),
			'locale' => $this->argument('locale') === null ? config('app.locale') : $this->argument('locale'),
			'output' => $this->argument('output') === null ? $this->defaultPath : base_path($this->argument('output')),
			'append' => $this->option('append') !== false,
			'excel' => $this->option('excel') !== false,
			'delimiter' => $this->option('delimiter'),
			'enclosure' => $this->option('enclosure'),
			'target_locale' => $this->option('target-locale'),
		];

		$this->setDefaultPath();
	}

	/**
	 * Set possible file names.
	 *
	 * @return void
	 */
	private function setDefaultPath()
	{
		if($this->parameters['append']) {
			$this->parameters['output'] .= '-'. $this->parameters['group'];
			$this->defaultPath .= '-'. $this->parameters['group'];
		}
	}

	/**
	 * Display output that command has started and which groups are being exported.
	 *
	 * @return void
	 */
	private function sayItsBeginning()
	{
		$this->info(PHP_EOL
			. 'Translations export of '. ($this->parameters['group'] === null ? 'all groups' : $this->parameters['group'] .' group') .' - started.');
	}

    /**
     * Get translations from localization files.
     *
     * @param $locale
     * @return array
     */
	private function getTranslations($locale)
	{
	    $from = LangListService::loadLangList($locale, $this->parameters['group']);
	    if ($this->parameters['target_locale']) {
            $target = LangListService::loadLangList($this->parameters['target_locale'], $this->parameters['group']);
            foreach ($target as $group => $translations) {
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
     * @param $translations
     * @return void
     */
	private function saveTranslations($locale, $translations)
	{
		$output = $this->openFile($locale);

		$this->saveTranslationsToFile($output, $translations);

		$this->closeFile($output);
	}

	/**
	 * Open specified file (if not possible, open default one).
	 *
	 * @return FilePointerResource
	 */
	private function openFile($locale)
	{
        $fileName = $this->getOutputFileName($locale);
        if(substr($fileName, -4) != $this->ext)
			$fileName .= $this->ext;

		if (!($output = fopen($fileName, 'w'))) {
			$output = fopen($this->defaultPath . $this->ext, 'w');
		}

		fputs($output, "\xEF\xBB\xBF");

		return $output;
	}

	/**
	 * Save content of translation files to specified file.
	 *
	 * @param FilePointerResource $output
	 * @param array $translations
	 * @return void
	 */
	private function saveTranslationsToFile($output, $translations)
	{
		foreach ($translations as $group => $files) {
			foreach($files as $key => $value) {
				if(is_array($value)) {
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
		fputcsv($output, $data, $this->parameters['delimiter'], $this->parameters['enclosure']);
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

		if($this->parameters['excel'])
			$this->adjustToExcel();
	}

	/**
	 * Adjust file to Excel format.
	 *
	 * @return void
	 *
	 */
	private function adjustToExcel()
	{
		$data = file_get_contents($this->parameters['output']);
		file_put_contents($this->parameters['output'], chr(255) . chr(254) . mb_convert_encoding($data, 'UTF-16LE', 'UTF-8'));
	}

    /**
     * @param $locale
     * @return mixed
     */
    private function getOutputFileName($locale)
    {
        $fileName = $this->parameters['output'];
        $fileName = str_replace(':locale', $locale, $fileName);
        return $fileName;
    }

}
