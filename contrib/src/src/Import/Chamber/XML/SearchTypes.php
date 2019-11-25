<?php

namespace App\Import\Chamber\XML;

class SearchTypes
{
    private $types = [];

    public static function single($type, int $legislature): array
    {
        $types = is_array($type) ? $type : [$type];

        return [
            'legislature' => $legislature,
            'types' => [$types],
        ];
    }

    public function getTypes(): array
    {
        $this->mergeGlobal();

        return \array_values($this->types);
    }

    public function hasTypes(): bool
    {
        return $this->types != null;
    }

    public function addTypes(array $types, string $legislature = null): void
    {
        $key = $legislature === null ? 'global' : $legislature;

        if (isset($this->types[$key]['types'])) {
            $this->types[$key]['types'] = $this->mergeTypes($this->types[$key]['types'], $types);
        } else {
            $this->types[$key] = array_filter(['legislature' => $legislature, 'types' => $types]);
        }
    }

    /**
     * Add global types to all other legislatures
     */
    private function mergeGlobal(): void
    {
        if (!isset($this->types['global'])) {
            return;
        }

        $global = $this->types['global'];

        foreach ($this->types as $legislature => &$type) {
            if ($legislature === 'all') {
                continue;
            }

            $type['types'] = $this->mergeTypes($type['types'], $global['types']);
        }
    }

    private function mergeTypes(array $current, array $merge): array
    {
        return \array_values(array_unique(array_merge($current, $merge)));
    }
}