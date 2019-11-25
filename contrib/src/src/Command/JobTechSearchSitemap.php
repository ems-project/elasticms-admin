<?php

namespace App\Command;

use EMS\CommonBundle\Command\CommandInterface;
use Elasticsearch\Client;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Service\EnvironmentService;
use GuzzleHttp\Client as HttpClient;
use EMS\CommonBundle\Common\EMSLink;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class JobTechSearchSitemap extends Command implements CommandInterface
{
    /**@var Client */
    private $client;
    /** @var string */
    private $tikaServer;
    /** @var Bulker */
    private $bulker;
    /** @var EnvironmentService */
    private $environmentService;

    static $defaultName = 'ems:job:tech-search:sitemap';

    const INDEX         = 'job_tech_search_sitemap';

    public function __construct(Client $client, $tikaServer, Bulker $bulker, EnvironmentService $environmentService)
    {
        parent::__construct();
        $this->client = $client;
        $this->tikaServer = $tikaServer;
        $this->bulker = $bulker;
        $this->environmentService = $environmentService;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('TechSearch import site map command')
            ->addArgument('emsLink', InputArgument::REQUIRED, 'ems://object:import:ouuid')
            ->addOption('reset', null, InputOption::VALUE_NONE, sprintf('start fresh will drop index : %s', self::INDEX))
            ->addOption('dontVerifySsl', null, InputOption::VALUE_NONE, 'Do not verify SSL certificate')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('Tech Search import');

        $client = new HttpClient(['verify' => (!$input->getOptions('dontVerifySsl')) ]);

        $tika = $this->createTika();

        $environment = $this->environmentService->getByName('preview');

        $emsLink = EMSLink::fromText($input->getArgument('emsLink'));
        $document = $this->client->get(['index' => $environment->getAlias(), 'type' => $emsLink->getContentType(), 'id' => $emsLink->getOuuid()]);
        $url = $document['_source']['url'];

        $style->section(sprintf('Getting sitemap from %s', $url));
        $siteMapResponse = $this->download($client, $url);

        $crawler = new Crawler();
        $crawler->addXmlContent($siteMapResponse->getBody()->getContents());
        $crawler->registerNamespace('g', 'https://www.ehealth.fgov.be/en/api/ap12.3_v0.2');

        try {
            $urls = $crawler->filterXPath('//g:url')->each(\Closure::fromCallable([$this->getUrlCrawler($emsLink), 'each']));
        } catch (\InvalidArgumentException $ex) {
            $style->error(sprintf('The sitemap %s is not valid!', $url));
            throw $ex;
        }

        $this->reset($input);

        $this->bulker->setLogger(new ConsoleLogger($output));
        $this->bulker->setSize(1);

        $linkManager = $this->getLinkManager($this->client, $environment->getAlias());

        $progress = $style->createProgressBar(count($urls));
        $progress->start();

        foreach ($urls as $data) {
            try {
                $url = $data['url'];

                $download = $this->download($client, $url);
                $content = $this->extractContent($tika, $download);
                $data['file'] = $this->extractFileInfo($tika, $download);

                foreach ($data['languages'] as $lang) {
                    $data['content_'.$lang] = $content;
                }

                $data['owner'] = $linkManager->get('owner', $data['owner']);
                $data['type'] = $linkManager->get('type', $data['type']);

                if (isset($data['facets'])) {
                    $data['facets'] = array_map(function ($facet) use ($linkManager) {
                        return $linkManager->get('facet', $facet);
                    }, $data['facets']);
                }

                $this->bulker->index(['_index' => self::INDEX, '_type' => 'url', '_id' => sha1('url_'.$url)], $data);

                $progress->advance();
            } catch (\UnexpectedValueException $e) {
                $style->error($e->getMessage());
                $style->writeln($data['url']);
            }
        }

        $this->bulker->send(true);
        $progress->finish();
    }

    private function reset(InputInterface $input)
    {
        if (!$input->getOption('reset')) {
            return;
        }

        $indices = $this->client->indices();

        if ($indices->exists(['index' => self::INDEX])) {
            $indices->delete(['index' => self::INDEX]);
        }

        $indices->create([
            'index' => self::INDEX,
            'body' => json_decode(file_get_contents(__DIR__ . '/tech_search_mapping.json'), true),
        ]);

    }

    private function download(HttpClient $client, $url)
    {
        $response = $client->get($url);

        if (200 !== $statusCode = $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('%s resulted in %s', $url, $statusCode));
        }

        return $response;
    }

    /** wil convert each url crawler object into array */
    private function getUrlCrawler(EMSLink $emsLink)
    {
        return new class($emsLink) {
            private $sitemapLink;

            public function __construct(EMSLink $emsLink)
            {
                $this->sitemapLink = sprintf('%s:%s', $emsLink->getContentType(), $emsLink->getOuuid());
            }

            public function each(Crawler $url)
            {
                $location = $url->filterXPath('child::*/g:loc')->text();
                $languages =  $url->filterXPath('child::*/g:language')->each(function (Crawler $lang) {
                    return $lang->text();
                });

                $lastmod = $url->filterXPath('child::*/g:lastmod')->text();
                if($lastmod === 'last_published_date') {
                    $lastmod = '2018-12-13T10:39:15+0100';
                }

                $data = [
                    'url' => $location,
                    'lastmod' => $lastmod,
                    'languages' => $languages,
                    'owner' => $url->filterXPath('child::*/g:owner')->text(),
                    'type' => $url->filterXPath('child::*/g:type')->text(),
                    'facets' => $url->filterXPath('child::*/g:facet')->each(function (Crawler $facet) {
                        return $facet->text();
                    }),
                    'service_id' => self::filterNull($url, 'child::*/g:service/g:id'),
                    'keywords' => self::filterNull($url, 'child::*/g:service/g:keywords'),
                    'sitemap' => $this->sitemapLink,
                ];
                
                $serviceName = self::filterNull($url, 'child::*/g:service/g:name');

                foreach ($languages as $lang) {
                    $data['url_'.$lang] = $location;
                    $data['service_name_'.$lang] = $serviceName;
                }

                $url->filterXPath('child::*/g:description')->each(function (Crawler $d) use (&$data) {
                    $lang = $d->filterXPath('child::*/g:language')->text();
                    $title = $d->filterXPath('child::*/g:title');
                    $body = $d->filterXPath('child::*/g:body');


                    $data['title_'.$lang] = $title->count() === 1 ? $title->text() : null;
                    $data['body_'.$lang] = $body->count() === 1 ? $body->text() : null;
                });

                return array_filter($data);
            }

            /** for optional fields */
            private static function filterNull(Crawler $crawler, $xpath)
            {
                return $crawler->filterXPath($xpath)->count() > 0 ? $crawler->filterXPath($xpath)->text() : null;
            }
        };
    }

    private function getLinkManager(Client $client, $index)
    {
        return new class ($client, $index) {
            private $client;
            private $index;
            private $links = [];

            public function __construct(Client $client, string $index)
            {
                $this->client = $client;
                $this->index = $index;
            }

            public function get(string $type, string $name): string
            {
                if (array_key_exists($name, $this->links)) {
                    return $this->links[$name];
                }

                $result = $this->client->search(['type' => $type, 'index' => $this->index, 'body' => ['query' => ['term' => ['identifier' => $name]]]]);
                $total = $result['hits']['total'];

                if ($total === 0) {
                    throw new \UnexpectedValueException(sprintf('found no %s for name %s', $type, $name));
                } elseif ($total > 1) {
                    throw new \UnexpectedValueException(sprintf('found %d %s\'s for name %s', $total, $type, $name));
                } else {
                    $emsLink = EMSLink::fromDocument(array_pop($result['hits']['hits']));
                    $this->links[$name] = sprintf('%s:%s', $emsLink->getContentType(), $emsLink->getOuuid());

                    return $this->links[$name];
                }
            }
        };
    }

    private function createTika()
    {
        $tika = new HttpClient([
            'base_uri' => $this->tikaServer,
            'timeout' => 30
        ]);

        if (200 !== $hello = $tika->get('/tika')->getStatusCode()) {
            throw new \RuntimeException(sprintf('Tika server (%s) down ? [%d]', $this->tikaServer, $hello));
        }

        return $tika;
    }

    private function extractFileInfo(HttpClient $tika, ResponseInterface $response)
    {
        $contentLength = $response->getHeaderLine('Content-Length');
        $contentDisposition = $response->getHeaderLine('Content-Disposition');

        if ($contentDisposition) {
            preg_match('/filename="(?\'filename\'.+)"/', $contentDisposition, $matches);
            $filename = $matches['filename'];
        } else {
            $filename = null;
        }

        /*
        $metaResponse = $tika->put('/meta', [
            'body' => $response->getBody()->__toString(),
            'headers' => ['Accept' => 'application/json']
        ]);
        $meta = json_decode($metaResponse->getBody()->getContents(), true);
        */


        return array_filter([
            'filename' => $filename,
            'filesize' => $contentLength != '' ? $contentLength : $response->getBody()->getSize(),
            'mimetype' => $response->getHeader('Content-Type'),
        ]);
    }

    private function extractContent(HttpClient $tika, ResponseInterface $response)
    {
        try {
            $contentResponse = $tika->put('/tika', [
                'body' => $response->getBody()->__toString(),
                'headers' => ['Accept' => 'text/plain']
            ]);
            return $contentResponse->getBody()->getContents();
        }
        catch (\Exception $e) {
            return "";
        }

    }
}