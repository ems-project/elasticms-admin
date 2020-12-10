<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use Psr\Log\LoggerInterface;

class FLWBDoc
{
    use XML;
    protected $source = [];

    /** @var Model */
    private $flwb;
    /** @var string */
    private $docCode;
    /** @var array */
    private $subDocTypes = [];
    /** @var LoggerInterface */
    private $logger;

    const TYPES_AUTHORS = [
        '0' => 'AUTEUR, GENESIS',
        '1' => 'AUTEUR, AUTEUR',
        '2' => 'SUCCESSEUR-AUTEUR, OVERNEMER-AUTEUR',
        '3' => 'SIGNATAIRE, ONDERTEKENAAR',
        '4' => 'SUCCESSEUR-SIGNATAIRE, OVERNEMER-ONDERTEKENAAR'
    ];
    const TYPES_OWNER = [
        'K' => 'KAMER, CHAMBRE',
        'S' => 'SENAAT, SENAT',
        'R' => 'VERENIGDE KAMERS, CHAMBRES REUNIES',
    ];

    const DOC_TYPE_LAW_PROJECT = 'flwb_doc_type.law_project';
    const DOC_TYPE_LAW_PROPOSAL = 'flwb_doc_type.law_proposal';
    const DOC_TYPE_RESOLUTION_PROPOSAL = 'flwb_doc_type.resolution_proposal';
    const DOC_TYPE_NATURALIZATION = 'flwb_doc_type.naturalization';
    const DOC_TYPE_AMENDMENT = 'flwb_doc_type.amendment';
    const DOC_TYPE_TEXT_ADOPTED = 'flwb_doc_type.text_adopted';
    const DOC_TYPE_STATE_ADVISE = 'flwb_doc_type.state_advise';
    const DOC_TYPE_COMMISSION_REPORT = 'flwb_doc_type.commission_report';
    const DOC_TYPE_NOMINATION_CANDIDATE = 'flwb_doc_type.nomination_candidate';
    const DOC_TYPE_POLICY_STATEMENT = 'flwb_doc_type.policy_statement';
    const DOC_TYPE_MOTION = 'flwb_doc_type.motion';
    const DOC_TYPE_LIST = 'genesis_doc_type.list';
    const DOC_TYPE_TRANSMITTED = 'genesis_doc_type.transmitted';
    const DOC_TYPE_ADOPTED = 'genesis_doc_type.adopted';

    const DOC_TYPES = [
        self::DOC_TYPE_LAW_PROJECT => ['01', '02'],
        self::DOC_TYPE_LAW_PROPOSAL => ['05', '07', '08', '26'],
        self::DOC_TYPE_RESOLUTION_PROPOSAL => ['06'],
        self::DOC_TYPE_NATURALIZATION => ['10', '77', '44'],
        self::DOC_TYPE_NOMINATION_CANDIDATE => ['13'],
        self::DOC_TYPE_POLICY_STATEMENT => ['29'],
        self::DOC_TYPE_AMENDMENT => ['31', '45', '52'],
        self::DOC_TYPE_TEXT_ADOPTED => ['34', '35'],
        self::DOC_TYPE_STATE_ADVISE => ['36'],
        self::DOC_TYPE_COMMISSION_REPORT => ['23', '32', '41', '33'],
        self::DOC_TYPE_MOTION => ['63'],
        self::DOC_TYPE_LIST => ['50', '12'],
        self::DOC_TYPE_TRANSMITTED => ['03'],
        self::DOC_TYPE_ADOPTED => ['48'],
    ];

    public function __construct(Model $flwb, Import $import, array $data)
    {
        $this->flwb = $flwb;
        $this->logger = $import->getLogger();

        $this->source['show_nl'] = $flwb->getSource()['show_nl'] ?? null;
        $this->source['show_fr'] = $flwb->getSource()['show_fr'] ?? null;
        $this->source['show_de'] = $flwb->getSource()['show_de'] ?? null;
        $this->source['show_en'] = $flwb->getSource()['show_en'] ?? null;
        $this->process($data);
    }

