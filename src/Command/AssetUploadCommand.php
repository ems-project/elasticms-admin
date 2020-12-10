<?php

namespace App\Command;

use Doctrine\ORM\EntityManager;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\FileService;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AssetUploadCommand extends Command implements CommandInterface
{
    /** @var FileService */
    private $fileService;
    /** @var AssetExtractorService */
    private $extractor;
    /** @var EntityManager */
    private $em;

    protected static $defaultName = 'ems:job:asset:upload';

    public function __construct(FileService $fileService, AssetExtractorService $extractor, RegistryInterface $doctrine)
    {
        parent::__construct();
        $this->fileService = $fileService;
        $this->extractor = $extractor;
        $this->em = $doctrine->getEntityManager();
    }

    protected function configure()
    {
        $this
            ->setDescription('Extract assets to database')
            ->addArgument('folders', InputArgument::IS_ARRAY, 'Paths to attachment files');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sfStyle = new SymfonyStyle($input, $output);
        $sfStyle->title('Upload files');

        $folders = $input->getArgument('folders');

        if (null == $folders) {
            throw new \InvalidArgumentException('missing folders');
        }

        foreach ($folders as $folder) {
            $sfStyle->section(sprintf('processing folder %s', $folder));

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folder),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            $counter = 0;
            $processBar = $sfStyle->createProgressBar();
            $processBar->start();

            foreach ($files as $name => $file) {
                /** @var $file \SplFileInfo */

                $filePath = $file->getPathname();

                if ($file->isDir()) {
                    continue;
                }

                $sha1File = sha1_file($file->getPathname());

                $this->fileService->uploadFile(basename($filePath), mime_content_type($filePath), $filePath, 'admin');

                if (!$this->fileService->head($sha1File)) {
                    $this->fileService->create($sha1File, $filePath);
                    $sfStyle->writeln(sprintf('created file %s with sha1 %s', $filePath, $sha1File));
                }

                $this->extractor->extractData($sha1File);

                $counter++;

                if ($counter%25 === 0) {
                    $processBar->advance(25);
                }

                $this->em->clear();
            }

            $processBar->advance($counter%25);
            $processBar->finish();
        }
    }
}
