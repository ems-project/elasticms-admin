<?php

namespace App\Command\Slm;

use App\Import\SLM\CSVImporter;
use App\Import\SLM\Document\Child;
use App\Import\SLM\ImportDocument;
use App\Import\SLM\XMLCollector;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Elasticsearch\Indexer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\User;

class ImportCommand extends Command implements CommandInterface
{
    /** @var SymfonyStyle */
    private $style;
    /** @var LoggerInterface */
    private $logger;

    /** @var Client */
    private $client;
    /** @var Indexer */
    private $indexer;
    /** @var Bulker */
    private $bulker;
    /** @var FileService */
    private $fileService;

    /** @var DataService */
    private $dataService;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var TokenStorageInterface */
    private $tokenStorage;

    const USERNAME = 'import'; //this user needs to have an account in the backend!

    protected static $defaultName = 'ems:job:slm-import';

    public function __construct(Bulker $bulker, Client $client, FileService $fileService, Indexer $indexer, DataService $dataService, ContentTypeService $contentTypeService, TokenStorageInterface $tokenStorage)
    {
        $this->client = $client;
        $this->indexer = $indexer;
        $this->bulker = $bulker;
        $this->fileService = $fileService;

        $this->contentTypeService = $contentTypeService;
        $this->dataService = $dataService;
        $this->tokenStorage = $tokenStorage;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('SLM import command')
            ->addArgument('emsLink', InputArgument::REQUIRED, 'ems://object:import:ouuid')
            ->addOption('bulkSize', null, InputOption::VALUE_REQUIRED, 'bulk size', 500)
            ->addOption('index', null, InputOption::VALUE_REQUIRED, 'index name', 'import_slm')
        ;
        parent::configure();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->style = new SymfonyStyle($input, $output);
        $this->logger = new ConsoleLogger($output);

        $this->bulker->setLogger($this->logger)->setSize($input->getOption('bulkSize'));
        $this->indexer->setLogger($this->logger);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->style->title('SLM Import');
        $importDocument = $this->getImportDocument(EMSLink::fromText($input->getArgument('emsLink')));

        $index = $input->getOption('index');

        if (!$this->indexer->exists($index)) {
            $mapping = \json_decode(\file_get_contents(__DIR__ . '/../../Import/SLM/index_mapping.json'), true);
            $this->indexer->create($index, [], $mapping);
        }

        $this->importXMLFiles($importDocument, $index);
        $csvImporter = $this->importCSVFile($importDocument, $index);

        $this->importChildren($csvImporter);

        $this->style->section('Missing sla ids');
        $this->style->table(['id', 'service', 'customer'], $csvImporter->getMissingSLAs());

        $this->indexer->atomicSwitch('import_cms', $index);
    }

    /**
     * Step 1) Get the import document from EMS
     */
    private function getImportDocument(EMSLink $emsLink): ImportDocument
    {
        try {
            $document = $this->client->get(['index' => 'slm_preview', 'type' => $emsLink->getContentType(), 'id' => $emsLink->getOuuid()]);

            return new ImportDocument($document, $this->fileService);
        } catch (Missing404Exception $e) {
            throw new \Exception('Document not found!');
        }
    }

    /**
     * Step 2) Import the xml files and create SLA's, Clients, CMS's documents
     */
    private function importXMLFiles(ImportDocument $importDocument, string $index): void
    {
        $this->style->section('Import xml files');

        $collector = new XMLCollector();
        foreach ($importDocument->getFiles('xml') as $xmlFile) {
            $this->logger->info($xmlFile['name']);
            $collector->collect($xmlFile);
        }

        $this->bulkCollection('Clients', $index, $collector->getClients());
        $this->bulkCollection('CSM', $index, $collector->getCSMs());
        $this->bulkCollection('SLA', $index, $collector->getSLAs());
    }

    /**
     * Step 3) Import the csv files for KPI's and there data
     */
    private function importCSVFile(ImportDocument $importDocument, string $index): CSVImporter
    {
        $this->style->section('Import csv file');

        $csvImporter = new CSVImporter($this->client, $importDocument, $index);
        $progressBar = $this->style->createProgressBar();

        foreach ($csvImporter->getKPIs() as $kpi) {
            $this->bulker->indexDocument($kpi, $index);

            foreach ($kpi->getData() as $data) {
                $this->bulker->indexDocument($data, $index);
            }

            $this->bulker->index(
                ['_index' => $index, '_type' => 'sla', '_id' => $kpi->getSLAId()],
                ['has_kpi' => true],
                true
            );

            $progressBar->advance();
        }
        $progressBar->finish();
        $this->style->newLine(2);

        return $csvImporter;
    }

    /**
     * Step 4) Ask the csv importer for managed children, if they don't exists we create them.
     * This way we don't need to migrate after importing, because the client can trigger imports
     */
    private function importChildren(CSVImporter $CSVImporter): void
    {
        $this->style->section('Import managed children (Types, Service windows, Conditions)');

        foreach ($CSVImporter->getChildren() as $type => $children) {
            $contentType = $this->contentTypeService->getByName($type);

            foreach ($children as $child) {
                /** @var $child Child */
                try {
                    $this->dataService->getNewestRevision($type, $child->getId());
                } catch (NotFoundHttpException $e) {
                    $this->createNewChild($contentType, $child);
                }
            }
        }
    }

    private function createNewChild(ContentType $contentType, Child $child): void
    {
        try {
            $token = new AnonymousToken('import', new User(self::USERNAME, 'import'));
            $this->tokenStorage->setToken($token);

            $revision = $this->dataService->newDocument($contentType, $child->getId(), [
                'label_nl' => $child->getLabel(),
                'label_fr' => $child->getLabel(),
            ]);
            $this->dataService->finalizeDraft($revision);
        } catch (DuplicateOuuidException $e) {
            $this->logger->error(sprintf('Error creating revision for ouuid %s:%s', $contentType->getName(), $child->getId()));
            return; //revision deleted but not cleaned?
        }
    }

    private function bulkCollection(string $type, string $index, array $collection): void
    {
        $this->style->writeln(sprintf('Bulking %d %s documents', \count($collection), $type));
        $progressBar = $this->style->createProgressBar(\count($collection));

        foreach ($collection as $document) {
            $this->bulker->indexDocument($document, $index);
            $progressBar->advance();
        }

        $this->bulker->send(true);
        $progressBar->finish();
        $this->style->newLine(2);
    }
}