<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\CollectionInterface;
use App\Import\Chamber\Import;
use Symfony\Component\Finder\SplFileInfo;

class ACTRMembers implements CollectionInterface
{
    use XML;

    /** @var Import */
    private $import;
    private $collection = [];

    private $counter = 0;

    public function __construct(SplFileInfo $file, Import $import)
    {
        preg_match('/.*(?<legislature>\d{2}).*/', $file->getFilename(), $matches);

        if (!isset($matches['legislature'])) {
            throw new \LogicException(sprintf('Invalid file name %s', $file->getFilename()));
        }

        $legislature = (int) $matches['legislature'];
        $data = $this->xmlToArray($file);
        $this->import = $import;

        foreach ($data['DEPUTE'] as $depute) {
            try {
                $this->collection[] = new ACTRMember($import, $depute, $legislature);
                $this->counter++;
            } catch (\LogicException $e) {
                $this->import->getLogger()->error($e->getMessage());
            }
        }

        $import->getLogger()->info('{count} actors updated', ['count' => $this->counter]);
    }

    public function getCollection(): array
    {
        return $this->collection;
    }
}