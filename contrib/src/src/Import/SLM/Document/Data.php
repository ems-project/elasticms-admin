<?php

namespace App\Import\SLM\Document;

use EMS\CommonBundle\Elasticsearch\Document;

class Data extends Document
{
    private $slo = [];
    private $b   = [];
    private $c1  = [];
    private $c2  = [];

    private $endToEnd = [];
    private $result   = [];

    const TYPE_AVAILABLE = 'beschikbaarheid';

    public function __construct(string $kpiId, string $kpiType, int $year, int $month, array $info)
    {
        $id = null;

        foreach ($info as $type => $value) {
            $this->{$type} = $this->sanitizeInfo($value);
        }

        $this->result = $this->calculate(true, true);
        if (strtolower($kpiType) === self::TYPE_AVAILABLE) {
            $this->endToEnd = $this->calculate(true, false);
        }

        parent::__construct([
            '_id' => sha1('data'.$kpiId.$year.$month),
            '_type' => 'data',
            '_source' => array_filter([
                '_contenttype' => 'data',
                'month'        => $month.'/'.$year,
                'kpi'          => sprintf('kpi:%s', KPI::createID($kpiId)),
                'slo'          => $this->slo,
                'value_raw'    => $this->b,
                'value_c1'     => $this->c1,
                'value_c2'     => $this->c2,
                'end_to_end'   => $this->endToEnd,
                'result'       => $this->result
            ])
        ]);
    }

    public function getPercentage(string $type): ?float
    {
        if ($this->{$type} == null || !isset($this->{$type}['percentage'])) {
            return null;
        }

        return $this->{$type}['percentage'];
    }

    private function sanitizeInfo(string $value): array
    {
        if (!strpos($value, '%')) {
            return ['text' => $value];
        }

        $cleanVal = trim(str_replace(',', '.', $value));

        return ['percentage' => floatval(substr($cleanVal, 0, -1))];
    }

    private function calculate($c1 = false, $c2 = false): array
    {
        if (null == $this->b) {
            return [];
        }

        $calculation = $c2 && $this->c2 != null ? $this->c2 : ($c1 && $this->c1 != null ? $this->c1 : $this->b);

        if (isset($calculation['percentage'])) {
            $calculation['class'] = ($calculation['percentage'] >= $this->getPercentage('slo') ? 'text-success': 'text-danger');
        }

        return $calculation;
    }
}