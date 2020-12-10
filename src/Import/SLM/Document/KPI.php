<?php

namespace App\Import\SLM\Document;

use EMS\CommonBundle\Elasticsearch\Document;

class KPI extends Document
{
    /** @var string */
    private $slaId;
    /** @var Data[] */
    private $data = [];

    /** @var Child */
    private $type;
    /** @var Child|null */
    private $condition;
    /** @var Child|null */
    private $serviceWindow;

    public function __construct(array $row, int $year)
    {
        $this->slaId = SLA::createID($row['sla_id']);
        $this->setKPIData($row['kpi_id'], $row['kpi_type'], $year, $row);

        $this->type = Child::create('type', $row['kpi_type']);
        $this->condition = Child::create('condition', $row['kpi_threshold'] ?? null);
        $this->serviceWindow = Child::create('service_window', $row['doc_service_window'] ?? null);

        parent::__construct([
            '_id' => self::createID($row['kpi_id']),
            '_type' => 'kpi',
            '_source' => array_filter([
                '_contenttype' => 'kpi',
                'averages' => $this->calculateAverages(),
                'condition' => $this->condition ? $this->condition->getEMSId() : null,
                'kpi_id' => $row['kpi_id'],
                'kpi_type' => $this->type->getEMSId(),
                'label' =>  $row['kpi_label'] ?? null,
                'title' =>  \implode(' - ', \array_filter([$row['kpi_id'], $row['kpi_label']])),
                'service_window' => $this->serviceWindow ? $this->serviceWindow->getEMSId() : null,
                'sla' => sprintf('sla:%s', $this->slaId),
            ])
        ]);
    }

    /**
     * @return Child[]
     */
    public function getChildren(): array
    {
        return array_filter([
            $this->condition,
            $this->serviceWindow,
            $this->type,
        ]);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public static function createID(string $id)
    {
        return sha1('kpi'.$id);
    }

    public function getSLAId(): string
    {
        return $this->slaId;
    }

    private function calculateAverages(): array
    {
        if (null == $slo = $this->calculateAverage('slo')) {
            return [];
        }

        return array_filter([
            'slo' => $slo,
            'raw' => $this->calculateAverage('b', $slo['percentage']),
            'c1' => $this->calculateAverage('c1', $slo['percentage']),
            'c2' => $this->calculateAverage('c2', $slo['percentage']),
        ]);
    }

    private function calculateAverage(string $type, ?float $sloAverage = null): array
    {
        $values = [];

        foreach ($this->data as $data) {
            $values[] = $data->getPercentage($type);
        }

        $values = array_filter($values);
        $average = $values ? floatval(array_sum($values))/count($values) : null;

        return array_filter([
            'percentage' => $average,
            'class' => $sloAverage && $average ? ($sloAverage > $average ? 'text-danger': 'text-success') : null
        ]);
    }

    private function setKPIData(string $kpiId, string $kpiType, string $year, array $row): void
    {
        $monthsInfo = $this->getMonthsInfo($row);

        foreach ($monthsInfo as $month => $info) {
            $this->data[] = new Data($kpiId, $kpiType, $year, $month, $info);
        }
    }

    private function getMonthsInfo(array $row): array
    {
        $monthsInfo = [];
        $regex = '/^(?<info>[a-z0-9]*)_(\d{4}_)?(?<month>\d{2})$/i';
        $filter = array_filter($row, function ($value) { return $value != null; });

        foreach ($filter as $column => $value) {
            if (preg_match($regex, $column, $match)) {
                $monthsInfo[(int) $match['month']][$match['info']] = $value;
            }
        }

        return $monthsInfo;
    }
}