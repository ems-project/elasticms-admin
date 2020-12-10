<?php

namespace App\Command\Ibpt;

use EMS\CommonBundle\Command\CommandInterface;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DownloadCommand extends Command implements CommandInterface
{
    /** @var Client */
    private $client;

    /** @var SymfonyStyle */
    private $style;

    /** @var string */
    private $downloadFolder;

    protected static $defaultName = 'ems:job:ibpt:download';

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => 'https://www.ibpt.be', 'timeout' => 30, 'exceptions' => false]);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Download files')
            ->addArgument('csv', InputArgument::OPTIONAL, 'The CSV file with URLs to download', 'C:\dev\import\ibpt\pages.csv')
            ->addArgument('downloadFolder', InputArgument::OPTIONAL, 'The folder to put the files in', 'C:\dev\import\ibpt\website\\');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->style = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $csv = $input->getArgument('csv');

        if (!\file_exists($csv)) {
            throw new \Error('File ' . $csv . ' does not exist');
        }

        $this->downloadFolder = $input->getArgument('downloadFolder');

        if (!\file_exists($this->downloadFolder)) {
            throw new \Error('Folder ' . $this->downloadFolder . ' does not exist');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $style->title(\sprintf('Downloading files'));

        $csvFile = \file($input->getArgument('csv'));
        $data = [];
        foreach ($csvFile as $line) {
            $data[] = \str_getcsv($line)[0];
        }

        $pg = $this->style->createProgressBar(\count($data));
        $pg->start();

        foreach ($data as $pageUrl) {
            $fileNameOnDisk = \hash('sha1', \strtolower($pageUrl)) . '.html';
            $pg->advance();
            if (\file_exists($this->downloadFolder . $fileNameOnDisk)) {
                continue;
            }

            $response = $this->client->get($pageUrl);
            \file_put_contents($this->downloadFolder . $fileNameOnDisk, $response->getBody()->getContents());
        }
        $pg->finish();
    }
}
