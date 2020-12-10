<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Model;
use EMS\CommonBundle\Elasticsearch\DocumentInterface;

class Child implements DocumentInterface
{
    /** @var string */
    private $id;
    /** @var string */
    private $type;
    /** @var array */
    private $source;

    private function __construct(string $id, string $type, array $source)
    {
        $this->id = $id;
        $this->type = $type;
        $this->source = $source;
    }

    public function __toString()
    {
        return $this->id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getEmsId(): string
    {
        return ($this->type . ':' . $this->id);
    }

    public function getSource(): array
    {
        ksort($this->source);

        return array_filter($this->source, [Model::class, 'arrayFilterFunction']);
    }

    public static function createDepartmentINQO(string $titleNl, string $titleFr): Child
    {
        return new self(sha1('dep_inqo_' . strtolower($titleNl . $titleFr)), 'department_inqo', [
            'last_import' => date('c'),
            'title_fr' => $titleFr,
            'title_nl' => $titleNl,
            'show_nl' => true,
            'show_fr' => true,
            'show_de' => false,
            'show_en' => false,
        ]);
    }

    public static function createDepartmentQRVA(array $data): Child
    {

        return new self(sha1('dep_qrva_' . strtolower(($data['title_short_fr'] ?? $data['title_fr']) . ($data['title_short_nl'] ?? $data['title_nl']))), 'department_qrva', \array_filter([
            //'id' => $data['id'],
            'last_import' => date('c'),
            'pres' => $data['pres'] ?? null,
            'title_fr' => $data['title_fr'],
            'title_nl' => $data['title_nl'],
            'title_short_fr' => $data['title_short_fr'] ?? $data['title_fr'],
            'title_short_nl' => $data['title_short_nl'] ?? $data['title_nl'],
            'show_nl' => true,
            'show_fr' => true,
            'show_de' => false,
            'show_en' => false,
        ], [Model::class, 'arrayFilterFunction']));
    }

    public static function createFLWBPdf(string $idFlwb, FLWBPdf $FLWBPdf, array $source, array $searchActorsTypes): Child
    {

        $getActors = function($actor) {
            return $actor['actor'] ?? null;
        };

        $document = \array_merge([
            'last_import' => date('c'),
            'search_id' => $FLWBPdf->getId(),
            'parent' => $idFlwb,
            'all_fr' => $FLWBPdf->getContent(),
            'all_nl' => $FLWBPdf->getContent(),
            'title_fr' => $source['sdocname'] ?? $FLWBPdf->getId(),
            'title_nl' => $source['sdocname'] ?? $FLWBPdf->getId(),
            'hash' => $FLWBPdf->getHash(),
            'show_nl' => true,
            'show_fr' => true,
            'show_de' => false,
            'show_en' => false,
            'search_types' => $FLWBPdf->getSearchTypes(),
            'search_type' => FLWBPdf::SEARCH_TYPE_FLWB_PDF,
            'file' => [
                'label' => $FLWBPdf->getFileLabel(),
                'filename' => $FLWBPdf->getFileName(),
                'path' => $FLWBPdf->getFilePath()
            ],
            'search_date_sort' => $source['date_distribution'] ?? $source['date_consideration'] ?? $source['date_send'] ?? $source['date_submission'] ?? null,
            'search_actors' => \array_filter(\array_map($getActors, $source['authors'] ?? []), [Model::class, 'arrayFilterFunction']),
            'search_actors_types' => $searchActorsTypes,
            'search_dates' => array_values(\array_filter([
                $source['date_distribution'] ?? null,
                $source['date_consideration'] ?? null,
                $source['date_send'] ?? null,
                $source['date_submission'] ?? null])),
        ], $source);

        return new self(sha1('flwb_pdf_' . $FLWBPdf->getId()), 'flwb_pdf', \array_filter($document, [Model::class, 'arrayFilterFunction']));
    }

    public static function createMOTI(MOTI $moti, array $searchActorsTypes): Child
    {
        return new self(sha1('inqo_moti_' . $moti->getId()), 'inqo_moti', \array_filter([
            'last_import' => date('c'),
            'id' => $moti->getId(),
            'search_id' => $moti->getId(),
            'all_fr' => $moti->getContent(),
            'all_nl' => $moti->getContent(),
            'hash' => $moti->getHash(),
            'title_fr' => $moti->getTitleFr(),
            'title_nl' => $moti->getTitleNl(),
            'show_nl' => true,
            'show_fr' => true,
            'show_de' => false,
            'show_en' => false,
            'search_types' => $moti->getSearchTypes(),
            'search_type' => MOTI::SEARCH_TYPE_INQO_MOTI,
            'file' => [
                'label' => $moti->getFileLabel(),
                'filename' => $moti->getFileName(),
                'path' => $moti->getFilePath()
            ],
            'search_date_sort' => $moti->getSearchDateSort(),
            'search_actors' => $moti->getActors(),
            'search_actors_types' => $searchActorsTypes,
            'search_dates' => array_values(\array_filter([
                $moti->getDatePublication(),
                $moti->getDateEnd() ?? null,
                $moti->getDateCommunication() ?? null]))
        ], [Model::class, 'arrayFilterFunction']));
    }


    public static function createKeyword(array $data): Child
    {
        $data['last_import'] = date('c');
        return new self(sha1('keyword_' . $data['title_fr'] . $data['title_nl']), 'keyword', \array_filter($data, [Model::class, 'arrayFilterFunction']));
    }
}
