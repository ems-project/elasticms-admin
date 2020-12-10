<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Model;

class FLWBCommission
{
    use XML {
        clean as xmlClean;
    }

    protected $source = [];

    /** @var FLWB */
    private $flwb;

    public function __construct($flwb, array $data)
    {
        $this->flwb = $flwb;
        $this->process($data);

        $this->source['show_nl'] = true;
        $this->source['show_fr'] = true;
        $this->source['show_de'] = false;
        $this->source['show_en'] = false;
    }

    public function getSource(): array
    {
        $source = $this->source;

        ksort($source);

        return \array_filter($source, [Model::class, 'arrayFilterFunction']);
    }

    protected function clean($value, $key): bool
    {
        if ($key === 'RAPPORT_KODE' && $value === 'Z') {
            return true;
        }

        if ($key === 'RAPPORTEUR_ACTIVE' && $value === '0') {
            return true;
        }

        if (\in_array($key, ['RAPPORTEUR', 'RAPPORT']) && \array_filter($value, [Model::class, 'arrayFilterFunction']) == null) {
            return true;
        }

        return $this->xmlClean($value, $key);
    }

    protected function getRootElements(): array
    {
        return [
            'SLEUTEL', 'COMMISSIE_TYPE', 'COMMISSIE_NAAM', 'COMMISSIE_CLASS',
            'KALENDER', 'INCIDENTEN', 'RAPPORT', 'RAPPORTEUR', 'REMARK'
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (int $value) { return $value; };

        return [
            'SLEUTEL' => ['source', 'key_commission', $intCallback]
        ];
    }

    protected function parseCOMMISSIE_NAAM(array $value): void
    {
        $this->source['title_fr'] = $value['COMMISSIE_NAAM_textF'];
        $this->source['title_nl'] = $value['COMMISSIE_NAAM_textN'];
    }

    protected function parseCOMMISSIE_TYPE(array $value): void
    {
        $this->source['type'] = $value['COMMISSIE_TYPE_KODE'];
        $this->source['type_fr'] = $value['COMMISSIE_TYPE_textF'];
        $this->source['type_nl'] = $value['COMMISSIE_TYPE_textN'];
    }

    protected function parseCOMMISSIE_CLASS(array $value): void
    {
        $this->source['class'] = $value['COMMISSIE_CLASS_KODE'];
        $this->source['class_fr'] = $value['COMMISSIE_CLASS_textF'] ?? 'Commission';
        $this->source['class_nl'] = $value['COMMISSIE_CLASS_textN'] ?? 'Commissie';
    }

    protected function parseKALENDER(array $value): void
    {
        $nested = isset($value['KALENDER_kode']) ? [$value] : $value;

        foreach ($nested as $item) {
            $this->source['calendar'][] = array_filter([
                'code' => (int) $item['KALENDER_kode'],
                'title_fr' => $item['KALENDER_textF'],
                'title_nl' => $item['KALENDER_textN'],
                'date' => Model::createDate($item['KALENDER_DATUM']),
                'remark' => $item['KALENDER_OPMERKING'] ?? null,
            ], [Model::class, 'arrayFilterFunction']);
        }
    }

    protected function parseINCIDENTEN(array $value): void
    {
        $nested = isset($value['INCIDENT']['INCIDENTEN_DATUM']) ? [$value['INCIDENT']] : $value['INCIDENT'];

        foreach ($nested as $item) {
            $this->source['incidents'][] = array_filter([
                'title_fr' => $item['INCIDENTEN_textF'],
                'title_nl' => $item['INCIDENTEN_textN'],
                'date' => Model::createDate($item['INCIDENTEN_DATUM']),
                'remark' => $item['INCIDENTEN_OPMERKING'] ?? null,
            ], [Model::class, 'arrayFilterFunction']);
        }
    }

    protected function parseRAPPORT(array $value): void
    {
        if (isset($value['RAPPORT_NR'])) {
            $this->source['report'] = \array_merge([
                'date' => isset($value['RAPPORT_DATE']) ? Model::createDate($value['RAPPORT_DATE']) : null,
            ], $this->flwb->createFileInfo($value['RAPPORT_NR']));
        }
        if (isset($value['RAPPORT_ANNEX_NR'])) {
            $this->source['report_annex'] = \array_merge([
                'date' => isset($value['RAPPORT_ANNEX_DATE']) ? Model::createDate($value['RAPPORT_ANNEX_DATE']) : null,
            ], $this->flwb->createFileInfo($value['RAPPORT_ANNEX_NR']));
        }
    }

    protected function parseRAPPORTEUR(array $value): void
    {
        $nested = isset($value['RAPPORTEUR_FORNAAM']) ? [$value] : $value;

        foreach ($nested as $item) {
            $this->source['reporters'][] = $this->flwb->getActor(
                'flwb_reporter',
                $item['RAPPORTEUR_SLEUTEL'],
                $item['RAPPORTEUR_FORNAAM'],
                $item['RAPPORTEUR_FAMNAAM'],
                ($item['RAPPORTEUR_PARTY'] ?? null)
            );
        }
    }
}