    public function getSource(): array
    {
        $source = $this->source;
        ksort($source);
        return \array_filter($source, [Model::class, 'arrayFilterFunction']);
    }

    public function isMainDocChamber(): bool
    {
        return (isset($this->source['number_doc']) && $this->source['number_doc'] === '1') && (isset($this->source['owner_code']) && $this->source['owner_code'] === 'K');
    }

    public function getDocType(): ?string
    {
        return $this->source['doc_type'] ?? null;
    }

    public function getDocTypes(): array
    {
        return isset($this->source['doc_type']) ? array_filter(array_merge([$this->source['doc_type']], $this->subDocTypes), [Model::class, 'arrayFilterFunction']) : [];
    }

    public static function makeDocType(string $code, string $label): ?string
    {
        foreach (self::DOC_TYPES as $key => $codes) {
            if (in_array($code, $codes, true)) {
                return $key;
            }
        }

        if (\stripos('ADOPTE', $label) !== false) {
            return 'flwb_doc_type.text_adopted';
        }

        return null;
    }

    protected function getRootElements(): array
    {
        return [
            'BICAM_SDOCNAME', 'DOCNR', 'MAIN_VOLGNR', 'LEGISL', 'SESSION',
            'OWNER', 'DEPOTDAT', 'MAINDOC_TYPE', 'DISTRIBUTION_DATE',
            'ENVOI', 'VOTE', 'CONSID', 'CADUC', 'COMMENTS',
            'MAINDOC_JOINTDOCS', 'AUTEURM', 'SUBDOCS', 'EXCADUC', 'MAIN_PDFDOC'
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (int $value) { return $value; };
        $stringCallback = function (string $value) { return $value; };
        $dateCallback = function (string $value) { return Model::createDate($value); };

        return [
            'BICAM_SDOCNAME' => ['source', 'sdocname', $stringCallback],
            'MAIN_VOLGNR' => ['source', 'number_doc', $stringCallback],
            'LEGISL' => ['source', 'legislature', $intCallback],
            'SESSION' => ['source', 'session', $stringCallback],
            'DEPOTDAT' => ['source', 'date_submission', $dateCallback],
            'DISTRIBUTION_DATE' => ['source', 'date_distribution', $dateCallback],
            'ENVOI' => ['source', 'date_send', $dateCallback],
            'CONSID' => ['source', 'date_consideration', $dateCallback],
            'CADUC' => ['source', 'date_expiry', $dateCallback],
        ];
    }

    protected function parseDOCNR(string $value): void
    {
        $this->source['doc_name'] = $value;
        $this->source['file'] = $this->flwb->createFileInfo($value);
    }

    protected function parseMAIN_PDFDOC(array $value): void
    {
        if($value['DOCLINK'] === ''){
            return;
        }
        $this->source['main_pdfdoc']['doc_link'] = $value['DOCLINK'];
        if(isset($value['DOCDATE']) && $value['DOCDATE'] !== ''){
            $this->source['main_pdfdoc']['doc_date'] = $value['DOCDATE'];
        }
        $this->source['main_pdfdoc']['film'] = $value['FILM'];
        $this->source['main_pdfdoc']['blip'] = $value['BLIP'];
    }

    protected function parseOWNER(array $value): void
    {

        if (is_array($value['OWNER_KODE'])) {
            $this->source['owner_code'] = $value['OWNER_KODE']['OWNER'];
            return;
        }

        if (!\array_key_exists($value['OWNER_KODE'], self::TYPES_OWNER)) {
            $this->logger->warning('unknown owner type [{type}]', ['type' => \implode(', ', $value)]);
        }

        $this->source['owner_code'] = $value['OWNER_KODE'];

    }

    protected function parseMAINDOC_TYPE(array $value): void
    {
        $this->source['doc_type'] = self::makeDocType($value['MAINDOC_TYPE_KODE'], $value['MAINDOC_TYPE_KODE_textF']);
    }

    protected function parseVOTE(array $value): void
    {
        if (isset($value['VOTE_DATE'])) {
            $this->source['date_vote'] = Model::createDate($value['VOTE_DATE']);
        }

        $this->source['vote_type_fr'] = $value['VOTE_TYPE']['VOTE_TYPE_FR'] ?? null;
        $this->source['vote_type_nl'] = $value['VOTE_TYPE']['VOTE_TYPE_NL'] ?? null;
    }

    protected function parseCOMMENTS(array $value): void
    {
        if (!empty($value['COMMENTS_textF']) && $value['COMMENTS_textF'] != ' ') {
            $this->source['comments_fr'] = $value['COMMENTS_textF'];
        }
        if (!empty($value['COMMENTS_textN']) && $value['COMMENTS_textN'] != ' ') {
            $this->source['comments_nl'] = $value['COMMENTS_textN'];
        }
    }

    protected function parseMAINDOC_JOINTDOCS($value): void
    {
        $mainDoc = $value['MAINDOC_JOINTDOC'];
        $nested = isset($mainDoc['MAINDOC_JOINTDOC_NRJ']) ? [$mainDoc] : $mainDoc;

        foreach ($nested as $item) {
            if (empty($item['MAINDOC_JOINTDOC_TYPE_textF'] || empty($item['MAINDOC_JOINTDOC_TYPE_textN']))){
                continue;
            }
            $this->source['join_docs'][] = array_merge([
                'doc_type_fr' => $item['MAINDOC_JOINTDOC_TYPE_textF'],
                'doc_type_nl' => $item['MAINDOC_JOINTDOC_TYPE_textN'],
            ], $this->flwb->createFileInfo($item['MAINDOC_JOINTDOC_NRJ']));
        }
    }

    protected function parseAUTEURM(array $value): void
    {
        $nested = isset($value['AUTEURM_SLEUTEL']) ? [$value] : $value;

        foreach ($nested as $item) {
            if (isset($item['AUTEURM_FAMNAAM']) && $item['AUTEURM_FAMNAAM'] === 'NOT FOUND') {
                continue;
            }
            if (isset($item['AUTEURM_SLEUTEL']) && $item['AUTEURM_SLEUTEL'] === '000000') {
                continue;
            }
            $type = $item['AUTEURM_TYPE'];

            if (!\array_key_exists($type['AUTEURM_TYPE_KODE'], self::TYPES_AUTHORS)) {
                $this->logger->warning('unknown author type [{type}]', ['type' => \json_encode($type)]);
            }

            $this->source['authors'][] = [
                'type' => $type['AUTEURM_TYPE_KODE'],
                'actor' => $this->flwb->getActor(
                    'flwb_author',
                    $item['AUTEURM_SLEUTEL'],
                    ($item['AUTEURM_FORNAAM'] ?? ''),
                    ($item['AUTEURM_FAMNAAM'] ?? ''),
                    ($item['AUTEURM_PARTY'] ?? null)
                ),
            ];
        }
    }

    protected function parseSUBDOCS(array $value): void
    {
        $nested = isset($value['SUBDOC']['SUBDOCNR']) ? [$value['SUBDOC']] : $value['SUBDOC'];

        foreach ($nested as $data) {
            if (isset($data['SUB_PDFDOC']['SUB_PDFDOC_DOCLINK']) &&
                ($data['SUB_PDFDOC']['SUB_PDFDOC_DOCLINK'] === 'NONE' || $data['SUB_PDFDOC']['SUB_PDFDOC_DOCLINK'] === '')){
                continue;
            }
            $subDoc = new FLWBDocSub($this->flwb, $this->logger, $data);
            $this->source['sub_docs'][] = $subDoc->getSource();

            if (null !== $subDocType = $subDoc->getDocType()) {
                $this->subDocTypes[] = $subDocType;
            }
        }
    }
}
