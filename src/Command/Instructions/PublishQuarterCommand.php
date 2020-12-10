<?php

declare(strict_types=1);

namespace App\Command\Instructions;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PublishQuarterCommand extends Command
{
    /** @var string */
    protected static $defaultName = 'ems:job:instructions:publishquarter';
    /** @var LoggerInterface */
    private $logger;
    /** @var Registry */
    private $doctrine;
    /** @var EnvironmentService */
    private $environmentService;
    /** @var Client */
    private $client;
    /** @var DataService */
    private $dataService;
    /** @var SymfonyStyle */
    private $io;
    /** @var string */
    private $latestEnvironment;
    /** @var string */
    private $nextEnvironment;
    /** @var string */
    private $user;
    /** @var string */
    private $previewManageAlias;
    /** @var string */
    private $templateManageAlias;
    /** @var array */
    private $contentTypes;

    const ARGUMENT_LATEST_ENVIRONMENT = 'latest-environment';
    const ARGUMENT_NEXT_ENVIRONMENT = 'next-environment';
    const ARGUMENT_USER = 'user';
    const ARGUMENT_PREVIEW_MANAGE_ALIAS = 'preview-ma';
    const ARGUMENT_TEMPLATE_MANAGE_ALIAS = 'template-ma';
    const ARGUMENT_CONTENT_TYPES = 'content-types';

    const DEFAULT_LATEST_ENVIRONMENT = 'latest';
    const DEFAULT_NEXT_ENVIRONMENT = 'nextquarter';
    const DEFAULT_USER = 'PUBLISH_QUARTER';
    const DEFAULT_PREVIEW_MANAGE_ALIAS = 'poc_instructions_front_ma_preview';
    const DEFAULT_TEMPLATE_MANAGE_ALIAS = 'poc_instructions_front_ma_template';
    const DEFAULT_CONTENT_TYPES = ['instruction'];

