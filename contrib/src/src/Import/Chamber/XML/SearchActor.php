<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;

class SearchActor
{
    /** @var Import */
    private $import;
    private $emsLinks = [];
    private $types = [];
    public $cache = [];

    public function __construct(Import $import)
    {
        $this->import = $import;
    }

    public function getEmsLinks(): array
    {
        $emsLinks = \array_values(\array_unique($this->emsLinks));

        $this->emsLinks = []; //clear for the next document

        return $emsLinks;
    }

    public function getTypes(): array
    {
        $types = \array_values($this->types);

        $this->types = []; //clear for the next document;

        return $types;
    }

    public function getFilteredTypes(array $authors): array
    {
        $filteredActorTypes = [];
        foreach ($authors as $author) {
            if (is_array($author) && isset($author['actor']) && isset($this->types[$author['actor']])) {
                $filteredActorTypes[] = $this->types[$author['actor']];
            }
            elseif (is_string($author) && isset($this->types[$author])) {
                $filteredActorTypes[] = $this->types[$author];
            }
        }

        return $filteredActorTypes;
    }

    public function get(int $legislature, string $type, $ksegna = null, ?string $fullName = null, ?string $party = null): string
    {
        $cacheKey = \implode('', func_get_args());
        $actor = $fullName . ($party && !in_array($party, [
            'XXX',
            'YYY',
            'ZZZ',
        ]) ? sprintf(' (%s)', $party) : '');

        if (isset($this->cache[$cacheKey])) {
            $this->addType($type, $cacheKey, $actor, $party);
            $this->emsLinks[] = $this->cache[$cacheKey];
            return $this->cache[$cacheKey];
        }

        $result = $this->search($ksegna, $fullName);
        $total = $result['hits']['total'];

        if ($total === 1) {
            return $this->result($cacheKey, $type, $actor, $party, $result['hits']['hits'][0]);
        } elseif ($total > 1) {
            $retry = $this->search($ksegna, $fullName, $legislature);

            if ($retry['hits']['total'] === 1) {
                return $this->result($cacheKey, $type, $actor, $party, $retry['hits']['hits'][0]);
            }
        }

        return $this->result($cacheKey, $type, $actor, $party);
    }

    private function result(string $cacheKey, string $type, string $actor, ?string $party, array $result = []): string
    {
        $value = $actor;
        if (isset($result['_id'])) {
            $value = sprintf('actr:%s', $result['_id']);
            $this->emsLinks[] = $value;
        }

        $this->cache[$cacheKey] = $value;
        $this->addType($type, $cacheKey, \trim($actor), $party);

        return $this->cache[$cacheKey];
    }

    private function addType(string $type, string $cacheKey, string $label, ?string $party)
    {
        $emsLink = $this->cache[$cacheKey];

        if (substr($emsLink, 0, 4) !== 'actr') {
            return;
        }

        $this->types[$emsLink]['actor'] = $emsLink;
        $this->types[$emsLink]['label'] = $label;
        $this->types[$emsLink]['types'] = \array_values(\array_unique(\array_merge(($this->types[$emsLink]['types'] ?? []), [$type])));
    }

    private function search(string $ksegna = null, string $fullName = null, int $legislature = null): array
    {
        $bool = ['minimum_should_match' => 1];
        $bool['should'] = [
            [ 'term' => ['id_ksegna' => $ksegna]],
            ['term' => ['full_name.keyword' => $fullName]]
        ];

        if ($legislature) {
            $bool['must'] = [['term' => ['legislature' => $legislature]]];
        }

        return $this->import->search(['_source' => ['full_name'], 'query' => ['bool' => $bool]]);
    }
}