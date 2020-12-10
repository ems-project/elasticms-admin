<?php

namespace App\Import\Chamber\Elasticsearch;

use App\Import\Chamber\Import;

final class ACTRIndexedMember
{
    /** @var Import */
    private $import;
    /** @var array */
    private $source = [];

    public function __construct(Import $import, string $index, string $id)
    {
        $this->import = $import;
        $actor = $this->getACTRMember($index, $id);

        if (null === $actor) {
            return;
        }

        $this->source = \array_intersect_key(
            $actor['_source'],
            [
                'is_member' => null,
                'id_ksegna' => null,
                'cv' => null,
            ]
        );
    }

    public function isValid(): bool
    {
        return $this->source['is_member'] ?? false;
    }

    public function getSource(): array
    {
        return $this->source;
    }

    private function getACTRMember(string $index, string $id): ?array
    {
        try {
            return $this->import->get($index, $id);
        } catch(\Exception $exception) {
            return null;
        }
    }
}
