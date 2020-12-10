<?php


namespace App\Command\Ibpt;


use EMS\CommonBundle\Storage\NotFoundException;
use EMS\CommonBundle\Storage\Service\FileSystemStorage;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use EMS\CoreBundle\Twig\AppExtension;
use GuzzleHttp\Client as HttpClient;
use Elasticsearch\Client;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Service\ContentTypeService;
use function GuzzleHttp\Psr7\mimetype_from_filename;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DocumentUpdateCommand extends Command implements CommandInterface
{

    /** @var ContentTypeService  */
    private $contentTypeService;
    /** @var Client  */
    private $client;
    /** @var array  */
    private $urls;
    /** @var OutputInterface */
    private $output;
    /** @var string */
    private $website;
    /** @var HttpClient */
    private $httpClient;
    /** @var AppExtension */
    private $appExtension;
    /** @var array */
    private $existingTaxonomies;
    /** @var array */
    private $treated;
    /** @var array */
    private $metaNotFound;
    /** @var array */
    private $notImported;
    /** @var array */
    private $frequencies;
    /** @var array */
    private $attributions;
    /** @var array */
    private $frequencyBands;
    /** @var AssetExtractorService */
    private $assetExtractorService;
    /** @var FileService  */
    private $fileService;
    /** @var FileSystemStorage  */
    private  $fileSystemStorage;
    /** @var bool  */
    private  $skipDownload;

    public function __construct(Client $client, ContentTypeService $contentTypeService, AppExtension $appExtension, AssetExtractorService $assetExtractorService, FileService $fileService)
    {
        parent::__construct();
        $this->contentTypeService = $contentTypeService;
        $this->client = $client;
        $this->appExtension = $appExtension;
        $this->fileService = $fileService;
        $this->assetExtractorService = $assetExtractorService;
        $this->fileSystemStorage = $fileService->getStorages()[0];
        $this->treated = [];
        $this->notImported = [];
        $this->metaNotFound = [];
        $this->attributions = [];
        $this->frequencies = [];
        $this->frequencyBands = [];

        $this->httpClient = new HttpClient(['base_uri' => 'https://www.ibpt.be', 'timeout' => 30, 'exceptions' => false]);
    }

    protected static $defaultName = 'ems:job:ibpt:update';

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Update for IBPT')
            ->addArgument('website', InputArgument::OPTIONAL, 'A folder containing all the html files of the original website', 'C:\\dev\\data\\ibpt\\pages\\')
            ->addArgument('csv', InputArgument::OPTIONAL, 'A CSV file with the list of URL\'s', 'C:\\dev\\data\\ibpt\\pages.csv')
            ->addOption('skip-download', '', InputOption::VALUE_NONE, 'Don\'t try tpo download urls');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->website = $input->getArgument('website');
        $csv = $input->getArgument('csv');
        $this->skipDownload = $input->getOption('skip-download');
        $scrollTimeout = '5m';
        $this->output = $output;

        $contentType = $this->contentTypeService->getByName('publication');

        $this->loadFrequencyPlan('/nl/operatoren/radio/frequentiebeheer/frequentieplan/tabel');
        $this->updateFrequencyPlan('/fr/operateurs/radio/gestion-des-frequences/plan-des-frequences/tableau', 'fr');
        $this->updateFrequencyPlan('/en/operators/radio/frequency-management/frequency-plan/table', 'en');

        $this->loadExistingCategories($contentType->getEnvironment()->getAlias());
        $this->loadUrls($csv);
        $output->writeln('');

        /*$output->writeln(sprintf('Fre keys:'));
        foreach ($this->frequencyBands as $value => $label) {
            $output->writeln(sprintf('%s', $value));
        }

        $output->writeln(sprintf('Fre labels:'));
        foreach ($this->frequencyBands as $value => $label) {
            $output->writeln(sprintf('%s', $label));
        }*/

        /*$output->writeln(sprintf('Attributions:'));
        foreach ($this->attributions as $label) {
            $output->writeln(sprintf('%s', $label));
        }*/

        file_put_contents('attributions.txt', print_r($this->attributions, true));
        file_put_contents('notImported.txt', print_r($this->notImported, true));
        file_put_contents('taxonomiesNotFound.txt', print_r(array_keys($this->metaNotFound), true));

        /*$documents = $this->client->search([
            'index' => $contentType->getEnvironment()->getAlias(),
            'type' => $contentType->getName(),
            'size' => 200,
            "scroll" => $scrollTimeout,
            'body' => [
                '_source' => ["title_*", "show_*", "publication_date", "file_*.filename", "file_*.mimetype", "file_*.sha1", "document_date", "category", "closing_date", "type", "description_*", "meta_description_*"],
                'query' => [
                    'term' => ['_id' => '1037c6dd7624cab409f85227af12ee0912af0b63']
                ]
            ],
        ]);/*

        $total = $documents["hits"]["total"];
        $progress = new ProgressBar($output, $total);
        $progress->start();

        while (isset($documents['hits']['hits']) && count($documents['hits']['hits']) > 0) {

            foreach ($documents["hits"]["hits"] as $value) {
                $this->updateDocument($value);
                $progress->advance();
            }
            $scrollId = $documents['_scroll_id'];
            $documents = $this->client->scroll([
                "scroll_id" => $scrollId,
                "scroll" => $scrollTimeout,
            ]);
        }
        $progress->finish();
        $output->writeln("");
        $output->writeln("Update done");
*/
    }

    private function updateFrequencyPlan(string $url, string $locale)
    {
        $xml= new \DOMXPath($this->loadDom($url));
        $links = $xml->query('//*/div[@class="left-content"]/table/tbody/tr[@valign="top"]/td/a');
        /** @var \DOMElement $li */
        foreach ($links as $link) {
            $path = explode('/', $link->getAttribute('href'));
            $normName = array_pop($path);
            if (!isset($this->frequencies[$normName])) {
                $this->output->writeln('Norm not found '.$normName);
                continue;
            }
            $this->frequencies[$normName]['title_'.$locale] = trim($link->nodeValue);
            $this->frequencies[$normName]['meta_description_'.$locale] = str_replace('title', $this->frequencies[$normName]['title_'.$locale], $this->frequencies[$normName]['meta_description_'.$locale]);
            $this->frequencies[$normName]['description_'.$locale] = str_replace('title', $this->frequencies[$normName]['title_'.$locale], $this->frequencies[$normName]['description_'.$locale]);
        }
    }

    private function loadFrequencyPlan(string $url)
    {
        $xml= new \DOMXPath($this->loadDom($url));
        $lis = $xml->query('//*/div[@class="left-content"]/table/tbody/tr[@valign="top"]');
        /** @var \DOMElement $li */
        foreach ($lis as $li) {
            $frequenciesChild = $li->firstChild->nextSibling;
            $shareChild = $frequenciesChild->nextSibling->nextSibling;
            $attributions = $shareChild->nextSibling->nextSibling;
            $applications = $attributions->nextSibling->nextSibling;

            $shareValue = [];
            switch ($shareChild->getAttribute('bgcolor')) {
                case 'white':
                    $shareValue = ['civilian'];
                    break;
                case 'black':
                    $shareValue = ['military'];
                    break;
                case 'gray':
                    $shareValue = ['military','civilian'];
                    break;
            }


            $matches = [];
            $frequencyBandLabel = trim($frequenciesChild->nodeValue);
            preg_match ( '/(?P<from>.*)-(?P<to>.*) (?P<gamme>.?Hz)/' , $frequencyBandLabel, $matches);

            switch ($matches['gamme']) {
                case 'kHz':
                    $coef = 100;
                    break;
                case 'MHz':
                    $coef = 100000;
                    break;
                case 'GHz':
                    $coef = 100000000;
                    break;
                default:
                    throw new \Exception('Error with '.$matches['gamme']);
            }
            $frequencyBand = substr('00000000000'.intval($matches['from']*$coef), -11).'_';
            $frequencyBand .= substr('00000000000'.intval($matches['to']*$coef), -11);

            $this->frequencyBands[$frequencyBand] = $frequencyBandLabel;

            $attributionValues = [];
            foreach ($attributions->childNodes as $childNode) {
                if (!in_array(trim($childNode->nodeValue),['', 'niet toegewezen'])){
                    $attributionValues[] = trim($childNode->nodeValue);
                    if (!in_array(trim($childNode->nodeValue), $this->attributions)) {
                        $this->attributions[] = trim($childNode->nodeValue);
                    }
                }
            }


            $source = [
                //'shared_status' => $shareValue,
                'frequency_band' => [$frequencyBand],
                //'attributions' => $attributionValues,
            ];

            /** @var \DOMElement $childNode */
            foreach ($applications->childNodes as $childNode) {
                if ($childNode->nodeName === 'a') {
                    $path = explode('/', $childNode->getAttribute('href'));
                    $normName = array_pop($path);
                    if (!isset($this->frequencies[$normName])) {
                        $this->frequencies[$normName] = $source;
                        $this->frequencies[$normName]['title_nl'] = trim($childNode->nodeValue);
                        $this->frequencies[$normName]['meta_description_nl'] = sprintf('Radio-interface %s voor %s (%s)', $normName, $this->frequencies[$normName]['title_nl'], $frequencyBandLabel);
                        $this->frequencies[$normName]['meta_description_fr'] = sprintf('Interface %s pour title (%s)' , $normName, $frequencyBandLabel);
                        $this->frequencies[$normName]['meta_description_en'] = sprintf('Interface %s for title (%s)' , $normName, $frequencyBandLabel);
                        $this->frequencies[$normName]['meta_description_de'] = sprintf('Funkschnittstelle %s (%s)' , $normName, $frequencyBandLabel);
                        $this->frequencies[$normName]['description_nl'] = sprintf('<p>%s</p>', $this->frequencies[$normName]['meta_description_nl']);
                        $this->frequencies[$normName]['description_fr'] = sprintf('<p>%s</p>', $this->frequencies[$normName]['meta_description_fr']);
                        $this->frequencies[$normName]['description_de'] = sprintf('<p>%s</p>', $this->frequencies[$normName]['meta_description_de']);
                        $this->frequencies[$normName]['description_en'] = sprintf('<p>%s</p>', $this->frequencies[$normName]['meta_description_en']);

                    }
                    else {
                        $this->frequencies[$normName]['frequency_band'] = array_merge($this->frequencies[$normName]['frequency_band'], $source['frequency_band']);
                        //$this->frequencies[$normName]['shared_status'] = array_values(array_unique(array_merge($this->frequencies[$normName]['shared_status'], $source['shared_status'])));
                        //$this->frequencies[$normName]['attributions'] = array_values(array_unique(array_merge($this->frequencies[$normName]['attributions'] ?? [], $source['attributions'])));
                    }
                    if (empty($this->frequencies[$normName]['attributions'])) {
                        unset($this->frequencies[$normName]['attributions']);
                    }

                }
            }
        }
    }

    private function loadExistingCategories(string $index)
    {
        $this->existingTaxonomies = [];
        $tempTaxo = $this->client->search([
            'index' => $index,
            'type' => 'taxonomy',
            'size' => 1000
        ]);
        foreach ($tempTaxo['hits']['hits'] as $item) {
            $this->existingTaxonomies[$item['_source']['title_fr']] = $item['_id'];
            $this->existingTaxonomies[$item['_source']['title_nl']] = $item['_id'];
            $this->existingTaxonomies[$item['_source']['title_de']] = $item['_id'];
            $this->existingTaxonomies[$item['_source']['title_en']] = $item['_id'];
        }
    }

    private function loadUrls(string $csvPath)
    {
        $this->urls =[];
        $handle = fopen($csvPath, "r");
        if (!$handle) {
            $this->output->writeln(sprintf('File %s not found', $csvPath));
            exit;
        }

        $progress = new ProgressBar($this->output, 9734);
        $progress->start();
        while (($line = fgets($handle)) !== false) {
            try {
                $path = trim($line);
                try {
                    $domDocument = $this->loadDom($path);
                    $this->extractMeta($domDocument, $path);
                }
                catch (NotFoundException $e) {
                }
                $progress->advance();
            } catch (\Exception $e) {
                $this->output->writeln(sprintf('Can\'t import %s', $line));
                dump($e);
            }
        }
        fclose($handle);
        $progress->finish();
    }

    private function convertLocale($label)
    {
        $locales = [
            'Deutsch' => 'de',
            'Nederlands' => 'nl',
            'Français' => 'fr',
            'English' => 'en',
            'DE' => 'de',
            'NL' => 'nl',
            'FR' => 'fr',
            'EN' => 'en',
        ];
        return $locales[trim($label)];
    }

    private function getGroup(\DOMXPath $xpath)
    {
        $links = $xpath->query('//ul[@id="top-section"]/li[@class="active"]');
        /** @var \DOMElement $link */
        foreach ($links as $link) {
            return in_array($link->nodeValue, File::CONSUMERS) ? 'consumers' : 'operators';
        }
    }

    private function getCurrentLocale(\DOMXPath $xpath)
    {
        $links = $xpath->query('//*[@id="top-languages"]/li[@class="active"]');
        /** @var \DOMElement $link */
        foreach ($links as $link) {
            return $this->convertLocale($link->nodeValue);
        }
    }

    private function getDetailMetas(\DOMXPath $DOMXPath)
    {
        $source = [];
        $metas = $DOMXPath->query('//*[@class="telephone-right-block document-detail"]/p');
        if ($metas->count() === 0) {
            $metas = $DOMXPath->query('//*[@class="telephone-right-block"]/p');
        }
        /** @var \DOMElement $meta */
        foreach ($metas as $meta) {
            $field = trim($meta->firstChild->nodeValue);
            $value = trim($meta->lastChild->nodeValue);
            switch ($field) {
                case 'Type':
                    if (isset(ImportCommand::PUBLICATION_TYPES[$value])) {
                        $source['type'] = ImportCommand::PUBLICATION_TYPES[$value];
                    }
                    else {
                        if (! isset($this->metaNotFound[$value])) {
                            $this->metaNotFound[$value] = 'Type';
                        }
                    }
                    break;
                case 'Documentnummer':
                    $source['version'] = $value;
                    break;
                case 'Topics':
                    $source['topics'] = $value;
                case 'Sleutelwoorden':
                    $source['keywods'] = $value;
                    break;
                case 'Publicatiedatum':
                    $source['publication_date'] = implode('/', array_reverse(explode('-', $value)));
                    break;
                case 'Sluitingsdatum':
                    $source['closing_date'] = implode('/', array_reverse(explode('-', $value)));
                    break;
                case 'Reageren tot':
                    $source['reaction_date'] = implode('/', array_reverse(explode('-', $value)));
                    break;
                case 'Datum':
                    $source['document_date'] = implode('/', array_reverse(explode('-', $value)));
                    break;
                default:
                    if (! isset($this->metaNotFound[$field])) {
                        $this->metaNotFound[$field] = 'Field';
                    }
            }
        }

        if (isset($source['publication_date'])) {
            $source['publication_datetime'] = $source['publication_date'].' 12:00:00';
            $source['document_date'] = $source['document_date'] ?? $source['publication_date'];
            $source['search_dates'] = $source['publication_date'];

        }
        $source['search_type'] = ($source['type']??'') === 'press_release'?'press-release':'publication';
        $source['publication_time']  = '12:00:00';

        return $source;
    }

    private function extractDataFromAsset(string $locale, $url) {
        $filename = $this->website.sha1($url);
        if(!file_exists($filename))  {
            $response = $this->httpClient->get($url);
            \file_put_contents($filename, $response->getBody()->getContents());
        }
        $slugs = explode('/', $url);
        $name = array_pop($slugs);
        $mimetype = mimetype_from_filename($name);
        $hash = sha1_file($filename);
        $size = filesize($filename);

        $folder = 'C:\\dev\\assets\\webibpt\\assets\\'. substr($hash, 0, 3);

        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        $copyTo = $folder . '\\' . $hash;
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        copy($filename, $copyTo);
        //$uploaded = $this->fileService->uploadFile($name, $mimetype, $filename, 'IBPT_importer');

        try{
            $data = $this->assetExtractorService->extractData($hash, $filename);
            return([('file_'.$locale)=>[
                '_author' => $data['Author']??null,
                '_content' => $data['content']??null,
                '_date' => $data['date']??null,
                '_language' => $locale,
                '_title' => $data['title']??null,
                'filename' => $name,
                'filesize' => $size,
                'mimetype' => $mimetype,
                'sha1' => $hash,
            ]]);
        }
        catch (\Exception $e) {
            $this->output->writeln(sprintf('Was not able to axtract data from %s', $name));
        }
        return[];

    }

    private function getTranslationLinks(\DOMXPath $DOMXPath, string $xpath, string $fieldPrefix, bool $extractData = false)
    {
        $source = [];
        $links = $DOMXPath->query($xpath);
        /** @var \DOMElement $link */
        foreach ($links as $link) {
            $locale = $this->convertLocale($link->nodeValue);
            $source[$fieldPrefix.$locale] =  $link->getAttribute('href');
            if ($extractData) {
                $source = array_merge($source, $this->extractDataFromAsset($locale, $source[$fieldPrefix.$locale]));
            }
        }
        return $source;
    }

    private function getValue(\DOMXPath $document, string $path, bool $asHtml=false)
    {
        $children = $document->query($path);
            /** @var \DOMElement $child */
        foreach ($children as $child) {
            if($asHtml) {
                $html = '';
                foreach ($child->childNodes as $grandChild) {
                    $html .= $grandChild->ownerDocument->saveHTML($grandChild);
                }
                return $html;
            }
            return $child->nodeValue;
        }
    }

    private function extractMeta(\DOMDocument $document, string $line)
    {
        $xpath= new \DOMXPath($document);
        $currentLocale = $this->getCurrentLocale($xpath);
        $source['migrated_url_'.$currentLocale] = $line;
        $files = $this->getTranslationLinks($xpath, '//*[@id="top-languages"]/li/a', 'migrated_url_');

        $source = array_merge($source, $files);
        ksort($source);

        $documentId = sha1(implode('|', $source));
        if (in_array($documentId, $this->treated)) {
            return;
        }
        $this->treated[] = $documentId;

        $source['target_group'] = [$this->getGroup($xpath)];
        $source['_contenttype'] = 'publication';
        $source['search_type'] = 'publication';
        $taxonomies = [];

        foreach (['nl', 'fr', 'de', 'en'] as $locale) {
            if (!isset($source['migrated_url_'.$locale])) {
                $source['show_'.$locale] = false;
                if ($locale === 'nl') {
                    $this->output->writeln(sprintf('No metas for url %s', $line));
                }
                continue;
            }

            try {
                if ($locale === $currentLocale) {
                    $domXpath = $xpath;
                }
                else {
                    $domXpath= new \DOMXPath($this->loadDom($source['migrated_url_'.$locale]));
                }
            }
            catch (NotFoundException $e) {
                $source['show_'.$locale] = false;
                continue;
            }

            $source['title_'.$locale] = trim($this->getValue($domXpath, '//*[@class="block-title document-title"]/h2'));
            if ($source['title_'.$locale] === '') {
                $source['title_'.$locale] = trim($this->getValue($domXpath, '//*[@class="block-title pressRelease-detail"]/h2'));
            }
            if($source['title_'.$locale] === '') {
                $source['show_'.$locale] = false;
                continue;
            }
            $source['show_'.$locale] = true;

            $input_lines = trim($this->getValue($domXpath, '//*/div[@class="inner_content_style"]/div', true));

            if ($input_lines === '') {
                $input_lines = trim($this->getValue($domXpath, '//*/div[@class="left-content"]', true));
            }
            $input_lines = preg_replace('/<img[^>]*>/s', '', $input_lines);
            $input_lines = preg_replace('/<p[^>]*><\/p>/s', '', $input_lines);
            $input_lines = preg_replace('/<div[^>]*>.*<\/div>/s', '', $input_lines);
            $source['description_'.$locale] = $input_lines;
            $source['slug_'.$locale] = $this->appExtension->toAscii($source['title_'.$locale]);
            $source['meta_description_'.$locale] = strip_tags($source['description_'.$locale]);


            $rawTaxonomies = $this->extractRawTaxonomies($source['migrated_url_'.$locale]);

            foreach($rawTaxonomies as $taxonomy)
            {
                if (isset($this->existingTaxonomies[$taxonomy])) {
                    $taxonomies[] = 'taxonomy:'.$this->existingTaxonomies[$taxonomy];
                }
                else {
                    if (! isset($this->metaNotFound[$taxonomy])) {
                        $this->metaNotFound[$taxonomy] = 'Taxonomy';
                    }
                }
            }

            if ($locale === 'nl') {
                $source = array_merge($source, $this->getDetailMetas($domXpath));
            }
        }


        $taxonomies = array_values(array_unique($taxonomies));

        if (!isset($source['type'])) {
            if (in_array('taxonomy:AW39KLSR-Sp0TD2f2ilj', $taxonomies)) {
                $source['type'] = 'press_release';
            }
            else {
                $this->output->writeln(sprintf('Type not found %', $documentId));
            }
        }


        //if interface
        if (($source['type'] ?? '') === 'radio_interface_specifications') {
            if (in_array($source['title_nl'], $this->treated)) {
                return;
            }
            $this->treated[] = $source['title_nl'];

            //if interface => remove radio
            if (($key = array_search('taxonomy:AW39KLRn-Sp0TD2f2ili', $taxonomies)) !== false) {
                unset($taxonomies[$key]);
            }

            $freqKey = str_replace('.', '-', $source['title_nl']);
            $freqKey = str_replace('_', '-', $freqKey);
            if (!isset($this->frequencies[$freqKey])) {
                $this->output->writeln('Freq. spec. not found '.$freqKey);
            }
            else {
                $source['version'] = $source['title_nl'];
                $source = array_merge($source, $this->frequencies[$freqKey]);
                $source['title_nl'] .= ' - '.$source['version'];
                $source['title_fr'] .= ' - '.$source['version'];
                $source['title_en'] .= ' - '.$source['version'];
            }
        }

        if (isset($source['publication_date'])) {
            $source['category'] = array_values(array_unique($taxonomies));
            $files = $this->getTranslationLinks($xpath, '//*[@class="document-languages"]/ul/li/a', 'migrated_doc_url_', true);
            $source = array_merge($source, $files);

            $fileHash = '';
            foreach (['fr', 'nl', 'de', 'en'] as $locale) {
                if (isset($source['file_'.$locale]['sha1'])) {
                    $fileHash .= $source['file_'.$locale]['sha1'];
                }
            }
            if ($fileHash === '' || in_array($fileHash, $this->treated)) {
                return;
            }
            $this->treated[] = $fileHash;

            $this->client->index([
                'index' => 'ibport_ibpt_mdk',
                'type' => 'doc',
                'id' => $documentId,
                'body' => $source,
            ]);
        }
        else {
            $this->notImported[] = $line;
        }


    }

    private function extractRawTaxonomies($url)
    {
        $url = $url = \preg_replace('/\?.*/', '', $url); // Drop query string
        $urlComponents = \explode('/', $url);
        \array_pop($urlComponents); // Remove page name
        \array_shift($urlComponents); // Remove empty string
        \array_shift($urlComponents); // Remove language
        \array_shift($urlComponents); // Remove target group

        foreach($urlComponents as $key => $component) // Remove one letter taxonomies
        {
            if(\strlen($component) < 2){
                unset($urlComponents[$key]);
            }
        }
        return $urlComponents;
    }

    private function getFilename(string $line): string
    {
        $filename = $this->website . sha1($line) . '.html';
        if (!file_exists($filename)) {
            if ($this->skipDownload) {
                throw new NotFoundException('Url not found '.$line);
            }
            $response = $this->httpClient->get($line);
            if ($response->getStatusCode() !== 200) {
                throw new NotFoundException('Url not found '.$line);
            }
            \file_put_contents($filename, $response->getBody()->getContents());
        }
        return $filename;
    }

    private function loadDom(string $line): \DOMDocument
    {
        $filename = $this->getFilename($line);
        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTMLFile($filename);
        return $document;
    }

    private function updateDocument(array &$value)
    {
        if (!isset($this->urls[$value['_id']])) {
            $this->output->writeln(sprintf('Url not found for hash %s', $value['_id']));
        }
    }
}