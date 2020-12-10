<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Elasticsearch\ParentDocument;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Finder\SplFileInfo;

/**
 * GENESIS = archived FLWB (NL: Wetsvoorstel, FR: Propoistion de loi, EN: Legislation)
 */
class GENESIS extends Model implements ParentDocument
{
    use XML;

    /** @var Keywords */
    protected $keywords;
    private $children = [];

    /** @var ?string */
    private $mainDocType;
    private $mainDocTypes = [];
    private $docTypes = [];
    private $status = [];
    private $titles = [];

    /** @var StorageManager */
    private $storageManager;
    /** @var AssetExtractorService */
    private $extractorService;
    /** @var FileService */
    private $fileService;

    const BICAM_TYPE_CHAMBER = 'chamber';
    const BICAM_TYPE_SENATE = 'senate';
    const BICAM_TYPE_UNITED = 'united';
    const BICAM_DEFAULT_CODE = 'K';

    public function __construct(SplFileInfo $file, Import $import, StorageManager $storageManager, AssetExtractorService $extractorService, FileService $fileService)
    {

        $this->import = $import;
        $this->storageManager = $storageManager;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;

        $this->keywords = new Keywords();
        $this->source['keywords_fr'] = [];
        $this->source['keywords_nl'] = [];

        $this->process($this->xmlToArray($file));
        $this->setSearch($import->getLegislature($this->source['legislature']));

        if (!isset($this->source['search_date_sort'])) {
            $this->source['search_date_sort'] = $this->source['date_submission'] ?? null;
        }

        if (!isset($this->source['search_dates']) && $this->source['search_date_sort'] !== null) {
            $this->source['search_dates'] = [$this->source['search_date_sort']];
        }

        parent::__construct($import, Model::TYPE_GENESIS, $this->source['id_genesis']);
    }

