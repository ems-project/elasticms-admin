<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use EMS\CoreBundle\Elasticsearch\ParentDocument;
use EMS\CoreBundle\Service\AssetExtractorService;
use Symfony\Component\Finder\SplFileInfo;

class QRVA extends Model implements ParentDocument
{
    use XML {
        clean as xmlClean;
    }

    protected $departmentData = [];
    protected $publicationData = [];
    protected $answersData = [];

    /** @var Keywords */
    protected $keywords;
    protected $keywordsData = [];
    protected $keywordsMainData = [];
    protected $keywordsCandidateData = [];
    protected $keywordsFreeData = [];

    /** @var Child */
    private $department;
    private $actors = [];
    /** @var AssetExtractorService */
    private $extractorService;

    public function __construct(SplFileInfo $file, Import $import, AssetExtractorService $extractorService)
    {
        $this->extractorService = $extractorService;
        $this->source['id_qrva'] = $file->getBasename('.' . $file->getExtension());

        $this->import = $import;
        $this->process($this->xmlToArray($file), $this->xmlToDom($file));

        $this->source['id_qrva_short'] = substr($this->source['question_number'] ?? $this->source['id_qrva'], -4);
        $this->source['id_number'] = intval($this->source['id_qrva_short']);
        $this->source['question_number'] = $this->source['question_number'] ?? 'NULL';
        $this->source['title_fr'] = $this->source['title_fr'] ?? sprintf('Question');
        $this->source['title_nl'] = $this->source['title_nl'] ?? sprintf('Vraag');

        $this->department = Child::createDepartmentQRVA($this->departmentData);

        $keywords = new Keywords();
        $this->source['keywords_fr'] = [];
        $this->source['keywords_nl'] = [];
        $keywords->add(Keywords::TYPE_KEYWORDS, $this->keywordsData);
        $keywords->add(Keywords::TYPE_IMPORTANT, $this->keywordsMainData);
        $keywords->add(Keywords::TYPE_CANDIDATE, $this->keywordsCandidateData);
        $keywords->add(Keywords::TYPE_FREE, $this->keywordsFreeData);
        $keywords->deduplicateMainKeywords();
        $this->keywords = $keywords;

        if ($this->publicationData) {
            $this->setPublication();
        }
        if ($this->answersData) {
            $this->setAnswers(1);
            $this->setAnswers(2);
            $this->setAnswers(3);
            $this->setAnswers(4);
        }

        $this->setSearch();

        parent::__construct($import, Model::TYPE_QRVA, $this->source['legislature'].$this->source['id_qrva']);
    }

    public function isValid(): bool
    {
        $source = $this->getSource();

        foreach (['question_status', 'question_status_sl', 'question_status_ol'] as $statusField) {
            if (!isset($source[$statusField])) {
                continue;
            }
            if ($source[$statusField] === 'archivedCanceled' || $source[$statusField] === 'canceled') {
                return false;
            }
        }

        return parent::isValid();
    }

    public function getSource(): array
    {
        $this->source = array_filter(array_merge(
            $this->source,
            ['department' => $this->department->getEmsId()],
            $this->keywords->getSource()
        ), [Model::class, 'arrayFilterFunction']);

        return parent::getSource();
    }

    public function getChildren(): array
    {
        return array_filter(array_merge(
            [$this->department],
            $this->keywords->all()
        ));
    }

    protected function clean($value, $key): bool
    {
        if ($value === "") {
            return true;
        }

        return $this->xmlClean($value, $key);
    }

