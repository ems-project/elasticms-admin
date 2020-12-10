<?php

namespace App\Command\Chamber;

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

    private const TYPES = ['actr', 'orgn'];

    protected static $defaultName = 'ems:job:chamber:download';

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => 'http://data.lachambre.be', 'timeout' => 30]);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Download files')
            ->addArgument('type', InputArgument::REQUIRED, 'actr, orgn')
            ->addArgument('path', InputArgument::REQUIRED, 'savePath')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        $path = $input->getArgument('path');

        $style = new SymfonyStyle($input, $output);
        $style->title(\sprintf('Downloading %s files', $type));

        if (!\in_array($type, self::TYPES)) {
            throw new \Exception('invalid type');
        }

        switch ($type) {
            case 'actr':
                $this->downloadACTR($style, $this->getSaveDir($path, $type));
                break;
            case 'orgn':
                $this->downloadORGN($style, $this->getSaveDir($path, $type));
                break;
        }
    }

    private function downloadACTR(SymfonyStyle $style, string $saveDir): void
    {
        foreach ($this->scroll($style, 'actr') as $item) {
            $filename = \sprintf('%s/%d.xml', $saveDir, $item['gaabId']);

            if (!\file_exists($filename)) {
                $this->download('actr/'.$item['gaabId'], $filename);
            }
        }
    }

    private function downloadORGN(SymfonyStyle $style, string $saveDir): void
    {
        foreach ($this->scroll($style, 'orgn') as $item) {
            $filename = \sprintf('%s/%d.xml', $saveDir, $item['id']);

            if (!\file_exists($filename)) {
                $this->download('orgn/'.$item['id'], $filename);
            }
        }
    }

    private function scroll(SymfonyStyle $style, string $endpoint): \Generator
    {
        $result = $this->getJson($endpoint, ['start' => 0]);
        $total = $result['total'];
        $start = 0;

        $pgBar = $style->createProgressBar($total);
        $pgBar->start();

        while ($start < $total) {
            $result = $this->getJson($endpoint, ['start' => $start]);
            $count = \count($result['items']);
            $start += $count;

            foreach ($result['items'] as $item) {
                yield $item;
            }

            $pgBar->advance($count);
        }

        $pgBar->finish();
        $style->writeln(2);
    }

    private function download($endpoint, $saveFilename): void
    {
        $response = $this->client->get(sprintf('/v0/%s', $endpoint));

        \file_put_contents($saveFilename, $response->getBody()->getContents());
    }

    private function getJson(string $endpoint, array $parms = []): array
    {
        $endpoint = \sprintf('/v0/%s?%s', $endpoint, \http_build_query($parms));
        $response = $this->client->get($endpoint, ['headers' => ['Accept' =>  'application/json']]);

        return \json_decode($response->getBody()->getContents(), true);
    }

    private function getSaveDir(string $path, string $type): string
    {
        $dir = $path.'/'.$type;

        if (!\is_dir($dir)) {
            \mkdir($dir);
        }

        return $dir;
    }
}