<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use EMS\CoreBundle\Elasticsearch\ParentDocument;
use Symfony\Component\Finder\SplFileInfo;

class ORGN extends Model implements ParentDocument
{
    private $children = [];
    private $data = [];

    use XML {
        clean as xmlClean;
    }

    public function __construct(SplFileInfo $file, Import $import)
    {
        $data = $this->xmlToArray($file);
        $processData = $data['items']['orgn:organ'] ?? $data;
        $this->process($processData);

        $orgnCode = new ORGNCode($this->data['code'], $this->data['pathFr'], $this->data['pathNl']);
        $this->source['code_orgn'] = $orgnCode->getCode();
        $this->source['legislature'] = $orgnCode->getLegislature();
        $this->source['type_orgn'] = $orgnCode->getType();
        $this->source['doc_name'] = $orgnCode->getLast();

        parent::__construct($import, Model::TYPE_ORGN, $this->source['id_orgn']);

        if ($this->source['type_orgn'] === 'commission') {
            $this->source['commission_group_id'] = $orgnCode->getGroup();
            $this->source['commission_group_nl'] = $orgnCode->getTranslation('group', 'nl');
            $this->source['commission_group_fr'] = $orgnCode->getTranslation('group', 'fr');
        }

        if ($this->source['type_orgn'] === 'political_group') {
            $this->children[] = new ORGNParty($import, $this);
        }

        if ($this->isValid()) {
            $this->setSearch($import);
        }
    }

    public function isValid(): bool
    {
        return $this->import->existLegislature($this->source['legislature']);
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    protected function clean($value, $key): bool
    {
        if(\in_array($key, ['actr:children', 'orgn:memberships'], true) && !\is_array($value)) {
            return true;
        }

        return $this->xmlClean($value, $key);
    }

    protected function getRootElements(): array
    {
        return [
            '@code', '@id', '@FR', '@NL',
            '@fullNameFR', '@fullNameNL',
            'orgn:link',
            'orgn:description_NL',
            'orgn:description_FR',
            'orgn:parentSummary',
            'orgn:memberships',
            'actr:children',
        ];
    }

    protected function getCallbacks(): array
    {
        $intCallback = function (int $value) { return $value; };
        $stringCallback = function (string $value) { return \trim($value); };

        return [
            '@id' => ['source', 'id_orgn', $intCallback],
            '@FR' => ['source', 'title_fr', $stringCallback],
            '@NL' => ['source', 'title_nl', $stringCallback],
            'orgn:description_FR' => ['source', 'description_fr', $stringCallback],
            'orgn:description_NL' => ['source', 'description_nl', $stringCallback],
            '@code' => ['data', 'code', $stringCallback],
            '@fullNameFR' => ['data', 'pathFr', $stringCallback],
            '@fullNameNL' => ['data', 'pathNl', $stringCallback],
        ];
    }

    protected function parseActrchildren($value): void
    {
        $nested = isset($value['orgn:childSummary']['@id']) ? [$value['orgn:childSummary']] : $value['orgn:childSummary'];
        $children = [];

        foreach ($nested as $child) {
            $path = $explode = \explode('.', $child['@code']);
            $last = \array_pop($path);

            $children[$last] = self::createEmsId(Model::TYPE_ORGN, $child['@id']);
        }

        \ksort($children); //children sorted by number
        $this->source['children'] = \array_values($children);
    }

    protected function parseOrgnparentsummary(array $value): void
    {
        $parentCode = new ORGNCode($value['@code'], $value['@fullNameFR'], $value['@fullNameNL']);

        if ($parentCode->isValid()) {
            $this->source['parent'] = self::createEmsId(Model::TYPE_ORGN, $value['@id']);
        }
    }

    protected function parseOrgnmemberships(array $value)
    {
        $nested = isset($value['actr:role']['@id']) ? [$value['actr:role']] : $value['actr:role'];

        $activeRoles = false;
        $roles = [];

        foreach ($nested as $role) {
            if (false === $activeRoles && $role['@status'] === 'active') {
                $activeRoles = true;
            }

            $roles[] = self::createEmsId(Model::TYPE_ACTR_ROLE, $role['@id']);
        }

        $this->source['active_roles'] = $activeRoles;
        $this->source['roles'] = $roles;
    }

    private function setSearch(Import $import)
    {
        if (isset($this->source['type_orgn']) && $this->source['type_orgn'] === 'commission') {
            $dates = $import->getLegislatureDates([$this->source['legislature']]);

            $this->source['search_id'] = $this->source['id_orgn'];
            $this->source['search_type'] = 'commission';
            $this->source['search_types'] = SearchTypes::single(SearchCategories::CAT_COMMISSION, $this->source['legislature']);
            $this->source['search_dates'] = $dates;
            $this->source['search_date_sort'] = $dates[0];
        }
    }
}