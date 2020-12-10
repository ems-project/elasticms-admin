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
 * INQO (NL: Mondelinge vragen in de commissie, FR: Questions orales en commission, EN: Oral Question)
 */
class INQO extends Model implements ParentDocument
{
    use XML;

    protected $children = [];
    protected $annals = [];

    /** @var Keywords */
    protected $keywords;
    protected $keywordsData = [];
    protected $keywordsMainData = [];
    protected $keywordsCandidateData = [];
    protected $keywordsFreeData = [];

    private $type;
    private $fileMoti;
    private $docTypes = [];
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

        $this->process($this->xmlToArray($file), $this->xmlToDom($file));
        $keywords = new Keywords();
        $this->source['keywords_fr'] = [];
        $this->source['keywords_nl'] = [];

        $keywords->add(Keywords::TYPE_KEYWORDS, $this->keywordsData);
        $keywords->add(Keywords::TYPE_IMPORTANT, $this->keywordsMainData);
        $keywords->add(Keywords::TYPE_CANDIDATE, $this->keywordsCandidateData);
        $keywords->add(Keywords::TYPE_FREE, $this->keywordsFreeData);
        $keywords->deduplicateMainKeywords();
        $this->keywords = $keywords;

        $this->setType();
        $this->setFiles();
        $this->setSearch($import->getLegislature($this->source['legislature']));


        $output_array = [];
        if (preg_match('/^[A-Z](?P<legislature>[0-9]{2})[A-Z]?(?P<identifier>[0-9]*)[A-Z]?$/', \trim($this->source['id_inqo']), $output_array)) {
            $identifier = $output_array['identifier'];
        }
        else {
            $identifier = substr($this->source['id_inqo'], -5);
        }

        $this->source['id_inqo_short'] = $identifier;
        $this->source['id_number'] = intval($identifier);

