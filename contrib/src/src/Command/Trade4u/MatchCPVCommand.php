<?php

namespace App\Command\Trade4u;

use Elasticsearch\Client;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Svg\Style;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\FormFactoryInterface;

class MatchCPVCommand extends Command implements CommandInterface
{
    /** @var Client */
    private $client;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var DataService */
    private $dataService;
    /**@var FormFactoryInterface $formFactory*/
    private $formFactory;

    protected static $defaultName = 'trade4u:match:cpv';

    public function __construct(
        Client $client,
        EnvironmentService $environmentService,
        DataService $dataService,
        FormFactoryInterface $formFactory
    ) {
        parent::__construct();
        $this->client = $client;
        $this->environmentService = $environmentService;
        $this->dataService = $dataService;
        $this->formFactory = $formFactory;
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Match cpv for products')
            ->addArgument('environment', InputArgument::OPTIONAL, 'environment', 'preview')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('Trade4u match CPV');

        $environmentName = $input->getArgument('environment');
        $environment = $this->environmentService->getByName($environmentName);

        if (!$environment) {
            throw new \RuntimeException(sprintf('environment %s not found', $environmentName));
        }

        $cpvs = $this->getCPVs($style, $environment->getAlias());
        $pBar = $style->createProgressBar(\count($cpvs));
        $pBar->start();

        $style->section('matching');

        foreach ($cpvs as $cpv) {
            foreach ($this->matchProducts($cpv, $environment->getAlias()) as $product) {
                $match = $this->getMatch($cpv['_source'], $product['_source']);
                $this->save($product, 'cpv:'.$cpv['_id'], $match);
            }
            $pBar->advance();
        }
        
        $pBar->finish();
        $pBar->clear();
    }

    private function matchProducts(array $cpv, string $index): \Generator
    {
        $body = [
            'query' => [
                'bool' => [
                    'minimum_should_match' => 1,
                    'should' => [
                        ['term' => ['title_nl.raw' => $cpv['_source']['title_nl']]],
                        ['term' => ['title_fr.raw' => $cpv['_source']['title_fr']]],
                        ['term' => ['title_en.raw' => $cpv['_source']['title_en']]],
                    ]
                ]
            ]
        ];
        $result = $this->client->search(['index' => $index, 'type' => 'product', 'body' => $body]);

        foreach ($result['hits']['hits'] as $product) {
            yield $product;
        }
    }

    private function getCPVs(SymfonyStyle $style, string $index): array
    {
        $scrollTimeout = '5s';
        $params = [
            'index' => $index,
            'type' => 'cpv',
            'scroll' => $scrollTimeout,
            'size' => 50,
            '_source' => ['title_nl', 'title_fr', 'title_en']
        ];

        $style->section("Analyzing cpv's");
        $pg = $style->createProgressBar();
        $pg->start();

        $result = [];
        $response = $this->client->search($params);
        $languages = ['nl', 'fr', 'en'];

        while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
            foreach ($response['hits']['hits'] as &$hit) {
                foreach ($languages as $lang) {
                    $analyze = $this->client->indices()->analyze([
                        'body' => [
                            'tokenizer' => 'keyword',
                            'char_filter' => ['html_strip'],
                            'filter' => ['lowercase', 'asciifolding'],
                            'text' =>  $hit['_source']['title_'.$lang]
                        ]
                    ]);


                    $hit['_source']['title_'.$lang] = $analyze['tokens'][0]['token'];
                }

                $result[] = $hit;
                $pg->advance();
            }

            $scrollId = $response['_scroll_id'];
            $response = $this->client->scroll(['scroll_id' => $scrollId, 'scroll' => $scrollTimeout]);
        }

        $pg->finish();
        $style->writeln(2);

        return $result;
    }

    private function getMatch(array $cpv, array $product): int
    {
        $a = [$cpv['title_nl'], $cpv['title_fr'], $cpv['title_en']];
        $b = array_map('strtolower', [$product['title_nl'], $product['title_fr'], $product['title_en']]);

        $diff = \array_diff($b, $a);

        return 3 - \count($diff);
    }

    private function save(array $product, string $cpv, int $cpvMatch)
    {
        $revision = $this->dataService->initNewDraft('product', $product['_id'], null, 'MATCH_JOB');
        $rawData = $revision->getRawData();
        $rawData['cpv'] = $cpv;
        $rawData['cpv_match'] = $cpvMatch;

        if( $revision->getDatafield() == NULL){
            $this->dataService->loadDataStructure($revision);
        }

        $builder = $this->formFactory->createBuilder(RevisionType::class, $revision);
        $form = $builder->getForm();

        $revision->setRawData($rawData);
        $this->dataService->finalizeDraft($revision, $form, 'MATCH_JOB', false);
    }
}