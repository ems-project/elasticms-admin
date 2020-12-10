<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\CollectionInterface;
use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use EMS\CoreBundle\Service\AssetExtractorService;
use Symfony\Component\Finder\SplFileInfo;

class PCRA implements CollectionInterface
{
    use XML;
    /** @var Model[] */
    private $collection = [];
    /** @var AssetExtractorService */
    private $extractorService;

    public function __construct(SplFileInfo $file, Import $import, AssetExtractorService $extractorService)
    {
        $data = $this->xmlToArray($file);
        $legislature = $data['@leg'];

        if  ($import->existLegislature($legislature)) {
            foreach ($data['MEETING'] as $meeting) {
                $this->collection[] = new Report($import, Model::TYPE_PCRA, SearchCategories::CAT_PCRA, $legislature, $meeting, $extractorService);
            }
            $import->getLogger()->debug('Imported {filename}', ['filename' => $file->getFilename()]);
        }
    }

    public function getCollection() : array
    {
        return $this->collection;
    }
}
