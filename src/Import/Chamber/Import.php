<?php

namespace App\Import\Chamber;

use App\Import\Chamber\XML\SearchActor;
use Elasticsearch\Client;
use Psr\Log\LoggerInterface;

class Import
{
    /** @var Client */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $rootDir;

    /** @var string */
    private $environment;
    private $legislatures = [];
    private $legislatureIds = [];
    private $parties = [];
    private $type;
    /** @var SearchActor */
    public $searchActor;
    /** @var bool */
    private $dryPdf;
    /** @var bool */
    private $keepCv;

    const EMS_INSTANCE_ID = 'webchamber_';


    public function __construct(Client $client, LoggerInterface $logger, string $dir, string $type, string $environment, bool $dryPdf, bool $keepCv)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->dryPdf = $dryPdf;
        $this->keepCv = $keepCv;
        $this->type = $type;
        $this->rootDir = ($type === Model::TYPE_ACTR) ? $dir.'/../../' : $dir.'/../../..';

        $this->environment = $environment;
        $this->legislatures = $this->buildLegislatures();
        $this->legislatureIds = \array_keys($this->legislatures);

        $this->searchActor = new SearchActor($this);
    }

    public function search(array $body): array
    {
        return $this->client->search([
            'index' => self::EMS_INSTANCE_ID . 'ma_' . $this->environment,
            'type' => 'doc',
            'body' => $body,
        ]);
    }

    public function get(string $index, string $id): array
    {
        return $this->client->get([
            'index' => $index,
            'type' => 'doc',
            'id' => Model::createId(Model::TYPE_ACTR, $id),
        ]);
    }

    public function existLegislature(int $id): bool
    {
        return \array_key_exists($id, $this->legislatures);
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getLegislatureDates(array $legislatureIds): array
    {
        $dates = [];

        foreach ($legislatureIds as $id) {
            $legislature = $this->getLegislature($id);

            $dates[] = $legislature['start'] ?? null;
            $dates[] = $legislature['end'] ?? null;
        }

        \array_filter($dates);
        \rsort($dates);

        return $dates;
    }

    public function getLegislaturesIds(): array
    {
        return $this->legislatureIds;
    }

    public function getActiveLegislatureId(): int
    {
        $legislatures = $this->legislatures;
        $first = array_shift($legislatures);

        return (int) $first['id'];
    }

    public function getLegislature(int $id): array
    {
        if($this->type === MODEL::TYPE_GENESIS){
            return [];
        }

        if (!$this->existLegislature($id)) {
            throw new \Exception(sprintf('Legislature unknown %s', $id));
        }

        return $this->legislatures[$id];
    }
    
    public function getLegislatureByDate(\DateTime $date): array
    {
        foreach ($this->legislatures as $legislature) {
            $start = \DateTime::createFromFormat( 'Y-m-d', $legislature['start']);
            $end = \DateTime::createFromFormat('Y-m-d', $legislature['end'] );

            if ($date >= $start && $date <= $end) {
                return $legislature;
            }
        }

        throw new \Exception(sprintf('Legislature unknown for date %s', $date->format('d-m-Y')));
    }

    public function getCommission(string $docName, int $legislature)
    {
        $result = $this->client->search([
            'index' => self::EMS_INSTANCE_ID . 'ma_' . $this->environment,
            'type' => 'doc',
            'body' => [
                'size' => 1,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['_contenttype' => ['value' => 'orgn' ]]],
                            ['term' => ['type_orgn' => ['value' => 'commission' ]]],
                            ['term' => ['doc_name' => ['value' => $docName ]]],
                            ['term' => ['legislature' => ['value' => $legislature ]]],
                        ]
                    ]
                ]
            ],
        ]);

        return (int) $result['hits']['total'] === 0 ? null :  'orgn:'.$result['hits']['hits'][0]['_id'];
    }

    public function getParty(string $emsLink): ?string
    {
        if ($this->parties == null) {
            $this->buildParties();
        }

        return isset($this->parties[$emsLink]) ? $this->parties[$emsLink]['id'] : null;
    }

    public function getPartyName(string $id, string $locale): ?string
    {
        if ($this->parties == null) {
            $this->buildParties();
        }

        return $this->parties[$id]['title_'.$locale] ?? null;
    }

    private function buildParties(): void
    {
        $search = $this->search(['size' => 1000, 'query' => ['term' => ['type_orgn' => ['value' => 'party'] ]]]);

        foreach ($search['hits']['hits'] as $hit) {
            $this->parties['orgn:'.$hit['_id']] = [
                'title_nl' => $hit['_source']['title_nl'],
                'title_fr' => $hit['_source']['title_fr'],
            ];

            $orgnIds = $hit['_source']['orgn_ids'] ?? [];

            foreach ($orgnIds as $id) {
                $this->parties[$id] = ['id' => 'orgn:'.$hit['_id']];
            }
        }
    }

    private function buildLegislatures(): array
    {
        $this->logger->info('Getting legislations');

        $result = $this->client->search([
            'index' => self::EMS_INSTANCE_ID . $this->environment,
            'type' => 'legislature',
            'size' => 100,
            'body' => ['sort' => ['date_start' => 'desc']],
        ]);
        $legislatures = [];

        foreach ($result['hits']['hits'] as $hit) {
            $legislatures[$hit['_id']] = [
                'id' => $hit['_id'],
                'start' => \DateTime::createFromFormat('Y/m/d', $hit['_source']['date_start'])->format('Y-m-d'),
                'end' => \DateTime::createFromFormat('Y/m/d', $hit['_source']['date_end'])->format('Y-m-d')
            ];
        }

        return $legislatures;
    }

    public function getRootDir(): string
    {
        return $this->rootDir;
    }

    public function isAttachmentIndexingEnabled(): bool
    {
        return !$this->dryPdf;
    }

    public function hasKeepCv(): bool
    {
        return $this->keepCv;
    }
}
