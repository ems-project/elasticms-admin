<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\CollectionInterface;
use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use Symfony\Component\Finder\SplFileInfo;

class CCRI implements CollectionInterface
{
    use XML;
    /** @var Model[] */
    private $collection = [];

    public function __construct(SplFileInfo $file, Import $import)
    {
        $data = $this->xmlToArray($file);
        $legislature = $data['@leg'];

        if  ($import->existLegislature($legislature)) {
            foreach ($data['MEETING'] as $meeting) {
                $this->collection[] = new Report($import, Model::TYPE_CCRI, SearchCategories::CAT_CCRI, $legislature, $meeting);
            }
            $import->getLogger()->debug('Imported {filename}', ['filename' => $file->getFilename()]);
        }
    }

    public function getCollection() : array
    {
        return $this->collection;
    }
}