        parent::__construct($import, Model::TYPE_INQO, $this->source['legislature'].$this->source['id_inqo']);
    }

    public function getSource(): array
    {
        $this->source = array_filter(array_merge($this->source, $this->keywords->getSource()), [Model::class, 'arrayFilterFunction']);
        return parent::getSource();
    }

    public function getChildren(): array
    {
        return array_filter(array_merge(
            $this->children,
            $this->keywords->all()
        ), [Model::class, 'arrayFilterFunction']);
    }

    protected function clean($value, $key): bool
    {
        if ($key === 'DOSSIERNR' && 0 === (int)$value) {
            return true;
        }

        return null === $value;
    }

    protected function getRootElements(): array
    {
        return [
            'SDOCNAME', 'IDENT', 'DOCNUM', 'LEGISL', 'DOSSIERNR', 'SESSION', 'TITF', 'TITN', 'AUT', 'REUNION', 'VERGAD',
            'DISCUSS1', 'DISCUSS2', 'MINASK', 'MINANSW', 'ANNALS', 'ANNALS2', 'THESAF', 'THESAN', 'THESAD', 'INQOJOINTKEYS',
            'FREEF', 'FREEN', 'REMARK', 'CANDF', 'CANDN', 'RESULT', 'BUDGET', 'SITUF', 'SITUN', 'JOINT', 'REQF', 'REQN',
            'DEPOTDAT', 'COMMDAT', 'ENDDAT', 'MOTILIST', 'DOCMOTI',
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (int $value) { return $value; };
        $numberCaseCallback = function (string $value) {
            $output_array = [];
            if (preg_match('/^[0-9]{2}(?P<ID>[0-9]{6})CJ$/', $value, $output_array)) {
                return intval($output_array['ID']);
            };
            return intval($value);
        };
        $stringCallback = function (string $value) { return $value; };
        $dateCallback = function (string $value) {
            return (\DateTime::createFromFormat('Ymd', $value))->format('Y-m-d');
        };

        $keywordCallback = function ($value) { return !is_array($value) ? [$value] : $value; };

        return [
            'LEGISL' => ['source', 'legislature', $intCallback],
            'SDOCNAME' => ['source', 'sdoc_name', $stringCallback],
            'IDENT' => ['source', 'id_inqo', $stringCallback],
            'DOCNUM' => ['source', 'number_doc', $stringCallback],
            'SESSION' => ['source', 'number_session', $intCallback],

            'DOSSIERNR' => ['source', 'number_case', $numberCaseCallback],

            'DEPOTDAT' => ['source', 'date_publication', $dateCallback],
            'COMMDAT' => ['source', 'date_communication', $dateCallback],
            'ENDDAT' => ['source', 'date_end', $dateCallback],
            'DISCUSS1' => ['source', 'date_discussion1', $dateCallback],
            'DISCUSS2' => ['source', 'date_discussion2', $dateCallback],

            'CANDF' => ['keywordsCandidateData', 'fr', $keywordCallback],
            'CANDN' => ['keywordsCandidateData', 'nl', $keywordCallback],
            'FREEF' => ['keywordsFreeData', 'fr', $keywordCallback],
            'FREEN' => ['keywordsFreeData', 'nl', $keywordCallback],

            'SITUF' => ['source', 'situation_fr', $stringCallback],
            'SITUN' => ['source', 'situation_nl', $stringCallback],
            'REQF' => ['source', 'request_plenary_fr', $stringCallback],
            'REQN' => ['source', 'request_plenary_nl', $stringCallback],

            'REMARK' => ['source', 'remark', $stringCallback],
            'BUDGET' => ['source', 'budget', $stringCallback],
            'RESULT' => ['source', 'result', $stringCallback],
            'ANNALS' => ['annals', 0, $stringCallback],
            'ANNALS2' => ['annals', 1, $stringCallback],
        ];
    }

    protected function getCallbacksHTML(): array
    {
        return [
            'TITF' => ['source', 'title_fr'],
            'TITN' => ['source', 'title_nl'],
            'REUNION' => ['source', 'meeting_name_fr'],
            'VERGAD' => ['source', 'meeting_name_nl'],
        ];
    }

    protected function parseAUT($value): void
    {
        if (\is_array($value)) {
            $value = $value['#text'][0]; //leg 51
        }

        $this->source['actor'] = $this->getActor($value, 'inqo_actor');
    }

    protected function parseDOCMOTI($value): void
    {
        $this->fileMoti = \is_array($value) ? \array_pop($value) : $value;
    }

    protected function parseTHESAF($value): void
    {
        $this->parseKeywords('fr', $value);
    }

    protected function parseTHESAN($value): void
    {
        $this->parseKeywords('nl', $value);
    }

    protected function parseTHESAD($value): void
    {
        $this->parseKeywords('de', $value);
    }

    protected function parseMINASK(array $value): void
    {
        $this->source['interpelated'] = $this->parseMinister($value, 'MASK', 'DASK', 'inqo_interpelated');
    }

    protected function parseMINANSW(array $value): void
    {
        $this->source['responding'] = $this->parseMinister($value, 'MANSW', 'DANS', 'inqo_responding');
    }

    protected function parseINQOJOINTKEYS($value): void
    {
        if (!is_array($value)) {
            return;
        }
        $data = is_array($value['BR']) ? $value['BR'] : [$value['BR']];

        $this->source['inqo_related'] =  array_map(function (string $id) {
            return self::createEmsId(Model::TYPE_INQO, $this->source['legislature'].$id);
        }, $data);
    }

    protected function parseJOINT(string $value): void
    {
        $exploded = explode(',', $value);

        $this->source['inqo_added'] =  array_map(function (string $id) {
            return self::createEmsId(Model::TYPE_INQO, $this->source['legislature'].$id);
        }, $exploded);
    }

    protected function parseMOTILIST(array $value): void
    {
        $motions = [];
        $value = isset($value['MOTI']['MOTI_NUMBER']) ? [$value['MOTI']] : $value['MOTI'] ;

        foreach ($value as $data) {
            $motion = [
                'number' => $data['MOTI_NUMBER'],
                'code' => $data['MOTI_NATURE']['MOTI_NATURE_KEY'],
                'title_fr' => $data['MOTI_NATURE']['MOTI_NATURE_textF'],
                'title_nl' => $data['MOTI_NATURE']['MOTI_NATURE_textN'],
                'authors' => $this->parseMontionAuthorList($data['MOTI_AUTEUR_LIST']),
                'remarks' => $data['MOTI_REMARK'] ?? null,
            ];
            if (isset($data['MOTI_VOTE'])) {
                $vote = array_filter([
                    'vote' => true,
                    'date_vote' => self::createDate($data['MOTI_VOTE']['MOTI_VOTE_DATE']),
                    'vote_positive' => (int) $data['MOTI_VOTE']['MOTI_VOTE_POSITIVE'],
                    'vote_negative' => (int) $data['MOTI_VOTE']['MOTI_VOTE_NEGATIVE'],
                    'vote_neutral' => (int) $data['MOTI_VOTE']['MOTI_VOTE_NEUTRAL'],
                    'vote_total' => (int) $data['MOTI_VOTE']['MOTI_VOTE_TOTAL'],
                ]);
                if (isset($data['MOTI_VOTE']['MOTI_VOTE_RESULT'])) {
                    $result = explode(' / ', $data['MOTI_VOTE']['MOTI_VOTE_RESULT']);
                    $vote['vote_result_fr'] = trim($result[0]);
                    $vote['vote_result_nl'] = trim($result[1]);
                }
                $motion = array_merge($motion, $vote);
            }
            $motions[] = array_filter($motion);
        }

        $this->source['motions'] = $motions;
    }

    private function parseMontionAuthorList(array $data): array
    {
        $data = isset($data['MOTI_AUTEUR']['MOTI_AUTEUR_KEY']) ? [$data['MOTI_AUTEUR']] : $data['MOTI_AUTEUR'] ;

        $list = [];
        foreach ($data as $author) {
            $list[] = $this->getActor(\implode(', ', [
                $author['MOTI_AUTEUR_FORNAME'],
                $author['MOTI_AUTEUR_FAMNAME'],
                $author['MOTI_AUTEUR_GROUP'],
                $author['MOTI_AUTEUR_KEY'],
            ]), 'inqo_montion_author');
        }

        return $list;
    }

    private function parseMinister(array $value, string $m, string $d, string $type): array
    {
        $ministers = [];

        $i = 0;
        while ($i++ < 10) {
            if (!isset($value[$m.$i])) {
                return $ministers;
            }

            $department = Child::createDepartmentINQO($value[$d.'N'.$i], $value[$d.'F'.$i]);
            $ministers[] = [
                'actor' => $this->getActor($value[$m.$i], $type),
                'department' => $department->getEmsId()
            ];
            $this->children[] = $department;
        }

        return $ministers;
    }

    private function parseKeywords(string $locale, $value): void
    {
        if (!is_array($value)) {
            $value = [$value]; //transform if only one is present
        }

        $this->keywordsData[$locale] = $this->extractThesaurus($value);
        $this->keywordsMainData[$locale]  = $this->extractThesaurus($value, true);
    }

    private function extractThesaurus(array $values, bool $important = false): array
    {
        $list = [];
        foreach ($values as $thesaurus) {
            $word = is_string($thesaurus) ? $thesaurus : $thesaurus['#'];
            $importantValue = false;
            if (is_array($thesaurus) && isset($thesaurus['@IMPORTANT'])) {
                $importantValue = $thesaurus['@IMPORTANT'];
            }
            if (is_array($thesaurus) && isset($thesaurus['@important'])) {
                $importantValue = $thesaurus['@important'];
            }
            if ($important && $importantValue !== 'Y') {
                continue;
            }
            $list[] = $word;
        }
        return $list;
    }

    private function getActor(string $raw, string $type): string
    {
        if ($raw === 'Catherine, Doyen-Fonck, cdH, 01204') {
            $raw = \str_replace('01204', '01076', $raw);
        }

        try {
            $explode = \explode(', ', $raw);

            list($firstName, $lastName, $party, $ksegna) = \array_values($explode);
            $fullName = sprintf('%s %s', $firstName, $lastName);

            return $this->import->searchActor->get($this->source['legislature'], $type, $ksegna, $fullName, $party);
        } catch (\Exception $e) {
            return $raw;
        }
    }

    private function setFiles(): void
    {
        $legislature = $this->source['legislature'];
        $numberDoc = $this->source['number_doc'];

        if (isset($this->source['motions'])) {
            if (null === $this->fileMoti && $legislature >= 51) {
                $this->fileMoti = sprintf('%dM%s', $legislature, $numberDoc);
            }

            \preg_match(sprintf('/^%d(?P<type>M|K)(?P<id>.*)/', $legislature), $this->fileMoti, $matches);
            $type = $matches['type'] ?? false;

            if ('K' === $type) {
                $folder = \substr($matches['id'], 0, 4 );
                $this->source['file_motion'] = [
                    'label' => $this->fileMoti,
                    'filename' => \sprintf('%s.pdf', $this->fileMoti),
                    'path' => \sprintf('FLWB/PDF/%d/%s/', $legislature, $folder, $this->fileMoti)
                ];
                $this->docTypes[] = 'MOTI';

                $this->createMotion($this->source['file_motion']);

            } elseif ('M' === $type) {
                $this->source['file_motion'] = [
                    'label' => $this->fileMoti,
                    'filename' => \sprintf('%s001.pdf', $this->fileMoti),
                    'path' => \sprintf('MOTI/pdf/%d/', $legislature, $this->fileMoti)
                ];
                $this->docTypes[] = 'MOTI';

                $this->createMotion($this->source['file_motion']);
            }
        }

        if (null != $this->annals) {
            $unique = [];

            if ($this->type === 'plenary') {
                $path = sprintf('PCRI/pdf/%d/', $legislature);
                $prefix = ($legislature < 50 ? $legislature.'KP' : 'ip');
                $this->docTypes[] = 'PCRI';
            } else {
                $path = sprintf('CCRI/pdf/%d/', $legislature);
                $prefix = ($legislature < 50 ? $legislature.'KC' : 'ic');
                $this->docTypes[] = 'CCRI';
            }

            foreach ($this->annals as $annal) {
                $explode = \explode(', ', $annal);
                $filename = $explode[0];
                $page = isset($explode[1]) ? (int) \substr($explode[1], 2) : null;

                if (\in_array($filename, $unique)) {
                    continue;
                }

                $unique[] = $filename;

                if (\strtolower(\substr($filename, 0, 1)) === 'c') {
                    $onlyNumber = (int) \substr($filename, 1);
                    $number = (\strlen($onlyNumber) == 2 ? '0'.$onlyNumber : $onlyNumber);
                } else {
                    $number = $filename;
                }

                if ($legislature < 50) {
                    $number = \sprintf('%04d', $number);
                }

                $this->source['files_report'][] = [
                    'label' => $filename,
                    'page' => $page,
                    'filename' => \sprintf('%s%s.pdf', $prefix, $number),
                    'path' => $path
                ];
                $this->docTypes[] = 'IC';
            }
        }
    }

    private function setSearch(array $legislature): void
    {
        $this->source['search_id'] = $this->source['id_inqo'];
        $this->source['search_type'] = Model::TYPE_INQO;
        $this->source['search_types'] = SearchTypes::single(SearchCategories::CAT_INQO, $this->source['legislature']);
        $this->source['search_actors'] = $this->import->searchActor->getEmsLinks();
        $this->source['search_actors_types'] = $this->import->searchActor->getTypes();

        $docTypes = \array_values(\array_unique($this->docTypes));
        $this->source['search_doc_types_inqo'] = $docTypes ?? 'empty';

        if (empty($this->source['title_fr'])) {
            $this->source['title_fr'] = $this->source['sdoc_name'];
        }
        if (empty($this->source['title_nl'])) {
            $this->source['title_nl'] = $this->source['sdoc_name'];
        }

        $dates = [
            $this->source['date_discussion1'] ?? null,
            $this->source['date_discussion2'] ?? null,
            $this->source['date_communication'] ?? null,
            $this->source['date_publication'] ?? null,
            $this->source['date_end'] ?? null,
        ];

        $motions = $this->source['motions'] ?? [];
        foreach ($motions as $motion) {
            $dates[] = $motion['date_vote'] ?? null;
        }

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

    private function setType(): void
    {
        $leg = $this->source['legislature'];
        $sdocname = $this->source['sdoc_name'];

        if (\preg_match(sprintf('/^V%d.*/', $leg), $sdocname)) {
            $this->source['types_inqo'][] = 'oral';
        } elseif (\preg_match(sprintf('/^I%d.*/', $leg), $sdocname)) {
            $this->source['types_inqo'][] = 'interpellation';
        }

        $meeting = $this->source['meeting_name_fr'] ?? '';

        if (strpos($meeting, 'PLENIERE') !== false) {
            $this->type = 'plenary';
            $this->source['types_inqo'][] = 'plenary';
        } else {
            $this->type = 'commission';
            $this->source['types_inqo'][] = 'commission';
        }
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

    protected function getFileWithPath(array $source): string
    {
        return $this->import->getRootDir() . '/' . $source['path'] . $source['filename'];
    }
}
