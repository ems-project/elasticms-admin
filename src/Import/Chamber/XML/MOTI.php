<?php


namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Elasticsearch\ParentDocument;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;

class MOTI
{
    /** @var FLWB */
    private $parentDocument;
    /** @var Import */
    private $import;
    /** @var array */
    private $source;
    /** @var StorageManager */
    private $storageManager;
    /** @var AssetExtractorService */
    private $extractorService;
    /** @var FileService */
    private $fileService;

    /** @var string */
    private $file;
    private $hash;
    private $content;

    const SEARCH_TYPE_INQO_MOTI = 'inqo_moti';

    public function __construct(ParentDocument $parentDocument, array $source, Import $import, StorageManager $storageManager, AssetExtractorService $extractorService, FileService $fileService)
    {
        $this->parentDocument = $parentDocument;
        $this->import = $import;
        $this->source = $source;
        $this->storageManager = $storageManager;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;

        if (get_class($parentDocument) == 'App\Import\Chamber\XML\FLWB'){
            $this->file = $this->import->getRootDir() . '/' . $this->source['file']['path'] . $this->source['file']['filename'];
        } else {
            $this->file = $this->import->getRootDir() . '/' . $this->source['path'] . $this->source['filename'];
        }


        $this->hash = sha1_file($this->file);
        $this->content = $this->extractorService->extractData($this->hash, $this->file);
    }

    public function getContent()
    {
        return $this->content['content'] ?? '';
    }

    public function getSearchTypes()
    {
        return SearchTypes::single(SearchCategories::CAT_INQO_MOTI, $this->parentDocument->getSource()['legislature']);
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function getId()
    {
        return $this->source['label'] ?? $this->source['file']['label'] ?? null;
    }

    public function getTitleNl()
    {
        return isset($this->parentDocument->getSource()['title_nl']) ? $this->parentDocument->getSource()['title_nl'] : '';
    }

    public function getTitleFr()
    {
        return isset($this->parentDocument->getSource()['title_fr']) ? $this->parentDocument->getSource()['title_fr'] : '';
    }

    public function getSearchDateSort()
    {
        return $this->parentDocument->getSource()['date_publication'] ?? $this->parentDocument->getSource()['date_end'] ?? $this->parentDocument->getSource()['date_communication'] ?? null;
    }

    public function getDatePublication()
    {
        return $this->parentDocument->getSource()['date_publication'] ?? null;
    }

    public function getActors()
    {
        $actors = [];
        if (get_class($this->parentDocument) == 'App\Import\Chamber\XML\FLWB'){
            foreach ($this->source['authors'] as $author) {
                $actors[] = $author['actor'];
            }
            return $actors;
        }
        if (!isset($this->parentDocument->getSource()['motions'])) return '';
        foreach ($this->parentDocument->getSource()['motions'] as $motion) {
            foreach ($motion['authors'] as $author) {
                $actors[] = $author;
            }
        }
        return $actors;
    }

    public function getActorTypes()
    {
        $actors = [];

        if (get_class($this->parentDocument) == 'App\Import\Chamber\XML\FLWB'){
            foreach ($this->source['authors'] as $author) {
                $actors[] = ['actor' => $author['actor'], 'type' => 1];
            }
            return $actors;
        }

        if (!isset($this->parentDocument->getSource()['motions'])) return '';
        foreach ($this->parentDocument->getSource()['motions'] as $motion) {
            foreach ($motion['authors'] as $author) {
                $actors[] = ['actor' => $author, 'type' => 1];
            }
        }
        return $actors;
    }

    public function getDateEnd()
    {
        return $this->parentDocument->getSource()['date_end'] ?? null;
    }

    public function getDateCommunication()
    {
        return $this->parentDocument->getSource()['date_communication'] ?? null;
    }

    public function getFileName()
    {
        return $this->source['filename'] ?? $this->source['file']['filename'] ?? null;
    }

    public function getFilePath()
    {
        return $this->source['path'] ?? $this->source['file']['path'] ?? null;
    }

    public function getFileLabel()
    {
        return $this->source['label'] ?? $this->source['file']['label'] ?? null;
    }
}