    public function getSource(): array
    {
        return parent::getSource();
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    protected function getRootElements(): array
    {
        return [
            'MAIN_DEPOTDAT', 'COPY', 'SDOCNAME', 'TITLE', 'TITLE_SHORT', 'SITU', 'LEG', 'STATUSOLD', 'BICAM',
            'COMMISSIES', 'COMPET_BEVOEGD', 'TREFWOORDEN', 'VOTEKAMER', 'VOTESENAAT', 'VOTEMOT', 'VOTECAN', 'PUBLIC'
        ];
    }

    protected function parseCOMMISSIES(array $value): void
    {
        $nested = isset($value['COMMISSIE']['SLEUTEL']) ? [$value['COMMISSIE']] : $value['COMMISSIE'];

        foreach ($nested as $data) {
            if (isset($data['SLEUTEL']) && $data['SLEUTEL'] === 0) {
                continue;
            }
            if (!isset($data['COMMISSIE_CLASS']) || !isset($data['COMMISSIE_NAAM'])) {
                continue;
            }
            $commission = [];
            $commission['class'] = $data['COMMISSIE_CLASS']['COMMISSIE_CLASS_KODE'] ?? null;
            $commission['class_fr'] = $data['COMMISSIE_CLASS']['COMMISSIE_CLASS_textF'] ?? null;
            $commission['class_nl'] = $data['COMMISSIE_CLASS']['COMMISSIE_CLASS_textN'] ?? null;
            $commission['key_commission'] = $data['SLEUTEL'] ?? null;
            $commission['title_fr'] = $data['COMMISSIE_NAAM']['COMMISSIE_NAAM_textF'] ?? null;
            $commission['title_nl'] = $data['COMMISSIE_NAAM']['COMMISSIE_NAAM_textN'] ?? null;
            $commission['type'] = $data['COMMISSIE_TYPE']['COMMISSIE_TYPE_KODE'] ?? null;
            $commission = array_filter($commission);

            if (isset($data['KALENDER'])) {
                foreach ($data['KALENDER'] as $item) {
                    if (isset($item['KALENDER_kode']) && isset($item['KALENDER_textN']) && isset($item['KALENDER_textF'])) {
                        $commission['calendar'][] = array_filter([
                            'code' => (int)$item['KALENDER_kode'],
                            'title_fr' => $item['KALENDER_textF'],
                            'title_nl' => $item['KALENDER_textN'],
                            'date' => Model::createDate($item['KALENDER_DATUM']),
                            'remark' => $item['KALENDER_OPMERKING'] ?? null,
                        ], [Model::class, 'arrayFilterFunction']);
                    }
                }
            }

            $this->source['commissions'][] = $commission;
        }
    }

    protected function parseCOMPET_BEVOEGD(array $value): void
    {
        $nested = isset($value['COMPET_BEVOEGD_KODE']) ? [$value] : $value;

        foreach ($nested as $data) {
            $this->source['jurisdictions'][] = array_filter([
                'code' => $data['COMPET_BEVOEGD_KODE'],
                'date' => self::createDate($data['COMPET_BEVOEGD_DATUM']),
                'text_fr' => $data['COMPET_BEVOEGD_textF'],
                'text_nl' => $data['COMPET_BEVOEGD_textN'],
                'days' => $data['COMPET_BEVOEGD_AANTALDAG'] ?? null, //never set?
                'comment_fr' => isset($data['OPMERKING']) ? $data['OPMERKING']['OPMERKING_textF'] : null,
                'comment_nl' => isset($data['OPMERKING']) ? $data['OPMERKING']['OPMERKING_textN'] : null,
            ]);
        }
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (int $value) {
            return $value;
        };
        $stringCallback = function (string $value) {
            return $value;
        };
        $dateCallback = function (string $value) {
            return Model::createDate($value);
        };
        $notYesCallback = function (string $value) {
            return strtoupper($value) !== 'N';
        };

        return [
            'SITUK_textF' => ['source', 'status_chamber_fr', $stringCallback],
            'SITUK_textN' => ['source', 'status_chamber_nl', $stringCallback],
            'SITUS_textF' => ['source', 'status_senate_fr', $stringCallback],
            'SITUS_textN' => ['source', 'status_senate_nl', $stringCallback],
            'MAIN_DEPOTDAT' => ['source', 'date_submission', $dateCallback],
            'VOTEKAMER' => ['source', 'date_vote_chamber', $dateCallback],
            'VOTESENAAT' => ['source', 'date_vote_senate', $dateCallback],
            'VOTEMOT' => ['source', 'date_vote_motion', $dateCallback],
            'VOTECAN' => ['source', 'date_vote_candidacy', $dateCallback],
            'COPY' => ['source', 'is_copy', $notYesCallback],
            'FirstBorn' => ['source', 'is_first_born', $notYesCallback],
        ];
    }

    private function setSearch(array $legislature): void
    {
        $this->source['search_id'] = $this->source['id_genesis'];
        $this->source['key_genesis'] = $this->source['id_genesis'];
        $this->source['search_type'] = Model::TYPE_GENESIS;
        $this->source['search_types'] = SearchTypes::single(SearchCategories::forGENESIS($this), $this->source['legislature']);
        $this->source['search_actors'] = $this->import->searchActor->getEmsLinks();
        $this->source['search_actors_types'] = $this->import->searchActor->getTypes();
        $this->source['search_doc_types_flwb'] = $this->docTypes ?? 'empty';

        if (!$this->keywords->isEmpty()) {
            $this->source['keywords_fr'] = $this->keywords->getKeywordsText('fr');
            $this->source['keywords_nl'] = $this->keywords->getKeywordsText('nl');
            $this->source['search_keywords'] = $this->keywords->getEmsIds();
        }

    }

    protected function parseSDOCNAME(string $value): void
    {
        preg_match('/(\d+)(?P<type>K|S|R)(?P<id>\d+)/', $value, $match);

        $this->source['id_genesis'] = $value;
        $this->source['id_genesis_short'] = substr($value, -4);
        $this->source['legislature'] = substr($value, 0, 2);
        $this->source['is_first_born'] = true;
    }

    protected function parseTITLE(array $value): void
    {
        if (isset($value[0])) {
            $value = $value[0];
        }

        if (isset($value['TITLE_LONG']['TITLE_LONG_textF']['#']) && isset($value['TITLE_LONG']['TITLE_LONG_textN']['#'])) {
            $this->source['title_fr'] = $value['TITLE_LONG']['TITLE_LONG_textF']['#'];
            $this->source['title_nl'] = $value['TITLE_LONG']['TITLE_LONG_textN']['#'];
        }

        if (isset($value['TITLE_LONG']['TITLE_LONG_textF FIELD']) && isset($value['TITLE_LONG']['TITLE_LONG_textN FIELD'])) {
            $this->source['title_fr'] = $value['TITLE_LONG']['TITLE_LONG_textF FIELD'];
            $this->source['title_nl'] = $value['TITLE_LONG']['TITLE_LONG_textN FIELD'];
        }

        if (isset($value['VERSION'])) {
            $titles = [];
            $titles['version_number'] = $value['VERSION']['VERSIENR'] ?? null;
            $titles['version_code'] = $value['VERSION']['VERSION_KODE']['VERSION_KODE_NR'] ?? null;
            $titles['version_code_nl'] = $value['VERSION']['VERSION_KODE_NL'] ?? null;
            $titles['version_code_fr'] = $value['VERSION']['VERSION_KODE_FR'] ?? null;

            $this->source['titles'] = array_filter($titles);
        }
    }

    protected function parseBICAM($value): void
    {
        $types = ['K' => self::BICAM_TYPE_CHAMBER, 'S' => self::BICAM_TYPE_SENATE, 'R' => self::BICAM_TYPE_UNITED];
        $owner = isset($value['MAINDOC']['OWNER']['OWNER_KODE']['OWNER']) ? $value['MAINDOC']['OWNER']['OWNER_KODE']['OWNER'] : self::BICAM_DEFAULT_CODE;
        if (!in_array($owner, $types)) {
            $owner = self::BICAM_DEFAULT_CODE;
        }
        $this->source['types_genesis'] = $types[$owner];

        $nested = isset($value['MAINDOC']['BICAM_SDOCNAME']) ? [$value['MAINDOC']] : $value['MAINDOC'];
        $docTypes = [];

        foreach ($nested as $data) {
            // A string is always invalid
            if (is_string($data)) {
                continue;
            }
            // Skip empty or invalid legislatures
            if (isset($data['LEGISL']) && ($data['LEGISL'] === '' || !is_numeric($data['LEGISL']))) {
                unset($data['LEGISL']);
            }
            // Skip subdoc distribution date if it's an array (always empty and invalid)
            if (isset($data['SUBDOCS']['SUBDOC']['SUBDOC_DISTRIBUTION_DATE']) && is_array($data['SUBDOCS']['SUBDOC']['SUBDOC_DISTRIBUTION_DATE'])) {
                unset($data['SUBDOCS']['SUBDOC']['SUBDOC_DISTRIBUTION_DATE']);
            }
            // Skip AUTEURM if it's empty (invalid)
            if (isset($data['AUTEURM']['AUTEURM_TYPE']['AUTEURM_TYPE_KODE']) && $data['AUTEURM']['AUTEURM_TYPE']['AUTEURM_TYPE_KODE'] === '') {
                unset($data['AUTEURM']);
            }
            // Don't import empty
            if (isset($data['EDESCRIPTOR']['EDESCRIPTOR_kode']) && $data['EDESCRIPTOR']['EDESCRIPTOR_kode'] === ' ') {
                unset($data['EDESCRIPTOR']);
            }
            // Don't import empty
            if (isset($data['EKANDIDAAT']['EKANDIDAAT_kode']) && $data['EKANDIDAAT']['EKANDIDAAT_kode'] === ' ') {
                unset($data['EKANDIDAAT']);
            }


            $doc = new FLWBDoc($this, $this->import, $data);

            if ($doc->isMainDocChamber()) {
                $this->mainDocType = $doc->getDocType();
                $this->mainDocTypes = $doc->getDocTypes();
            }

            $docTypes = array_merge($docTypes, $doc->getDocTypes());
            $this->source['docs'][] = $doc->getSource();
        }

        $this->docTypes = array_values(array_unique($docTypes));

    }

    public function isNotFirstBornOrCopy(): bool
    {
        return false === $this->source['is_first_born'] || false === $this->source['is_copy'];
    }

    public function isMainDocType(string $docType): bool
    {
        return $this->mainDocType === $docType;
    }

    public function inMainDocTypes(string $docType): bool
    {
        return \in_array($docType, $this->mainDocTypes, true);
    }

    public function inTitle(string $search): bool
    {
        $titles = array_unique(array_filter($this->titles));
        $pattern = sprintf('/.*%s.*/i', $search);

        foreach ($titles as $title) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }

        return false;
    }

