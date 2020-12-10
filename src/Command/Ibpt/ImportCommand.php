<?php

namespace App\Command\Ibpt;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Elasticsearch\Document;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Elasticsearch\Indexer;
use EMS\CommonBundle\Storage\StorageManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportCommand extends Command
{
    /** @var Bulker */
    private $bulker;
    /** @var Indexer */
    private $indexer;
    /** @var LoggerInterface */
    private $logger;
    /** @var StorageManager */
    private $storageManager;
    /** @var SymfonyStyle */
    private $style;
    /** @var Client */
    private $client;

    /** @var array */
    private $alreadyImported = [];
    /** @var array */
    private $serializedDocuments = [];
    /** @var array */
    private $urlVsHash = [];
    /** @var array */
    private $urlInError = [];
    /** @var string */
    private $website;
    /** @var string */
    private $attachments;
    /** @var string */
    private $csv;
    /** @var array */
    private $existingTaxonomies;

    const INDEX = 'webibpt_import';
    const SEARCH_INDEX = 'webibpt_v1_preview';
    const TYPE = 'import_publication';

    CONST PUBLICATION_TYPES = ['Consultations' => 'consultation',
        'Konsultation' => 'consultation',
        'Decisions' => 'decision',
        'Communication' => 'communication',
        'Other' => 'opinion',
        'Besluiten' => 'decision',
        'Beschlüsse' => 'decision',
        'Décisions' => 'decision',
        'Funk-Schnittstellenbeschreibung' => 'radio_interface_specifications',
        'radio-interface' => 'radio_interface_specifications',
        'specification' => 'radio_interface_specifications',
        'Raadpleging' => 'consultation',
        'Geschillen' => 'dispute',
        'Contentieux' => 'dispute',
        'radio' => 'radio_interface_specifications',
        'Specificatie van radio-interface' => 'radio_interface_specifications',
        'besluit' => 'decision',
        'Sonstiges' => 'other',
        'Mitteilung' => 'communication',
        'Mededeling' => 'communication',
        'Overige' => 'other',
        'Autre' => 'other',


        'decree' => 'national_framework_decree',
        'Nationaal kader – ministerieel besluit' => 'national_framework_decree',
        'Nationaal kader – koninklijk besluit' => 'national_framework_decree',
        'Nationaal kader – decreet' => 'national_framework_decree',
        'Voorafgaande raadpleging' => 'consultation',
        'consultation' => 'consultation',
        'Samenvatting van raadpleging' => 'consultation',
        'Erlass' => 'national_framework_decree',

        'Streitigkeiten' => 'dispute',

        'Avis' => 'opinion',

        'Gutachten' => 'opinion',
        'Annex' => 'other',

        'Formular' => 'form',
        'raadpleging' => 'consultation',

        'Opinion' => 'opinion',
        'Form' => 'form',
        'Advies' => 'opinion',
        'Liste' => 'other',
        'annuel' => 'annual_report',
        'directive' => 'european_framework',
        'Europees kader – beschikking' => 'european_framework',
        'Europees kader – andere akten' => 'european_framework',
        'Europees kader – richtlijn' => 'european_framework',
        'Europees kader – verordening' => 'european_framework',
        'loi' => 'national_framework_act',
        'Nationaal kader – wet' => 'national_framework_act',
        'procurement' => 'public_tender',

        'act' => 'national_framework_act',
        'order' => 'national_framework_ministerial_order',


        'Statistics' => 'statistics',

        'FAQ' => 'faq',
        'Formulier' => 'form',
        'Overheidsopdracht' => 'public_tender',
        'Enquête' => 'survey',
        'ministériel' => 'national_framework_ministerial_order',
        'Auftrag' => 'survey',

        'Formulaire' => 'form',
        'List' => 'other',


        'Bijlage' => 'other',
        'Jahresbericht' => 'annual_report',
        'Ministerialerlass' => 'national_framework_ministerial_order',
        'Internationaal kader – akkoord' => 'international_framework',

        'accord' => 'other',
        'akkoord' => 'other',
        'Annexe' => 'other',
        'Erläuterung' => 'other',
        'Statistiques' => 'statistics',
        'Toelichting' => 'other',
        'décision' => 'decision',
        'Anlage' => 'other',
        'Jaarverslag' => 'annual_report',
        'Verslag' => 'annual_report',
        'Rapport' => 'annual_report',
        'Verslag aan het Parlement' => 'annual_report',
        'Statistieken' => 'statistics',
        'Statistiken' => 'statistics',


        'release' => 'press_release',
        'Lijst' => 'other',
        'decision' => 'decision',
        'opérationnel' => 'operational_plan',
        'presse' => 'press_release',
        'Beheersplan' => 'press_release',
        'Pressemeldung' => 'press_release',


        'Beschlussentwurf' => 'consultation',
        'Ontwerpbesluit' => 'consultation',
        'Préconsultation' => 'consultation',
        'Survey' => 'survey',
        'Werkplan' => 'operational_plan',

        'Arbeitsplan' => 'operational_plan',
        'Strategisch plan' => 'strategic_plan',


        'Manual' => 'other',
        'Manuel' => 'other',
        'Handleiding' => 'other',

        'Persbericht' => 'press_release',

        'contract' => 'other',
        'Database' => 'database',
        'Beheerscontract' => 'management_contract',

    ];


    protected static $defaultName = 'ems:job:ibpt:import';

    public function __construct(Bulker $bulker, Indexer $indexer, StorageManager $storageManager, Client $client)
    {
        $this->bulker = $bulker;
        $this->indexer = $indexer;
        $this->storageManager = $storageManager;
        $this->client = $client;
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Import for IBPT')
            ->addArgument('website', InputArgument::OPTIONAL, 'A folder containing all the html files of the original website', 'C:\dev\import\ibpt\website')
            ->addArgument('attachments', InputArgument::OPTIONAL, 'A folder with attachments containing all PDF\'s, xlsx, ...', 'C:\dev\import\ibpt\attachments')
            ->addArgument('csv', InputArgument::OPTIONAL, 'A CSV file with the list of URL\'s', 'C:\dev\import\ibpt\pages.csv');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->style = new SymfonyStyle($input, $output);
        $this->logger = new ConsoleLogger($output);
        $this->bulker->setLogger($this->logger);
        $this->indexer->setLogger($this->logger);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $csv = $input->getArgument('csv');
        if (!\file_exists($csv)) {
            throw new \Error('File ' . $csv . ' does not exist');
        }

        $website = $input->getArgument('website');
        if (!\file_exists($website)) {
            throw new \Error('Folder ' . $website . ' does not exist');
        }

        $attachments = $input->getArgument('attachments');
        if (!\file_exists($attachments)) {
            throw new \Error('Folder ' . $attachments . ' does not exist');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->style->title('IBPT import');

        $this->website = $input->getArgument('website');
        $this->attachments = $input->getArgument('attachments');
        $this->csv = $input->getArgument('csv');

        $this->resetIndex();

        $this->importDocuments($output);

        $this->indexer->atomicSwitch('import', self::INDEX);

        $this->style->newLine(1);
        $output->writeln('Succesfully completed!');

        foreach ($this->urlInError as $url) {
            $output->writeln($url);
        }
    }

    private function getHmlFilePaths(): array
    {
        $filePaths = \scandir($this->website);
        return \array_diff($filePaths, array('.', '..'));
    }


    private function escapefileUrl($url){
        $parts = \parse_url($url);
        $path_parts = \array_map('rawurldecode', explode('/', $parts['path']));

        return
            $parts['scheme'] . '://' .
            $parts['host'] .
            implode('/', array_map('rawurlencode', $path_parts))
            ;
    }

    private function convertFileToDocument(OutputInterface $output, File $file): ?array
    {
        if (\in_array($file->getLocalPath(), $this->alreadyImported)) {
            return null;
        }

        if(!$this->existingTaxonomies) {
            $this->existingTaxonomies = [];
            $tempTaxo = $this->client->search([
                'index' => self::SEARCH_INDEX,
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

        $taxonomies = [];
        $rawTaxonomies = $this->extractRawTaxonomies($file->getOriginalUrl());

        foreach($rawTaxonomies as $taxonomy)
        {
            if (isset($this->existingTaxonomies[$taxonomy])) {
                $taxonomies[] = 'taxonomy:'.$this->existingTaxonomies[$taxonomy];
            }
            else {
                //$output->writeln(sprintf('Taxonomy not found: %s', $taxonomy));
            }
        }

        $lang = $file->getLanguage();
        $sha = \hash('sha1', $file->getOriginalUrl());

        // Build up the document array
        $document = [
            '_id' => $sha,
            '_type' => self::TYPE,
            '_score' => 1,
            '_source' => [
                'title_' . $lang => $file->getTitle(),
                'body_' . $lang => $file->getDescription(),
                'show_' . $lang => true,
                'target_group' => $file->getTargetGroup(),
                '_contenttype' => self::TYPE,
                'category' => $taxonomies,
                '_sha1' => $sha,
            ]
        ];

        if (isset( ImportCommand::PUBLICATION_TYPES[$file->getType()])) {
            $document['_source']['type'] = ImportCommand::PUBLICATION_TYPES[$file->getType()];
        }


        if ($file->getPublicationDate()) {
            $date = \date('Y/m/d', \strtotime($file->getPublicationDate()));
            $document['_source']['publication_date'] = $date;
        }
        if ($file->getDocumentDate()) {
            $document['_source']['document_date'] = \date('Y/m/d', \strtotime($file->getDocumentDate()));
        }
        if ($file->getReactionDate()) {
            $document['_source']['reaction_date'] = \date('Y/m/d', \strtotime($file->getReactionDate()));
        }
        if ($file->getClosingDate()) {
            $document['_source']['closing_date'] = \date('Y/m/d', \strtotime($file->getClosingDate()));
        }
        if ($file->getRelatedDossier()) {
            $document['_source']['reference'] = [$file->getRelatedDossier()];
        }
        if ($file->getLinkedContent()) {
            $document['_source']['linked_content'] = [$file->getLinkedContent()];
        }

        if (strpos($file->getOriginalUrl(), 'interface')) {
            $document['_source']['frequency'] = [];
            $params = [
                'index' => self::SEARCH_INDEX,
                'type' => 'frequency',
                'body' => [
                    'query' => [
                        'match_phrase' => [
                            "filename" => \basename($file->getOriginalUrl())
                        ]
                    ]
                ],
            ];

            $response = $this->client->search($params);
            foreach ($response['hits']['hits'] as $hit) {
                $document['_source']['frequency'][] = 'frequency:' . $hit['_id'];
            }
        }

        foreach ($file->getAttachments() as $attachment) {
            $path = $this->attachments . DIRECTORY_SEPARATOR . basename($attachment[1]);


            if (!\file_exists($path)) {
                try {
                    $downloadedFileContents = \file_get_contents($this->escapefileUrl($attachment[1]));
                    if($downloadedFileContents === false){
                        $this->logger->warning('Failed to download file at: ' . $attachment[1]);
                        continue;
                    }
                    //if (empty($content)) {
                    //    $this->logger->warning('empty file: ' . $attachment[1]);
                    //    continue;
                    //}

                    $save = file_put_contents($path, $downloadedFileContents);
                    if($save === false){
                        $this->logger->warning('Failed to save file to: ' . $path);
                        continue;
                    }

                    if (filesize($path) === 0) {
                        unlink($path);
                        $this->logger->warning('empty file: ' . $attachment[1]);
                        continue;
                    }

                }
                catch (\Exception $e) {
                    //dump($e);
                    $this->logger->warning('Failed to save file from: ' . $attachment[1]);
                    continue;
                }
            }

            if (\file_exists($path)) {
                $content = \file_get_contents($path);
                if (empty($content)) {
                    $this->logger->warning('empty file: ' . $path);
                    continue;
                }
                $mime = \mime_content_type($path);
                $filename = \basename($attachment[1]);
                $document['_source']['file_' . $attachment[0]] = [
                    '_author' => null,
                    '_content' => null,
                    '_date' => null,
                    '_language' => null,
                    '_title' => null,
                    'filename' => $filename,
                    'filesize' => \filesize($path),
                    'mimetype' => $mime,
                    'sha1' => $this->storageManager->saveContents($content, $mime, $filename)
                ];
            }
            else {
                $this->logger->warning('Failed to find file at: ' . $path);
                continue;
            }
        }

        foreach ($file->getOtherLanguages() as $lang) {
            $fileNameOnDisk = \hash('sha1', \strtolower($lang[1])) . '.html';
            $path = $this->website . DIRECTORY_SEPARATOR . $fileNameOnDisk;


            if (! \file_exists($path)) {
                try {
                    $downloadedFileContents = \file_get_contents('https://www.ibpt.be' . $lang[1]);
                    if($downloadedFileContents === false){
                        $this->logger->warning('Failed to download file at: ' . 'https://www.ibpt.be' . $lang[1]);
                        continue;
                    }
                    if (empty($downloadedFileContents)) {
                        $this->urlInError[] = $file->getOriginalUrl();
                        return null;
                        //$this->logger->warning('empty file: ' . $lang[1]);
                        //dump($file->getOriginalUrl());
                        //continue;
                    }

                    $save = file_put_contents($path, $downloadedFileContents);
                    if($save === false){
                        $this->logger->warning('Failed to save file to: ' . $path);
                        continue;
                    }
                }
                catch (\Exception $e) {
                    $this->logger->warning('Failed to save file from: ' . 'https://www.ibpt.be'.$lang[1]);
                    continue;
                }
            }



            if (\file_exists($path)) {
                try {
                    $relatedFile = new File($this->website, $fileNameOnDisk);
                    if (!\in_array($path, $this->alreadyImported)) {
                        $this->alreadyImported[] = $path;
                    }
                    $document['_source']['title_' . $lang[0]] = $relatedFile->getTitle();
                    $document['_source']['body_' . $lang[0]] = $relatedFile->getDescription();
                    $document['_source']['show_' . $lang[0]] = true;
                }
                catch (\Exception $e) {
                    $this->style->warning('Not able to parse ' . $path);
                }
            } else {
                $this->logger->warning('File not found ' . $fileNameOnDisk . ' for url ' . $lang[1]);
            }
        }

        return $document;
    }

    private function resetIndex(): void
    {
        if ($this->indexer->exists(self::INDEX)) {
            $this->indexer->delete(self::INDEX);
        }
        $this->indexer->create(self::INDEX, [], \json_decode(\file_get_contents(__DIR__ . '/index_mapping.json'), true));
    }

    private function crawlFiles(array $filesNames): array
    {
        $this->style->title('Starting crawling of physical HTML files');
        $pg = $this->style->createProgressBar(\count($filesNames));
        $pg->start();
        $files = [];
        foreach ($filesNames as $fileName) {
            if (isset($this->getUrlVsHash()[\substr_replace(\basename($fileName), '', -5)])) {
                $file = new File($this->website, $fileName);
                $file->setOriginalUrl($this->getUrlVsHash()[\substr_replace(\basename($fileName), '', -5)][0]);
                $files[] = $file;
            }
            $pg->advance();
        }
        $pg->finish();

        return $files;
    }

    private function convertCrawledFilesToDocuments(OutputInterface $output): void
    {
        $crawledFiles = $this->crawlFiles($this->getHmlFilePaths());

        $this->style->newLine(1);
        $this->style->title('Starting conversion of PHP array to Elastic Documents');
        $pg = $this->style->createProgressBar(\count($crawledFiles));
        $pg->start();
        foreach ($crawledFiles as $file) {
            if ($elasticDocument = $this->convertFileToDocument($output, $file)) {
                $this->serializedDocuments[] = $elasticDocument;
                $pg->advance();
            }
        }
        $pg->finish();
    }

    private function importDocuments(OutputInterface $output): void
    {
        $this->convertCrawledFilesToDocuments($output);

        $this->style->newLine(1);
        $this->style->title('Starting the import of the documents');
        $pg = $this->style->createProgressBar(\count($this->serializedDocuments));
        $pg->start();
        foreach ($this->serializedDocuments as $document) {
            $this->bulker->indexDocument(new Document($document), self::INDEX);
            $pg->advance();
        }
        $pg->finish();

        try {
            $this->bulker->send(true);
        } catch (NoNodesAvailableException $e) {
            $this->logger->error('Bulker send failed.');
        }
    }

    private function getUrlVsHash(): array
    {
        if ($this->urlVsHash !== []) {
            return $this->urlVsHash;
        }

        $csvFile = \file($this->csv);
        $hashesVsUrls = [];
        foreach ($csvFile as $line) {
            $hashesVsUrls[\hash('sha1', \strtolower(\str_getcsv($line)[0]))] = [\strtolower(\str_getcsv($line)[0])];
        }
        $this->urlVsHash = $hashesVsUrls;
        return $hashesVsUrls;
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


}
