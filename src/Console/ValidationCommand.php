<?php

namespace LangImportExport\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use LangImportExport\Facades\LangListService;

class ValidationCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:validate 
                            {target? : Locale to be checked.} 
    						{--l|locale= : The locales to be exported. Separated by comma (default - default lang of application).} 
    						{--g|group= : The name of translation file to export (default all groups).}
    						';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Check language files for missing or misspelled placeholders";

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
     * @throws \Exception
     */
    public function handle()
    {
        $this->getParameters();

        $baseTranslations = LangListService::loadLangList($this->parameters['locale'], $this->parameters['group']);
        foreach ($this->strToArray($this->parameters['target']) as $locale) {
            $targetTranslations = LangListService::loadLangList($locale, $this->parameters['group']);
            foreach ($targetTranslations as $group => $translations) {
                foreach ($translations as $key => $translation) {
                    if (isset($baseTranslations[$group][$key])) {
                        $placeholders = $this->matchPlaceholders($baseTranslations[$group][$key]);
                        foreach ($placeholders as $placeholder) {
                            if (strpos($translation, $placeholder) === false) {
                                $this->warning("$locale/$group.$key is missing \"$placeholder\".");
                                $this->info($translation, 'v');
                            }
                        }
                    }
                }
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
     * Fetch command parameters (arguments and options) and analyze them.
     *
     * @return void
     */
    private function getParameters()
    {
        $parameters = [
            'target' => $this->argument('target'),
            'locale' => $this->option('locale'),
            'group' => $this->option('group'),
        ];
        $parameters = array_filter($parameters, function ($var) {
            return !is_null($var);
        });
        $this->parameters = array_merge(config('lang_import_export.check'), $parameters);
    }

    private function matchPlaceholders($translation)
    {
        preg_match_all('~(:[a-zA-Z0-9_]+)~', $translation, $m);
        return $m[1] ?? [];
    }
}