    protected function getRootElements(): array
    {
        return [
            '@xmlns:ns0', '@xmlns:ns1', '@ns0:xsi', '@ns1:noNamespaceSchemaLocation', '@xmlns:xsi', '@xsi:noNamespaceSchemaLocation',
            'ID', 'SDOCNAME', 'DOCNAME','LEGISL', 'AUT', 'QUESTNUM', 'DEPOTDAT',
            'PUBLIDAT', 'STATUSQ', 'STATUS_SL', 'STATUS_OL', 'DELAIDAT',
            'TITF', 'TITN', 'DEPTPRES', 'DEPTNUM', 'DEPTF', 'DEPTN', 'SUBDEPTF', 'SUBDEPTN',
            'PUBLICQ', 'LANG', 'TEXTQF', 'TEXTQN',
            'ANNUL_MOTIF_NL', 'ANNUL_MOTIF_FR', 'ANNUL_MOTIF_ID', 'ANNUL_MOTIF_SHOW',
            'MAIN_THESAF', 'MAIN_THESAN', 'MAIN_THESAD',
            'THESAF', 'THESAN', 'THESAD', 'DESCF', 'DESCN', 'DESCD', 'FREEF', 'FREEN', 'FREED',
            'NUMA1', 'CASA1', 'STATUSA1', 'PUBLICA1', 'TEXTA1F', 'TEXTA1N',
            'NUMA2', 'CASA2', 'STATUSA2', 'PUBLICA2', 'TEXTA2F', 'TEXTA2N',
            'NUMA3', 'CASA3', 'STATUSA3', 'PUBLICA3', 'TEXTA3F', 'TEXTA3N',
            'NUMA4', 'CASA4', 'STATUSA4', 'PUBLICA4', 'TEXTA4F', 'TEXTA4N',
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (int $value) { return $value; };
        $boolCallback = function (bool $value) { return $value; };
        $stringCallback = function (string $value) { return $value; };

        $stringOneLineCallback = function (string $value) {
            return preg_replace('/\s+/', ' ', $value);
        };
        $dateCallback = function (string $value) { return Model::createDate($value); };
        $keywordMainCallback = function (string $value) { return [trim($value)]; };
        $keywordCallback = function ($value) {
            $value = \is_array($value) ? \implode(', ', $value['#text']) : $value;
            return is_string($value) ? preg_split('/(,\s+|\s+\|\s+)/', trim($value)) : null;
        };

        return [
            'SDOCNAME' => ['source', 'sdoc_name', $stringCallback],
            'DOCNAME' => ['source', 'doc_name', $stringCallback],
            'LEGISL' => ['source', 'legislature', $intCallback],
            'LANG' => ['source', 'language', $stringCallback],
            'DEPOTDAT' => ['source', 'date_submission', $dateCallback],
            'DELAIDAT' => ['source', 'date_period', $dateCallback],
            'PUBLIDAT' => ['source', 'date_publication', $dateCallback],

            'QUESTNUM' => ['source', 'question_number', $stringCallback],
            'STATUSQ' => ['source', 'question_status', $stringCallback],
            'STATUS_SL' => ['source', 'question_status_sl', $stringCallback],
            'STATUS_OL' => ['source', 'question_status_ol', $stringCallback],

            'DEPTPRES' => ['departmentData', 'pres', $intCallback],
            'DEPTNUM' => ['departmentData', 'id', $intCallback],
            'PUBLICQ' => ['publicationData', 'question', $stringOneLineCallback],
            'ANNUL_MOTIF_NL' => ['source', 'cancel_nl', $stringCallback],
            'ANNUL_MOTIF_FR' => ['source', 'cancel_fr', $stringCallback],
            'ANNUL_MOTIF_SHOW' => ['source', 'cancel_show', $boolCallback],

            'PUBLICA1' => ['answersData', 'answer_1_publication', $stringOneLineCallback],
            'PUBLICA2' => ['answersData', 'answer_2_publication', $stringOneLineCallback],
            'PUBLICA3' => ['answersData', 'answer_3_publication', $stringOneLineCallback],
            'STATUSA1' => ['answersData', 'answer_1_status', $stringCallback],
            'STATUSA2' => ['answersData', 'answer_2_status', $stringCallback],
            'STATUSA3' => ['answersData', 'answer_3_status', $stringCallback],
            'CASA1' => ['answersData', 'answer_1_type', $stringOneLineCallback],
            'CASA2' => ['answersData', 'answer_2_type', $stringOneLineCallback],
            'CASA3' => ['answersData', 'answer_3_type', $stringOneLineCallback],

            'MAIN_THESAF' => ['keywordsMainData', 'fr', $keywordMainCallback],
            'MAIN_THESAN' => ['keywordsMainData', 'nl', $keywordMainCallback],
            'MAIN_THESAD' => ['keywordsMainData', 'de', $keywordMainCallback],
            'THESAF' => ['keywordsData', 'fr', $keywordCallback],
            'THESAN' => ['keywordsData', 'nl', $keywordCallback],
            'THESAD' => ['keywordsData', 'de', $keywordCallback],
            'DESCF' => ['keywordsCandidateData', 'fr', $keywordCallback],
            'DESCN' => ['keywordsCandidateData', 'nl', $keywordCallback],
            'DESCD' => ['keywordsCandidateData', 'de', $keywordCallback],
            'FREEF' => ['keywordsFreeData', 'fr', $keywordCallback],
            'FREEN' => ['keywordsFreeData', 'nl', $keywordCallback],
            'FREED' => ['keywordsFreeData', 'de', $keywordCallback],
        ];
    }

    protected function getCallbacksHTML(): array
    {
        return [
            'TITF' => ['source', 'title_fr'],
            'TITN' => ['source', 'title_nl'],
            'DEPTN' => ['departmentData', 'title_nl'],
            'DEPTF' => ['departmentData', 'title_fr'],
            'SUBDEPTN' => ['departmentData', 'title_short_nl'],
            'SUBDEPTF' => ['departmentData', 'title_short_fr'],
            'TEXTQN' => ['source', 'text_nl'],
            'TEXTQF' => ['source', 'text_fr'],
            'TEXTA1F' => ['answersData', 'answer_1_fr'],
            'TEXTA1N' => ['answersData', 'answer_1_nl'],
            'TEXTA2F' => ['answersData', 'answer_2_fr'],
            'TEXTA2N' => ['answersData', 'answer_2_nl'],
            'TEXTA3F' => ['answersData', 'answer_3_fr'],
            'TEXTA3N' => ['answersData', 'answer_3_nl'],
        ];
    }

    protected function parseAUT($value): void
    {
        if (\is_array($value)) {
            $value = $value['#text'][0]; //leg 51
        }

        $this->source['actor'] = $this->getActor($value, 'qrva_author');
    }

    private function getActor($raw, $type): string
    {
        $raw = preg_replace('/\s+/', ' ', $raw);
        \preg_match('/^(?<fullname>.*),((?<party>.*)\((?<ksegna>.*)\)|(?<only_party>.*))$/', $raw, $matches);
        $data = array_map('trim', $matches);
        $party = $data['only_party'] ?? ($data['party'] ?? null);

        return $this->import->searchActor->get($this->source['legislature'], $type, $data['ksegna'] ?? '', $data['fullname'] ?? '', $party);
    }

    private function setAnswers(int $number): void
    {
        if (!preg_grep(sprintf('/^answer_%d/', $number), array_keys($this->answersData))) {
            return;
        }

        $publication = $this->getPublication($this->answersData['answer_'.$number.'_publication']);

        $this->source['answers'][] = array_filter(array_merge([
            'type' => $this->answersData['answer_'.$number.'_type'] ?? null,
            'status' => $this->answersData['answer_'.$number.'_status'] ?? null,
            'text_fr' => $this->answersData['answer_'.$number.'_fr'] ?? null,
            'text_nl' => $this->answersData['answer_'.$number.'_nl'] ?? null,
        ], $publication));
    }

    private function setPublication(): void
    {
        $this->source = array_merge($this->source, $this->getPublication($this->publicationData['question']));
    }

    private function getPublication(string $value): array
    {
        $leg = $this->source['legislature'];
        $exploded = \explode(',', $value);

        $id = str_pad(\substr($exploded[0], 1), 4, '0', \STR_PAD_LEFT);
        $page = \trim($exploded[1]) != null ? (int) substr(\trim($exploded[1]), 1) : null;

        $publication = [
            'publication_period' => isset($exploded[3]) ? (int) $exploded[3] : null,
            'publication_file' => array_filter([
                'label' => $exploded[0],
                'page' => $page,
                'filename' => sprintf('%dK%s.pdf', $leg, $id),
                'path' => sprintf('QRVA/pdf/%d/', $leg),
            ]),
        ];

        if (isset($exploded[2])) {
            $stringDate = \substr(\trim($exploded[2]), 0, 10);
            $publication['date_publication'] = self::createDate($stringDate, 'd/m/Y');
        }

        return array_filter($publication);
    }

    private function setSearch(): void
    {
        $this->source['search_id'] = $this->source['question_number'];
        $this->source['search_type'] = Model::TYPE_QRVA;
        $this->source['search_types'] = SearchTypes::single(SearchCategories::CAT_QRVA, $this->source['legislature']);
        $this->source['search_actors'] = $this->import->searchActor->getEmsLinks();
        $this->source['search_actors_types'] = $this->import->searchActor->getTypes();

        $dates = array_filter([
            $this->source['date_submission'] ?? null,
            $this->source['date_publication'] ?? null,
        ]);

        $ansers = $this->source['answers'] ?? [];
        foreach ($ansers as $answer) {
            $dates[] = $answer['date_publication_'] ?? null;
        }

        $dates = \array_filter($dates);
        \sort($dates);

        $this->source['search_dates'] = $dates;
        $this->source['search_date_sort'] = $dates[0];

        if (!$this->keywords->isEmpty()) {
            $this->source['keywords_fr'] = $this->keywords->getKeywordsText('fr');
            $this->source['keywords_nl'] = $this->keywords->getKeywordsText('nl');
            $this->source['search_keywords'] = $this->keywords->getEmsIds();
        }
    }
}