    public function inStatusChamber(string $locale, string $search): bool
    {
        if (!isset($this->source['status_chamber_' . $locale])) {
            return false;
        }

        return strpos(strtoupper($this->source['status_chamber_' . $locale]), strtoupper($search));
    }

    public function hasPublicationDate(): bool
    {
        return isset($this->source['date_publication']);
    }

    public function inDocTypes(string $docType): bool
    {
        return \in_array($docType, $this->docTypes, true);
    }

    public function createFileInfo(string $name): array
    {
        $info = ['label' => $name, 'filename' => sprintf('%s.pdf', $name)];

        // @TODO import PDFs

        return $info;
    }

    public function getActor(string $type, string $ksegna, string $firstName, string $lastName, ?array $party): string
    {
        $fullName = sprintf('%s %s', $firstName, $lastName);
        if (isset($party) && $party[0] === '') {
            $party = '';
        }
        return $this->import->searchActor->get($this->source['legislature'], $type, $ksegna, $fullName, $party);
    }

    protected function parseTREFWOORDEN(array $value): void
    {
        foreach ($value as $type => $keywords) {
            $nested = isset($keywords[$type . '_kode']) ? [$keywords] : $keywords;
            foreach ($nested as $data) {
                if (isset($data['IMPORTANT_kode']) && ($data['IMPORTANT_kode'] === '' || $data['IMPORTANT_kode'] === ' ')) {
                    continue;
                }
                if (isset($data['EDESCRIPTOR_kode']) && ($data['EDESCRIPTOR_kode'] === 0 || $data['EDESCRIPTOR_kode'] === '' || $data['EDESCRIPTOR_kode'] === ' ')) {
                    continue;
                }
                if (isset($data['EKANDIDAAT_kode']) && ($data['EKANDIDAAT_kode'] === 0 || $data['EKANDIDAAT_kode'] === '' || $data['EKANDIDAAT_kode'] === ' ')) {
                    continue;
                }
                if (isset($data['FREE_kode']) && ($data['FREE_kode'] === 0 || $data['FREE_kode'] === '')) {
                    continue;
                }
                if (isset($data['FREE_textF']) && ($data['FREE_textF'] === 'NOT FOUND' || empty($data['FREE_textF']) || $data['FREE_textF'] === ' ')) {
                    continue;
                }
                $this->keywords->addFLWB($type, $data);
            }
        }

        $this->keywords->deduplicateMainKeywords();
    }
}
