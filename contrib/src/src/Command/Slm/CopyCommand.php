<?php

namespace App\Command\Slm;

use App\Import\SLM\CSVImporter;
use App\Import\SLM\Document\Child;
use App\Import\SLM\ImportDocument;
use App\Import\SLM\XMLCollector;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CoreBundle\Elasticsearch\Bulker;
use EMS\CoreBundle\Elasticsearch\Indexer;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\User;

class CopyCommand extends Command implements CommandInterface
{
    /** @var SymfonyStyle */
    private $style;
    /** @var Client */
    private $client;
    /** @var DataService */
    private $dataService;
    /** @var ContentTypeService */
    private $contentTypeService;
    /** @var TokenStorageInterface */
    private $tokenStorage;

    const USERNAME = 'import'; //this user needs to have an account in the backend!

    protected static $defaultName = 'ems:job:slm-copy-publications';

    public function __construct(Client $client, DataService $dataService, ContentTypeService $contentTypeService, TokenStorageInterface $tokenStorage)
    {
        $this->client = $client;
        $this->contentTypeService = $contentTypeService;
        $this->dataService = $dataService;
        $this->tokenStorage = $tokenStorage;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('SLM copy publications command')
            ->addArgument('month_year', InputArgument::REQUIRED)
        ;
        parent::configure();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->style = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->style->title('SLM Copy Publications');

        $this->style->success($input->getArgument('month_year'));

        //@todo SLM-49
    }
}