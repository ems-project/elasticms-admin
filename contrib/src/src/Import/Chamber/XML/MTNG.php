<?php

namespace App\Import\Chamber\XML;

use App\Import\Chamber\CollectionInterface;
use App\Import\Chamber\Import;
use App\Import\Chamber\Model;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class MTNG implements CollectionInterface
{
    use XML;
    /** @var Model[] */
    private $collection = [];

    public function __construct(SplFileInfo $file, Import $import)
    {
        $data = $this->xmlToArray($file);

        if (isset($data['meetings'])) {
            //parse week xml
            $meetings = $data['meetings']['meeting'] ?? [];
            $nested = isset($meetings['id']) ? [$meetings] : $meetings;

            foreach ($nested as $meeting) {
                $this->addModel($meeting, $import);
            }
        } else {
            $this->addModel($data, $import);
        }
    }

    /** @return Model[] */
    public function getCollection() : array
    {
        return $this->collection;
    }

    public static function findFiles(SymfonyStyle $style, string $dir): \Generator
    {
        yield from self::find($style, $dir, '/^calendar.xml$/');
        yield from self::find($style, $dir, '/^agenda.xml$/');
    }

    private function addModel(array $data, $import)
    {
        if (!isset($data['@schemaVersion'])) {
            return;
        }

        $this->collection[] = new Agenda($data, $import);
    }

    private static function find(SymfonyStyle $style, string $dir,  string $regex): \Generator
    {
        $files = Finder::create()->in($dir)->files()->name($regex);

        $progress = $style->createProgressBar($files->count());
        $progress->start();

        foreach ($files as $file) {
            /** @var $file SplFileInfo */
            $style->write('   ' . $file->getRelativePathname());
            yield $file;
            $progress->advance();
        }

        $progress->finish();
        $style->newLine(1);
    }

}