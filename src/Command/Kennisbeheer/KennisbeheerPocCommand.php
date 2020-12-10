<?php

namespace App\Command\Kennisbeheer;

use Elasticsearch\Client;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Elasticsearch\Bulk\Bulker;
use GuzzleHttp\Client as HttpClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Proof of concept for importing kenninsbeheer data from hippo to elasticsearch
 */
class KennisbeheerPocCommand extends Command implements CommandInterface
{
    private $client;

    protected static $url = 'http://kennisbeheer.hippocmsacc.smals.be';
    protected static $defaultName = 'ems:job:kb-import-poc';

    public function __construct(Client $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('KB import command')
            ->addArgument('username', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('KB import');

        $bulker = new Bulker($this->client, new ConsoleLogger($output));
        $bulker->setSize(1000);

        $httpClient = new HttpClient([
            'base_uri' => self::$url,
            'timeout' => 30,
            'auth' => [$input->getArgument('username'), $input->getArgument('password')]
        ]);

        $xpath = "//*[@kennisbeheer:public_flag='true' and hippo:availability='live']";
        $publicPaths = $this->searchXpath($httpClient, $xpath, 10000);

        foreach ($publicPaths as $data) {
            $doc = $this->getDocument($httpClient, $data['jcr:path']);
            $locale = $doc['hippotranslation:locale'];

            if (isset($doc['kennisbeheer:content'])) {
                $doc['content'] = $this->getDocument($httpClient, $doc['kennisbeheer:content'])['hippostd:content'];
            }

            $bulker->index(['_index' => 'poc_kennisbeheer', '_type' => 'theme', '_id' => $doc['jcr:uuid']], [
                'title_'.$locale => $doc['kennisbeheer:title'],
                'summary_'.$locale => $doc['kennisbeheer:summary'],
                'content_'.$locale => $doc['content'],
                'expiration_date' => $doc['kennisbeheer:expirationDate'],
            ]);
        }

        $bulker->send(true);
    }

    private function getDocument(HttpClient $httpClient, $path)
    {
        $response = $httpClient->get('/cms/repository'.$path);

        $crawler = new Crawler();
        $crawler->addHtmlContent($response->getBody()->getContents());

        $list = $crawler->filterXPath('//html/body/ul');

        $document = [];

        $list->filterXPath('//li[@type="circle"]/a')->each(function (Crawler $circle) use (&$document, $path) {
            $text = trim(preg_replace('/\s+/', ' ', $circle->text()));

            $document[$text] = $path . substr($circle->attr('href'), 1);
        });

        $list->filterXPath('//li[@type="disc"]')->each(function (Crawler $disc) use (&$document) {
            $text = trim(preg_replace('/\s+/', ' ', $disc->text()));
            $regex = '/\[.*="(?<label>.*)"\] = (?<value>.*)/';
            preg_match($regex, $text, $matches);

            $label = $matches['label'];
            $value = $matches['value'];

            if (preg_match('/^\[(?<data>.*)]$/', $value, $valueMatch)) {
                $value = array_filter(explode(', ', $valueMatch['data']));
            }

            $document[$label] = $value;
        });

        return $document;
    }

    private function searchXpath(HttpClient $httpClient, $xpath, $limit = 1000)
    {
        $response = $httpClient->get('/cms/repository/?'.http_build_query(['xpath' => $xpath, 'limit' => $limit]));
        $contents = $response->getBody()->getContents();

        $re = '/Number of results found: (?<count>\d+)/m';
        preg_match($re, $contents, $matches, PREG_OFFSET_CAPTURE, 0);
        $total = (int) $matches['count'][0];

        if ($total > $limit) {
            throw new \RuntimeException(sprintf('Not everything is returned, limit: %d, total: %d', $limit, $total));
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
}