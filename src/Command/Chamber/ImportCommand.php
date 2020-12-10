<?php

namespace App\Command\Chamber;

use App\Import\Chamber\Import;
use App\Import\Chamber\IndexHelper;
use App\Import\Chamber\Model;
use App\Import\Chamber\ModelFactory;
use App\Import\Chamber\XML\MTNG;
use Elasticsearch\Client;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Elasticsearch\Indexer;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ImportCommand extends Command implements CommandInterface
{
    /** @var Bulker */
    private $bulker;
    /** @var Indexer */
    private $indexer;
    /** @var Client */
    private $client;

    protected static $defaultName = 'ems:job:chamber';
    private const REGEX = [
        Model::TYPE_QRVA => '/^(?!52-B079-501-0484-2008200909734)(\d{2}-B.*).xml$/',
        Model::TYPE_INQO => '/^(?:I)|(?:(?:Q)(\d{2})(\d{1}|\w{1}).*).xml$/',
        Model::TYPE_CCRI => '/^CI_.*.xml$/',
        Model::TYPE_PCRI => '/^PI_.*.xml$/',
    ];
    /**  @var AssetExtractorService */
    private $extractorService;
    /**  @var StorageManager */
    private $storageManager;
    /** @var FileService */
    private $fileService;

    public function __construct(Bulker $bulker, Indexer $indexer, Client $client, StorageManager $storageManager, AssetExtractorService $extractorService, FileService $fileService)
    {
        parent::__construct();
        $this->bulker = $bulker;
        $this->indexer = $indexer;
        $this->client = $client;
        $this->extractorService = $extractorService;
        $this->storageManager = $storageManager;
        $this->fileService = $fileService;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Import Chamber')
            ->addArgument('dir', InputArgument::REQUIRED, 'directory')
            ->addArgument('type', InputArgument::REQUIRED, sprintf('type [%s]', implode(', ', array_keys(Model::TYPES))))
            ->addArgument('importId', InputArgument::REQUIRED, 'importId')
            ->addOption('environment', null, InputOption::VALUE_REQUIRED, 'ems env default template')
            ->addOption('pattern', null, InputOption::VALUE_REQUIRED, 'regex of file to import', '.*')
            ->addOption('dry', null, InputOption::VALUE_NONE, 'skip indexing (also activates dryPdf)')
            ->addOption('clean', null, InputOption::VALUE_NONE, 'remove old indexes')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'limit')
            ->addOption('bulkSize', null, InputOption::VALUE_REQUIRED, 'bulk size default (500)')
            ->addOption('dryPdf', null, InputOption::VALUE_NONE, 'skip extraction of PDF content')
            ->addOption('append', null, InputOption::VALUE_REQUIRED, 'import into the latest index, optionally provide the environment')
            ->addOption('keepCV', null, InputOption::VALUE_NONE, 'keep existing Member (CV) data when reimporting ACTR files')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('dry')) {
            $input->setOption('dryPdf', true);
        }

        $type = $input->getArgument('type');
        if ($input->getOption('keepCV') && $type !== Model::TYPE_ACTR) {
            throw new \RuntimeException(sprintf('The option "--keepCV" can only be used for the type "%s"', Model::TYPE_ACTR));
        }
        if ($input->getOption('append') && \in_array($type, IndexHelper::INDEX_BREAKS_WITH_APPEND)) {
            throw new \RuntimeException(sprintf('The option "--append" is not allowed for the type "%s"', $type));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        list($dir, $type, $importId, $environment, $dry, $clean, $limit, $bulkSize, $dryPdf, $append, $keepCv, $pattern) = $this->getOptions($input);

        $style = new SymfonyStyle($input, $output);
        $style->title(sprintf('Chamber import %s from %s', $type, $dir));

        $logger = new ConsoleLogger($output);
        $this->indexer->setLogger($logger);
        $this->bulker->setLogger($logger)->setSingleIndex(true)->setSize($bulkSize);

        $indexHelper = new IndexHelper($this->indexer, $environment, $importId);
        $import = new Import($this->client, $logger, $dir, $type, $environment, $dryPdf, $keepCv);
        $factory = new ModelFactory($indexHelper, $import, $this->storageManager, $this->extractorService, $this->fileService);

        foreach ($this->findFiles($style, $dir, $type, $pattern) as $file) {
            if (null !== $limit && 0 === $limit--) {
                break;
            }

            $model = $factory->create($file, $type);

            if ($model && !$dry) {
                $this->bulker->indexDocument($model, $indexHelper->getIndex($model, $append));
            }
        }
        $this->bulker->send(true);

        if ($this->bulker->hasErrors()) {
            $style->warning('Rolling back indexes! Processing of Children cancelled');
            $indexHelper->rollbackIndexes();
            return;
        }

        foreach ($factory->getChildren() as list($childType, $children)) {
            $style->section(sprintf('Collected %d %s', \count($children), $childType));

            if (!$dry) {
                $childProgress = $style->createProgressBar(\count($children));
                $childProgress->start();

                foreach ($children as $child) {
                    $this->bulker->indexDocument($child, $indexHelper->getIndex($child, $append), true);
                    $childProgress->advance();
                }
                $this->bulker->send(true);
                $childProgress->finish();
            }
        }

        if ($append) {
            return;
        }
        if (!$this->bulker->hasErrors()) {
            $indexHelper->switchIndexes($clean);
        } else {
            $style->warning('Rolling back indexes!');
            $indexHelper->rollbackIndexes();
        }
    }

    private function getOptions(InputInterface $input): array
    {
        return [
            $input->getArgument('dir'),
            strtolower($input->getArgument('type')),
            strtolower($input->getArgument('importId')),
            $input->getOption('environment') ?? 'template',
            (bool)$input->getOption('dry'),
            (bool)$input->getOption('clean'),
            (int)$input->getOption('limit') > 0 ? (int)$input->getOption('limit') : null,
            (int)$input->getOption('bulkSize') > 0 ? (int)$input->getOption('bulkSize') : 500,
            (bool)$input->getOption('dryPdf'),
            (string)$input->getOption('append'),
            (bool)$input->getOption('keepCV'),
            (string)$input->getOption('pattern'),
        ];
    }

    private function findFiles(SymfonyStyle $style, string $dir, string $type, string $pattern = '.*'): \Generator
    {
        if ($type === Model::TYPE_MTNG) {
            yield from MTNG::findFiles($style, $dir);
        } else {
            $regex = self::REGEX[$type] ?? '/^.*.xml$/';
            $regex = str_replace('.*', $pattern, $regex);
            $files = Finder::create()->in($dir)->files()->name($regex);

            $progress = $style->createProgressBar($files->count());
            $progress->start();

            foreach ($files as $file) {
                /** @var $file SplFileInfo */
                $style->write('   ' . $file->getFilename());
                yield $file;
                $progress->advance();
            }

            $progress->finish();
            $style->newLine(1);
        }
    }
}
