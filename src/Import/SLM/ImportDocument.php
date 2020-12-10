<?php

namespace App\Import\SLM;

use EMS\CoreBundle\Service\FileService;

class ImportDocument
{
    /** @var string */
    private $id;
    /** @var string */
    private $name;
    /** @var int */
    private $year;
    private $files = [];

    public function __construct(array $document, FileService $fileService)
    {
        $source = $document['_source'];
        $this->id = $document['_id'];
        $this->name = $source['name'];
        $this->year = (int) $source['year'];

        $files = $source['files'];

        foreach ($files as $file) {
            $this->files[] = [
                'name' => $file['file']['filename'],
                'extension' => \pathinfo($file['file']['filename'], PATHINFO_EXTENSION),
                'file' => $fileService->getFile($file['file']['sha1']),
            ];
        }
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getCSVFile(): string
    {
        foreach ($this->files as $file) {
            if ($file['extension'] === 'csv') {
                return $file['file'];
            }
        }

        throw new \Exception('csv file not found!');
    }

    public function getFiles(string $extension): array
    {
        $files = $this->files;

        return array_filter($files, function (array $file) use ($extension) {
            return $file['extension'] === $extension;
        });
    }
}