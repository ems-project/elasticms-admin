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
 * FLWB (NL: Wetsvoorstel, FR: Propoistion de loi, EN: Legislation)
 */
class FLWB extends Model implements ParentDocument
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

        parent::__construct($import, Model::TYPE_FLWB, $this->source['legislature'] . $this->source['id_flwb']);
    }

    public function getActor(string $type, string $ksegna, string $firstName, string $lastName, ?string $party): string
    {
        $fullName = sprintf('%s %s', $firstName, $lastName);

        return $this->import->searchActor->get($this->source['legislature'], $type, $ksegna, $fullName, $party);
    }

    public function createFileInfo(string $name): array
    {
        $info = ['label' => $name, 'filename' => sprintf('%s.pdf', $name)];

        preg_match('/(?P<leg>\d{2})(?P<type>K|S|R)(?P<folder>\d{4})/', $name, $match);

        if ('K' === $match['type']) {
            $info['path'] = sprintf('FLWB/Pdf/%d/%s/', $match['leg'], $match['folder']);
        } elseif ('S' === $match['type']) {
            $senateLeg = ($match['leg'] - 48); //49 is 1
            if ($senateLeg >= 1) {
                $info['senate_uri'] = \http_build_query(['LEG' => $senateLeg, 'NR' => $match['folder']]);
            }
        }

        return $info;
    }

    public function getSource(): array
    {
        $this->source = (\array_merge($this->source, $this->keywords->getSource()));

        return parent::getSource();
    }

    public function getChildren(): array
    {
        return array_merge($this->children, $this->keywords->all());
    }

    public function hasPublicationDate(): bool
    {
        return isset($this->source['date_publication']);
    }

    public function isMainDocType(string $docType): bool
    {
        return $this->mainDocType === $docType;
    }

    public function inDocTypes(string $docType): bool
    {
        return \in_array($docType, $this->docTypes, true);
    }

    public function inMainDocTypes(string $docType): bool
    {
        return \in_array($docType, $this->mainDocTypes, true);
    }

    public function inStatusChamber(string $locale, string $search): bool
    {
        if (!isset($this->source['status_chamber_' . $locale])) {
            return false;
        }

        return strpos(strtoupper($this->source['status_chamber_' . $locale]), strtoupper($search));
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

    public function isNotFirstBornOrCopy(): bool
    {
        return false === $this->source['is_first_born'] || false === $this->source['is_copy'];
    }

    protected function clean($value, $key): bool
    {
        if ($value === 'nothing' && $key === '@FIELD') {
            return true;
        }
        if (is_array($value)) {
            return empty($value);
        }

        if (trim($value) === '') {
            return true;
        }
        if (($key === 'MONITEUR_nr' || $key === 'COMPET_BEVOEGD_AANTALDAG') && $value === '000') {
            return true;
        }

        return false;
    }

    protected function getRootElements(): array
    {
        return [
            'SDOCNAME', 'ID', 'TITLE', 'TITLE_SHORT', 'TERMART', 'SITU', 'LEG', 'BICAM', 'COMMISSIES', 'COMPET_BEVOEGD',
            'TREFWOORDEN', 'VOTEKAMER', 'VOTESENAAT', 'VOTEMOT', 'VOTECAN', 'PUBLIC', 'MAIN_DEPOTDAT', 'COPY', 'FirstBorn'
        ];
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
            'LEG' => ['source', 'legislature', $intCallback],
            'ID' => ['source', 'key_flwb', $intCallback],
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

    protected function parseSDOCNAME(string $value): void
    {
        preg_match('/(\d+)(?P<type>K|S|R)(?P<id>\d+)/', $value, $match);
        $types = ['K' => 'chamber', 'S' => 'senate', 'R' => 'united'];

        $this->source['id_flwb'] = $value;
        $this->source['id_flwb_short'] = substr($value, -4);
        $this->source['id_number'] = intval(substr($value, -4));

        if ('R' == $match['type']) {
            $this->source['types_flwb'] = [$types['K'], $types['S']];
        } else {
            $this->source['types_flwb'] = $types[$match['type']];
        }
    }

    protected function parseTITLE(array $value): void
    {
        if (isset($value['VERSION']) && isset($value['TITLE_LONG'])) {
            $value = [$value]; //only one
        }

        $titles = [];

        foreach ($value as $data) {
            $this->titles[] = $data['TITLE_LONG']['TITLE_LONG_textF'];
            $this->titles[] = $data['TITLE_LONG']['TITLE_LONG_textN'];

            $titles[] = [
                'version_number' => $data['VERSION']['VERSIENR'],
                'version_code' => $data['VERSION']['VERSION_KODE']['VERSION_KODE_NR'] ?? null,
                'title_fr' => $data['TITLE_LONG']['TITLE_LONG_textF'],
                'title_nl' => $data['TITLE_LONG']['TITLE_LONG_textN'],
            ];
        }

        $this->source['title_fr'] = $titles[0]['title_fr'];
        $this->source['title_nl'] = $titles[0]['title_nl'];
        $this->source['titles'] = $titles;
    }

    protected function parseTITLE_SHORT(array $value): void
    {
        $this->titles[] = $value['TITLE_SHORT_textF'] ?? null;
        $this->titles[] = $value['TITLE_SHORT_textN'] ?? null;

        if (!isset($this->source['title_fr'])) {
            $this->source['title_fr'] = $value['TITLE_SHORT_textF'];
        } else {
            $this->source['title_short_fr'] = $value['TITLE_SHORT_textF'] ?? null;
        }

        if (!isset($this->source['title_nl'])) {
            $this->source['title_nl'] = $value['TITLE_SHORT_textN'];
        } else {
            $this->source['title_short_nl'] = $value['TITLE_SHORT_textN'] ?? null;
        }
    }

    protected function parseTERMART(array $value): void
    {
        $this->source['constitution_number'] = (int)$value['TERMART_KODE'];
        $this->source['constitution_fr'] = $value['TERMART_FR'] ?? null;
        $this->source['constitution_nl'] = $value['TERMART_NL'] ?? null;
    }

    protected function parseSITU(array $value): void
    {
        if (isset($value['SITUK_textF'])) {
            $this->source['status_fr'] = $value['SITUK_textF'];
        }
        if (isset($value['SITUK_textN'])) {
            $this->source['status_nl'] = $value['SITUK_textN'];
        }
    }

    protected function parseBICAM($value): void
    {
        $nested = isset($value['MAINDOC']['BICAM_SDOCNAME']) ? [$value['MAINDOC']] : $value['MAINDOC'];
        $docTypes = [];

        foreach ($nested as $data) {
            $doc = new FLWBDoc($this, $this->import, $data);
            $this->indexAttachments($doc);

            if ($doc->isMainDocChamber()) {
                $this->mainDocType = $doc->getDocType();
                $this->mainDocTypes = $doc->getDocTypes();
            }

            $docTypes = array_merge($docTypes, $doc->getDocTypes());
            $this->source['docs'][] = $doc->getSource();
            foreach ($doc->getSource() as $subdocs) {
                if (is_array($subdocs)) {
                    foreach ($subdocs as $subdoc) {
                        if(is_array($subdoc) && isset($subdoc['doc_type']) && $subdoc['doc_type'] == 'flwb_doc_type.motion'){
                            $this->createMotion($subdoc);
                        }
                    }
                }
            }
        }

        $this->docTypes = array_values(array_unique($docTypes));
    }

    protected function parseCOMMISSIES(array $value): void
    {
        $nested = isset($value['COMMISSIE']['SLEUTEL']) ? [$value['COMMISSIE']] : $value['COMMISSIE'];

        foreach ($nested as $data) {
            $commission = new FLWBCommission($this, $data);
            $this->source['commissions'][] = $commission->getSource();
        }
    }

    protected function parseTREFWOORDEN(array $value): void
    {
        foreach ($value as $type => $keywords) {
            $nested = isset($keywords[$type . '_kode']) ? [$keywords] : $keywords;

            foreach ($nested as $data) {
                $this->keywords->addFLWB($type, $data);
            }
        }

        $this->keywords->deduplicateMainKeywords();
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

    protected function parsePUBLIC(array $value): void
    {
        $this->source['date_publication'] = self::createDate($value['MONITEUR']['MONITEUR_DATE']);
        $this->source['number_publication'] = (int)$value['MONITEUR']['MONITEUR_nr'];

        if (isset($value['MONITEUR']['LAWDAT'])) {
            $this->source['date_publication_law'] = self::createDate($value['MONITEUR']['LAWDAT']);
        }

        if (isset($value['ERRATUM'])) {
            foreach ($value['ERRATUM'] as $e) {
                $this->source['errata'][] = [
                    'erratum_number' => (int)$e['ERRATUM_NR'],
                    'erratum_date' => self::createDate($e['ERRATUM_DATE']),
                ];
            }
        }
    }

    private function setSearch(array $legislature): void
    {
        $this->source['search_id'] = $this->source['id_flwb'];
        $this->source['search_type'] = Model::TYPE_FLWB;
        $this->source['search_types'] = SearchTypes::single(SearchCategories::forFLWB($this), $this->source['legislature']);
        $this->source['search_actors'] = $this->import->searchActor->getEmsLinks();
        $this->source['search_actors_types'] = $this->import->searchActor->getTypes();
        $this->source['search_doc_types_flwb'] = $this->docTypes ?? 'empty';

        $dates = [
            $this->source['date_submission'] ?? null,
            $this->source['date_vote_chamber'] ?? null,
            $this->source['date_vote_senate'] ?? null,
            $this->source['date_vote_motion'] ?? null,
            $this->source['date_vote_candidacy'] ?? null,
            $this->source['date_publication'] ?? null,
            $this->source['date_publication_law'] ?? null,
        ];

        $dates = \array_filter($dates);
        if (null == $dates) {
            $dates = [$legislature['start'], $legislature['end']];
        }

        \sort($dates);
        $this->source['search_dates'] = $dates;
        $this->source['search_date_sort'] = $dates[0];

        if (!$this->keywords->isEmpty()) {
            $this->source['keywords_fr'] = $this->keywords->getKeywordsText('fr');
            $this->source['keywords_nl'] = $this->keywords->getKeywordsText('nl');
            $this->source['search_keywords'] = $this->keywords->getEmsIds();
        }
    }

    protected function indexAttachments(FLWBDoc $doc): void
    {

        if (!$this->import->isAttachmentIndexingEnabled()) {
            return;
        }

        $flwbPdf = $this->indexAttachment($this->source['id_flwb'] ?? '', $doc->getSource());

        if ($flwbPdf === null) {
            return;
        }

        foreach ($flwbPdf->getSubDocs() as $subDoc) {
            $this->indexAttachment($this->source['id_flwb'] ?? '', $subDoc);
        }
    }

    protected function indexAttachment(string $idFlwb, $source): ?FLWBPdf
    {
        if (!file_exists($this->getFileWithPath($source))) {
            return null;
        }

        $flwbPdf = new FLWBPdf($this, $this->import, $source, $this->storageManager, $this->extractorService, $this->fileService);
        $this->children[] = Child::createFLWBPdf($idFlwb, $flwbPdf, $source, $this->import->searchActor->getFilteredTypes($source['authors'] ?? []));

        return $flwbPdf;
    }

    protected function getFileWithPath(array $source): string
    {
        if (isset($source['file']['path']) && isset($source['file']['filename'])) {
            return $this->import->getRootDir() . '/' . $source['file']['path'] . $source['file']['filename'];
        }
        return 'FLWB doc not found';
    }

    protected function createMotion($source): void
    {
        if(!$this->import->isAttachmentIndexingEnabled()){
            return;
        }

        if(!file_exists($this->getFileWithPath($source))){
            return;
        }

        $moti = new MOTI($this, $source, $this->import, $this->storageManager, $this->extractorService, $this->fileService);
        $this->children[] = Child::createMOTI($moti, $this->import->searchActor->getFilteredTypes($moti->getActors()));
    }
}
