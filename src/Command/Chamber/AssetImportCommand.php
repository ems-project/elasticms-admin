<?php

namespace App\Command\Chamber;

use App\Command\LaChambre\Asset\Asset;
use Doctrine\DBAL\Connection;
use EMS\CommonBundle\Command\CommandInterface;
use Elasticsearch\Client;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Elasticsearch\Bulker;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AssetImportCommand extends Command implements CommandInterface
{
    /**@var Client */
    private $client;
    /** @var Bulker */
    private $bulker;
    /** @var Connection */
    private $conn;
    /** @var AssetExtractorService  */
    private $extractorService;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    private $index = 'webchamber_import';

    protected static $defaultName = 'ems:job:chamber:import:files';


    public function __construct(Client $client, AssetExtractorService $extractorService, RegistryInterface $doctrine)
    {
        parent::__construct();
        $this->client = $client;
        $this->conn = $doctrine->getConnection();
        $this->extractorService = $extractorService;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger = new ConsoleLogger($output);

        $sfStyle = new SymfonyStyle($input, $output);
        $sfStyle->title('Import files as assets');

        $this->conn->getConfiguration()->setSQLLogger(null);


        $this->bulker = new Bulker($this->client, $this->logger);
        $this->bulker->setLogger($this->logger)->setSingleIndex(true)->setSize(5);

        $this->buildAssets($sfStyle);

        $this->bulker->send(true);

        $sfStyle->writeln('');
        $sfStyle->success('done');
    }



    private function buildAssets(SymfonyStyle $sfStyle)
    {
        $qryAssets = <<<QUERY
            SELECT substring(name, '^(\d+)')::int as id,
              a.name as filename, a.sha1, a.size as filesize, a.type as mimetype, c.data as extract_data
            FROM uploaded_asset a
            JOIN (SELECT sha1, max(created) as created FROM uploaded_asset group by sha1) m
              ON a.sha1 = m.sha1 AND a.created = m.created
            LEFT JOIN cache_asset_extractor c ON c.hash = a.sha1
            WHERE a.id is not null
QUERY;

        $stmt = $this->conn->query($qryAssets);
        $results = $stmt->fetchAll(\PDO::FETCH_GROUP); //group by id

        foreach ($results as $id => $files) {
            if ('' === $id) {
                continue;
            }

            $sfStyle->section(sprintf('Processing files for id %s', $id));
            $processBar = $sfStyle->createProgressBar();
            $processBar->start();

            foreach ($files as $file) {
                $processBar->advance();
                $extract = json_decode($file['extract_data'], true);

                if (null === $extract) {
                    continue;
                }

                $asset = array_filter([
                    'filename' => $file['filename'],
                    'filesize' => $file['filesize'],
                    'mimetype' => $file['mimetype'],
                    'sha1'     => $file['sha1'],
                    'legislature' => $id,
                ]);


                if (isset($extract['date']) && $extract['date']) {
                    $asset['_date'] = $extract['date'];
                }
                if (isset($extract['content']) && $extract['content']) {
                    $asset['_content'] = $extract['content'];
                }
                if (isset($extract['Author']) && $extract['Author']) {
                    $asset['_author'] = $extract['Author'];
                }
                if (isset($extract['author']) && $extract['author']) {
                    $asset['_author'] = $extract['author'];
                }
                if (isset($extract['language']) && $extract['language']) {
                    $asset['_language'] = $extract['language'];
                }
                if (isset($extract['title']) && $extract['title']) {
                    $asset['_title'] = $extract['title'];
                }

                $asset = new Asset($asset);
                $this->bulker->indexDocument($asset, $this->index);
            }
            $processBar->finish();
        }

        return $stmt->rowCount();
    }

}