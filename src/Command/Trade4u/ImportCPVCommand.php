<?php

namespace App\Command\Trade4u;

use App\Import\Trade4u\CPV;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Elasticsearch\Bulker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Download cpv xls from https://simap.ted.europa.eu/fr/web/simap/cpv and convert to csv
 */
class ImportCPVCommand extends Command implements CommandInterface
{
    /** @var Bulker */
    private $bulker;

    protected static $defaultName = 'trade4u:import:cpv';

    public function __construct(Bulker $bulker)
    {
        parent::__construct();
        $this->bulker = $bulker;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Import cpv from csv')
            ->addArgument('csv', InputArgument::REQUIRED, 'path to csv')
            ->addArgument('index', InputArgument::OPTIONAL, '', 'trade4u_import_cpv_'.time())
            ->addOption('bulkSize', null, InputOption::VALUE_REQUIRED, 'bulk size', 500)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('Trade4u import CPV');

        $this->bulker->setLogger(new ConsoleLogger($output));
        $this->bulker->setSize($input->getOption('bulkSize'));

        $items = [];
        foreach ($this->readCSV($input->getArgument('csv')) as $body) {
            $items[] = new CPV($body);
        }

        $index = $input->getArgument('index');
        $config = ['_index' => $index, '_type' => 'cpv'];
        $style->section(sprintf('start importing on index "%s"', $index));

        foreach ($items as $cpv) {
            /** @var $cpv CPV */
            $cpv->setChildren($items);
            $config['_id'] = $cpv->getId();
            $this->bulker->index($config, $cpv->getBody());
        }

        $this->bulker->send(true);
    }

    private function readCSV(string $filename)
    {
        if(!($handle = fopen($filename, 'r'))){
            return;
        }

        $keys = [];
        $row = 0;
        while(($rawData = fgetcsv($handle, 100000, ';')) !== false) {
            $row ++;
            if ($row === 1) {
                $keys = array_map(function ($key) {
                    if ($key !== 'CODE') {
                        $key = 'title_'.$key;
                    }

                    return \strtolower(trim($key));
                }, $rawData);
                continue;
            }

            yield array_combine($keys, array_map("utf8_encode", $rawData));
        }
    }
}