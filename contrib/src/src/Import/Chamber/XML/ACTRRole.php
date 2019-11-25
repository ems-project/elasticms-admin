<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\Model;

/**
 * Example http://data.dekamer.be/v0/actr/1
 */
class ACTRRole extends Model
{
    use XML {
        clean as xmlClean;
    }

    public function __construct(Import $import, array $data, string $emsLinkActr)
    {
        $this->import = $import;
        $this->process($data);
        $this->source['actr'] = $emsLinkActr;

        parent::__construct($import, Model::TYPE_ACTR_ROLE, $this->source['id_actr_role']);
    }

    public function getSearchCategories(bool $notAssigned): ?array
    {
        switch ($this->source['mandate_code']) {
            case 'M':
                $active = $this->source['active'] ?? false;
                $category = [SearchCategories::CAT_ACTR_MEMBER];

                if (!$notAssigned && $active && $this->getLegislature() === $this->import->getActiveLegislatureId() && 'plenary' === $this->getOrgnType()) {
                    $category[] = SearchCategories::CAT_ACTR_ACTIVE_MEMBER;
                }

                return $category;
            case 'H':
                return [SearchCategories::CAT_ACTR_HONORARY];
            case 'S':
                return [SearchCategories::CAT_ACTR_SENATOR];
            case 'F':
                return [SearchCategories::CAT_ACTR_GOVERNMENT];
            default:
                return null;
        }
    }

    public function getOrgn(): ?string
    {
        return $this->source['orgn'] ?? null;
    }

    public function getOrgnType(): ?string
    {
        return $this->source['orgn_type'] ?? null;
    }

    public function isValid(): bool
    {
        if ('H' === $this->source['mandate_code']) {
            return true;
        }

        if (!isset($this->source['legislature']) || $this->source['orgn_root'] == '03') {
            return false;
        }

        return $this->import->existLegislature($this->source['legislature']);
    }

    public function getLegislature(): ?int
    {
        return $this->source['legislature'] ?? null;
    }

    protected function clean($value, $key): bool
    {
        if ($value === '/null') {
            return true;
        }

        if ($key === 'orgn:functionSummary' && empty($value)) {
            return true;
        }

        return $this->xmlClean($value, $key);
    }

    protected function getRootElements(): array
    {
        return [
            '@id', '@mandateCode', '@status',
            'actr:beginDate',
            'actr:endDate',
            'actr:languageCode',
            'orgn:functionSummary',
            'orgn:ouSummary',
            'actr:personSummary',
            'actr:prestationDate',
            'actr:beginSession',
            'actr:endSession',
            'actr:theoreticalEndDate',
            'actr:comment_FR',
            'actr:comment_NL',
            'actr:description_FR',
            'actr:description_NL',
            'actr:finalMotif',
            'actr:rangNr',
            'actr:successorRole'
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (int $value) { return $value; };
        $stringCallback = function (string $value) { return \trim($value); };
        $dateCallback = function (string $value) { return Model::createDate($value); };

        return [
            '@id' => ['source', 'id_actr_role', $intCallback],
            '@mandateCode' => ['source', 'mandate_code', $stringCallback],
            'actr:beginDate' => ['source', 'date_start', $dateCallback],
            'actr:endDate' => ['source', 'date_end', $dateCallback],
            'actr:theoreticalEndDate' => ['source', 'date_theoretical_end', $dateCallback],
            'actr:prestationDate' => ['source', 'date_presentation', $dateCallback]
        ];
    }

    protected function parseStatus(string $value)
    {
        $this->source['active'] = $value == 'active';
    }

    public function parseOrgnfunctionSummary($value)
    {
        $this->source['function_code'] = $value['@code'];
        $this->source['function_code_nl'] = $value['@fullNameNL'];
        $this->source['function_code_fr'] = $value['@fullNameFR'];
    }

    public function parseOrgnouSummary($value)
    {
        $code = new ORGNCode($value['@code'], $value['@fullNameFR'], $value['@fullNameNL']);

        $this->source['legislature'] = $code->getLegislature();
        $this->source['orgn_type'] = $code->getType();
        $this->source['orgn_root'] = $code->getRoot();
        $this->source['orgn'] = self::createEmsId(Model::TYPE_ORGN, $value['@id']);
    }
}