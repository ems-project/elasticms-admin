<?php

namespace App\Command\Kennisbeheer;

use EMS\CommonBundle\Elasticsearch\Document;
use EMS\CoreBundle\Elasticsearch\Bulker;
use GuzzleHttp\Client as HttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Example
 *
 * php bin/console ems:job:kb-import mdk chef http://kennisbeheer.hippocmsacc.smals.be /cms/repository "sql=SELECT+*+FROM+kennisbeheer%3AThemeDocument+WHERE+jcr%3Apath+LIKE+%27%2Fcontent%2Fdocuments%2F%25%27+and+hippo%3Aavailability+%3D+%27live%27&limit=20"
 */
class ImportCommand extends Command
{
    /** @var Bulker */
    private $bulker;
    /** @var SymfonyStyle */
    private $style;
    /** @var HttpClient */
    private $client;

    protected static $defaultName = 'ems:job:kb-import';

    public function __construct(Bulker $bulker)
    {
        parent::__construct();
        $this->bulker = $bulker;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('KB import command')
            ->addArgument('username', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('url', InputArgument::REQUIRED)
            ->addArgument('path', InputArgument::REQUIRED)
            ->addArgument('query', InputArgument::REQUIRED)
            ->addOption('bulkSize', null, InputOption::VALUE_REQUIRED, 'bulk size default (500)', 500)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->style = new SymfonyStyle($input, $output);
        $this->bulker->setLogger(new ConsoleLogger($output))->setSize($input->getOption('bulkSize'));

        $this->client = new HttpClient([
            'base_uri' => $input->getArgument('url'),
            'timeout' => 30,
            'auth' => [$input->getArgument('username'), $input->getArgument('password')]
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->style->title('KB import');

        $index = 'import_kennisbeheer';
        $path = $input->getArgument('path');
        $query = $input->getArgument('query');

        $search = $this->getSearch($path.'/?'.$query);

        $progressBar = new ProgressBar($output, \count($search));
        $progressBar->start();

        foreach ($search as $result) {
            $item = $this->getItem($path . $result['jcr:path']);

            if (isset($item['links']['kennisbeheer:content'])) {
               // $content = $this->getItem($this->client->get($path . $result['jcr:path'].$item['links']['kennisbeheer:content']));
               // $item['hippostd:content'] = $content['hippostd:content'];
            }

            if (isset($item['kennisbeheer:documents']) && \count(array_keys($item['kennisbeheer:documents'])) > 2) {
                $documents = $this->getItem($this->client->get($path . $result['jcr:path'].$item['links']['kennisbeheer:documents']));

//                if (\count($documents['links']) > 0) {
//                    dump($item, $result, $documents); die;
//                }
            }

            $this->bulker->indexDocument(new Document([
                '_id' => $item['jcr:uuid'],
                '_type' => $item['jcr:primaryType'],
                '_source' => $item
            ]), $index);

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->bulker->send(true);
    }

    private function getSearch(string $uri): array
    {
        $contents = $this->client->get($uri)->getBody()->getContents();

        preg_match('/Number of results found: .*/m', $contents, $matches);
        if ($matches) {
            $this->style->comment($matches[0]);
        }

        $crawler = new Crawler();
        $crawler->addHtmlContent($contents);
        $table = $crawler->filterXPath('//html/body/table[@summary="searchresult"]');

        $headers = $table->filterXPath('//th')->each(function (Crawler $th){
            return $th->html();
        });

        $data = $table->filterXPath('//tr')->each(function (Crawler $tr) use ($headers) {
            $values = $tr->filterXPath('//td')->each(function (Crawler $td) {
                return $td->html();
            });

            return ($values ? array_combine($headers, $values) : null);
        });

        return array_filter($data);
    }

    private function getItem(string $path): array
    {
        $response = $this->client->get($path);
        $content = $response->getBody()->getContents();

        $crawler = new Crawler();
        $crawler->addHtmlContent($content);

        $item = [];
        $list = $crawler->filterXPath('//html/body/ul');

        $list->filterXPath('//li[@type="circle"]/a')->each(function (Crawler $circle) use (&$item, $path) {
            $text = trim(preg_replace('/\s+/', ' ', $circle->text()));

            $item['links'][$text] = $this->getItem(str_replace('//', '/', $path . substr($circle->attr('href'), 1)));
        });

        $list->filterXPath('//li[@type="disc"]')->each(function (Crawler $disc) use (&$item) {
            $text = trim(preg_replace('/\s+/', ' ', $disc->text()));

            $regex = '/^\[name="(?<label>.*)"] =(?<value>.*)/i';
            preg_match($regex, $text, $matches);

            $label = $matches['label'];
            $value = trim($matches['value']) ?? null;

            if (preg_match('/^\[(?<data>.*)]$/', $value, $valueMatch)) {
                $value = array_filter(array_map('trim', explode(', ', $valueMatch['data'])));
            }

            $item[$label] = $value;
        });

        return $item;
    }
}