<?php

namespace App\Import\Chamber;

use App\Import\Chamber\XML\ACTR;
use App\Import\Chamber\XML\CCRA;
use App\Import\Chamber\XML\CCRI;
use App\Import\Chamber\XML\FLWB;
use App\Import\Chamber\XML\GENESIS;
use App\Import\Chamber\XML\INQO;
use App\Import\Chamber\XML\PCRA;
use App\Import\Chamber\XML\PCRI;
use App\Import\Chamber\XML\QRVA;
use EMS\CommonBundle\Elasticsearch\DocumentInterface;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Elasticsearch\ParentDocument;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use Symfony\Component\Finder\SplFileInfo;

class ModelFactory
{
    /** @var IndexHelper */
    private $indexHelper;
    /** @var Import */
    private $import;
    /** @var array */
    private $children = [];
    /**@var StorageManager */
    private $storageManager;
    /** @var AssetExtractorService */
    private $extractorService;
    /** @var FileService */
    private $fileService;

    public function __construct(IndexHelper $indexHelper, Import $import, StorageManager $storageManager, AssetExtractorService $extractorService, FileService $fileService)
    {
        $this->indexHelper = $indexHelper;
        $this->import = $import;
        $this->storageManager = $storageManager;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;
    }

    public function create(SplFileInfo $xml, string $type): ?DocumentInterface
    {
        $xml = $this->createXML($xml, $type);

        if ($xml instanceof CollectionInterface) {
            foreach ($xml->getCollection() as $child) {
                $this->addChild($child);
            }
        }

        if (!$xml instanceof Model || !$xml->isValid() || !$this->checkRequired($xml)) {
            return null;
        }

        if ($xml instanceof ParentDocument) {
            foreach ($xml->getChildren() as $child) {
                $this->addChild($child);
            }
        }

        return $xml instanceof DocumentInterface ? $xml : null;
    }

    public function getChildren(): \Generator
    {
        foreach ($this->children as $type => $children) {
            yield [$type, $children];
        }
    }

    private function createXML(SplFileInfo $xml, string $type)
    {
        try {
            if (strpos($type, '_')) {
                $explodeType = array_map('ucfirst', \explode('_', $type));
                $explodeType[0] = strtoupper($explodeType[0]);
                $class = 'App\Import\Chamber\XML\\'.\implode('', $explodeType);
            } else {
                $class = 'App\Import\Chamber\XML\\'.\strtoupper($type);
            }

            if (in_array($class, [FLWB::class,INQO::class, GENESIS::class])) {
                return new $class($xml, $this->import, $this->storageManager, $this->extractorService, $this->fileService);
            }

            if ($class === ACTR::class) {
                return new $class($xml, $this->import, $this->indexHelper);
            }

            if (in_array($class, [PCRI::class, CCRI::class, PCRA::class, CCRA::class, QRVA::class])) {
                return new $class($xml, $this->import, $this->extractorService);
            }

            return new $class($xml, $this->import);
        } catch (\LogicException $e) {
            $this->import->getLogger()->critical($e->getMessage());
            return null;
        } catch (\Exception $e) {
            throw new \Exception(sprintf('%s : %s', $xml->getFilename(), $e->getMessage()), 0, $e);
        }
    }

    private function checkRequired(Model $model): bool
    {
        $required = $this->indexHelper->getRequired($model->getType());
        $keys = array_keys($model->getSource());

        if ($diffRequired = \array_diff($required, $keys)) {
            throw new \LogicException(sprintf("Required fields '%s'", \implode("', '", $diffRequired)));
        }

        return true;
    }

    private function addChild(DocumentInterface $child): void
    {
        if ($child instanceof Model && !$this->checkRequired($child)) {
            return;
        }

        if ($child instanceof MergeInterface && isset($this->children[$child->getType()][$child->getEmsId()])) {
            $child->merge($this->children[$child->getType()][$child->getEmsId()]);
        }

        $this->children[$child->getType()][$child->getEmsId()] = $child;
    }
}