    public function __construct(LoggerInterface $logger, Registry $doctrine, EnvironmentService $environmentService, Client $client, DataService $dataService)
    {
        $this->logger = $logger;
        $this->doctrine = $doctrine;
        $this->environmentService = $environmentService;
        $this->client = $client;
        $this->dataService = $dataService;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Generate the name of the next "snapshot" env, create the "snapshot" from "latest", align "latest" from "next-quarter", apply ContentRemover transformer to "next-quarter"')
            ->addArgument(
                self::ARGUMENT_LATEST_ENVIRONMENT,
                InputArgument::OPTIONAL,
                'Latest environment name',
                self::DEFAULT_LATEST_ENVIRONMENT
            )
            ->addArgument(
                self::ARGUMENT_NEXT_ENVIRONMENT,
                InputArgument::OPTIONAL,
                'Next environment name',
                self::DEFAULT_NEXT_ENVIRONMENT
            )
            ->addArgument(
                self::ARGUMENT_USER,
                InputArgument::OPTIONAL,
                'User name',
                self::DEFAULT_USER
            )
            ->addArgument(
                self::ARGUMENT_PREVIEW_MANAGE_ALIAS,
                InputArgument::OPTIONAL,
                'Preview manage alias',
                self::DEFAULT_PREVIEW_MANAGE_ALIAS
            )
            ->addArgument(
                self::ARGUMENT_TEMPLATE_MANAGE_ALIAS,
                InputArgument::OPTIONAL,
                'Template manage alias',
                self::DEFAULT_TEMPLATE_MANAGE_ALIAS
            )
            ->addArgument(
                self::ARGUMENT_CONTENT_TYPES,
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Content Types names',
                self::DEFAULT_CONTENT_TYPES
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Publish quarter');

        $this->latestEnvironment = $input->getArgument(self::ARGUMENT_LATEST_ENVIRONMENT);
        $this->nextEnvironment = $input->getArgument(self::ARGUMENT_NEXT_ENVIRONMENT);
        $this->user = $input->getArgument(self::ARGUMENT_USER);
        $this->previewManageAlias = $input->getArgument(self::ARGUMENT_PREVIEW_MANAGE_ALIAS);
        $this->templateManageAlias = $input->getArgument(self::ARGUMENT_TEMPLATE_MANAGE_ALIAS);
        $this->contentTypes = $input->getArgument(self::ARGUMENT_CONTENT_TYPES);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info('Execute the PublishQuarter command');

        $this->io->section('0.1. Check if draft(s) exist');
        $draftedRevisions = $this->dataService->getAllDrafts();
        if (\count($draftedRevisions) > 0) {
            $message = \sprintf('There are "%s" revision(s) in draft. Please resolve this before launch a publish quarter.', \count($draftedRevisions));
            $this->io->error($message);
            $this->logger->error($message);
            return -1;
        }
        $this->io->note('No drafts found');

        $this->io->section('0.2. Lock all revisions');
        $until = (new \DateTime())->modify('+20 minutes');
        $countLockedRevisions = $this->dataService->lockAllRevisions($until, $this->user);
        $this->io->note(\sprintf('"%s" revisions have been locked.', $countLockedRevisions));

        $this->io->title('1. Generate the name of the next "snapshot" environment');
        try {
            $lastSnapshotName = $this->checkAndGetLastSnapshotName();
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->logger->error($e->getMessage());
            return -1;
        }
        $nextSnapshotName = $this->generateNextSnapshotName($lastSnapshotName);

        $this->io->title(\sprintf('2. Launch the command "ems:environment:create" to create the future snapshot environment: "%s"', $nextSnapshotName));
        $createEnvironmentCommand = $this->getApplication()->find('ems:environment:create');
        $createEnvironmentCommandArguments = [
            'command' => 'ems:environment:create',
            'name' => $nextSnapshotName,
            '--strict' => true
        ];

        try {
            $createEnvironmentCommand->run(new ArrayInput($createEnvironmentCommandArguments), $output);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->logger->error($e->getMessage());
            return -1;
        }

        $this->io->title(\sprintf('3. Launch the command "ems:environment:align" to align and tag as snapshot "%s" from "%s"', $nextSnapshotName, $this->latestEnvironment));
        $alignSnapshotCommand = $this->getApplication()->find('ems:environment:align');
        $alignSnapshotCommandArguments = [
            'command' => 'ems:environment:align',
            'source' => $this->latestEnvironment,
            'target' => $nextSnapshotName,
            '--force' => true,
            //'--snapshot' => false,
            '--strict' => true
        ];

        try {
            $alignSnapshotCommand->run(new ArrayInput($alignSnapshotCommandArguments), $output);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->logger->error($e->getMessage());
            return -1;
        }

        $this->io->title(\sprintf('4. Add new snapshot index "%s" to M.A. "%s" and "%s"', $nextSnapshotName, $this->previewManageAlias, $this->templateManageAlias));
        $snapshotAlias = $this->environmentService->getByName($nextSnapshotName)->getAlias();
        if (null === $snapshotAlias) {
            $message = 'Snap! the Snapshot alias doesnt exist';
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $snapshotIndex = $this->findIndexNameByAlias($snapshotAlias);
        try {
            $this->client->indices()->updateAliases([
                'body' => [
                    'actions' => [
                        [
                            'add' => [
                                'index' => $snapshotIndex,
                                'alias' => $this->previewManageAlias,
                            ]
                        ],
                        [
                            'add' => [
                                'index' => $snapshotIndex,
                                'alias' => $this->templateManageAlias,
                            ]
                        ]
                    ]
                ]
            ]);
        } catch(\Exception $e) {
            $this->io->error($e->getMessage());
            $this->logger->error($e->getMessage());
            return -1;
        }
        $this->io->success(\sprintf('The index "%s" has been added to the M.A. "%s" and "%s"', $snapshotIndex, $this->previewManageAlias, $this->templateManageAlias));

        $this->io->title(\sprintf('5. Launch the command "ems:environment:align" to align "%s" from "%s"', $this->latestEnvironment, $this->nextEnvironment));
        $alignCommand = $this->getApplication()->find('ems:environment:align');
        $alignCommandArguments = [
            'command' => 'ems:environment:align',
            'source' => $this->nextEnvironment,
            'target' => $this->latestEnvironment,
            '--force' => true,
            '--strict' => true
        ];

        try {
            $alignCommand->run(new ArrayInput($alignCommandArguments), $output);
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->logger->error($e->getMessage());
            return -1;
        }

        $this->io->title(\sprintf('6. Loop the command "ems:contenttype:transform" to apply ContentRemover transformer to "%s" content-type(s)', implode(', ', $this->contentTypes)));
        foreach ($this->contentTypes as $contentType) {
            $transformCommand = $this->getApplication()->find('ems:contenttype:transform');
            $transformCommandArguments = [
                'contentType' => $contentType,
                'user' => $this->user,
                '--strict' => true
            ];

            try {
                $transformCommand->run(new ArrayInput($transformCommandArguments), $output);
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
                $this->logger->error($e->getMessage());
                return -1;
            }
        }

        $this->io->section('0.3. Unlock all revisions');
        $countUnlockedRevisions = $this->dataService->unlockAllRevisions($this->user);
        $this->io->note(\sprintf('"%s" revisions have been unlocked.', $countUnlockedRevisions));

        $this->io->success('Quarter is published.');
        return 0;
    }

    private function generateNextSnapshotName(string $lastSnapshotName): ?string
    {
        list($q, $year, $quarter) = \explode('-', $lastSnapshotName);

        if (\intval($quarter) === 4) {
            $year++;
            return $q . '-' . $year . '-' . 1;
        }

        $quarter++;
        return $q . '-' . $year . '-' . $quarter;
    }

    private function checkAndGetLastSnapshotName(): string
    {
        $lastSnapshot = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findBy(['snapshot' => false], ['id' => 'desc'], 1);

        if (empty($lastSnapshot)) {
            throw new \Exception('The last snapshot doesnt exists');
        }

        $lastSnapshotName = $lastSnapshot[0]->getName();
        if (false === $this->isValidSnapshotName($lastSnapshotName)) {
            throw new \Exception(\sprintf('The last snapshot name "%s" doesnt respects the following regex /^[q]-[12]\d{3}-[1234]$/', $lastSnapshotName));
        }

        return $lastSnapshotName;
    }

    private function isValidSnapshotName(string $name): bool
    {
        return (\preg_match('/^[q]-[12]\d{3}-[1234]$/', $name)) ? true : false;
    }

    private function findIndexNameByAlias(string $aliasName): ?string
    {
        $aliases = $this->client->indices()->getAliases();
        foreach ($aliases as $index => $aliasMapping) {
            if (\array_key_exists($aliasName, $aliasMapping['aliases'])) {
                return $index;
            }
        }

        return null;
    }
}
