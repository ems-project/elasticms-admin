<?php


namespace App\Import\Chamber\XML;

use App\Import\Chamber\Import;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;

class FLWBPdf
{
    /** @var FLWB */
    private $flwb;
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

    const SEARCH_TYPE_FLWB_PDF = 'flwb_pdf';

    public function __construct(FLWB $flwb, Import $import, array $source, StorageManager $storageManager, AssetExtractorService $extractorService, FileService $fileService)
    {
        $this->flwb = $flwb;
        $this->import = $import;
        $this->source = $source;
        $this->storageManager = $storageManager;
        $this->extractorService = $extractorService;
        $this->fileService = $fileService;

        $this->filename = $source['file']['filename'];
        $this->filepath = $source['file']['path'];
        $this->filelabel = $source['file']['label'];
        $this->file = $this->import->getRootDir() . '/' . $this->filepath . $this->filename;

        $this->hash = sha1_file($this->file);
        $this->content = $this->extractorService->extractData($this->hash, $this->file);
    }

    public function getContent()
    {
        return $this->content['content'] ?? '';
    }

    public function getSearchTypes()
    {
        $searchTypes = [SearchCategories::CAT_FLWB_PDF];
        if( FLWBDoc::DOC_TYPE_AMENDMENT === ($this->source['doc_type'] ?? false)) {
            $searchTypes[] = SearchCategories::CAT_FLWB_AMENDMENT;
        }
        if( FLWBDoc::DOC_TYPE_COMMISSION_REPORT === ($this->source['doc_type'] ?? false)) {
            $searchTypes[] = SearchCategories::CAT_FLWB_COMMISSION_REPORT;
        }

        return SearchTypes::single($searchTypes, $this->flwb->getSource()['legislature']);
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function getId()
    {
        return $this->source['file']['label'];
    }

    public function getSubDocs()
    {
        return isset($this->source['sub_docs']) ? $this->source['sub_docs'] : [];
    }

    public function getFileName()
    {
        return $this->filename;
    }

    public function getFilePath()
    {
        return $this->filepath;
    }

    public function getFileLabel()
    {
        return $this->filelabel;
    }
}