<?php

namespace App\Command\Flexmail;

use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CommonBundle\Common\Document;
use EMS\CoreBundle\Command\EmsCommand;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use Exception;
use Finlet\flexmail\Config\Config;
use Finlet\flexmail\FlexmailAPI\FlexmailAPI;
use Finlet\flexmail\FlexmailAPI\Service\FlexmailAPI_Campaign;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

/**
 * Example command:
 * php bin/console ems:job:flexmail:send preview AW8jI-Gkn5IfFMhVLMln 40 nl 1806540 "Test newsletter 03-01 - Update van 27/12/2019" BIPT-Update laurens.mertens@smals.be laurens.mertens@smals.be
 */
class FlexmailSendCommand extends EmsCommand implements CommandInterface
{
    protected static $defaultName = 'ems:job:flexmail:send';

    /** @var int */
    protected $campaignId;
    /** @var Config */
    protected $config;
    /** @var string */
    protected $contentType;
    /** @var string */
    protected $description;
    /** @var RegistryInterface */
    protected $doctrine;
    /** @var string */
    protected $locale;
    /** @var int */
    protected $messageId;
    /** @var int */
    protected $listId = 0;
    /** @var string */
    protected $ouuid;
    /** @var string */
    protected $replyEmail;
    /** @var string */
    protected $senderEmail;
    /** @var string */
    protected $senderName;
    /** @var string */
    protected $title;
    /** @var Environment */
    protected $twig;

    public function __construct(LoggerInterface $logger, Client $client, RegistryInterface $doctrine, Environment $twig)
    {
        $this->doctrine = $doctrine;
        $this->twig = $twig;
        parent::__construct($logger, $client);
    }

    public function configure(): void
    {
        $this
            ->setDescription('If a newsletter ID is provided, this commands sends a flexmail newsletters')
            ->addArgument('environmentName', InputArgument::REQUIRED, 'The name of the environment')
            ->addArgument('ouuid', InputArgument::REQUIRED, 'The OUUID of the item')
            ->addArgument('templateId', InputArgument::REQUIRED, 'Id of the content type template')
            ->addArgument('locale', InputArgument::REQUIRED, 'The locale for the mail (is also passed to the template as twig variable)')
            ->addArgument('listId', InputArgument::REQUIRED, 'The listId of the receiver list')
            ->addArgument('title', InputArgument::REQUIRED, 'Title of the campaign')
            ->addArgument('campaignSenderName', InputArgument::REQUIRED, 'The sender name which will appear in the email')
            ->addArgument('campaignReplyEmailAddress', InputArgument::REQUIRED, 'The reply email address')
            ->addArgument('campaignSenderEmailAddress', InputArgument::REQUIRED, 'The from e-mail address');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $environmentName = $input->getArgument('environmentName');
        $this->ouuid = $input->getArgument('ouuid');
        $templateId = $input->getArgument('templateId');
        $this->locale = $input->getArgument('locale');
        $this->listId = $input->getArgument('listId');
        $this->senderName = $input->getArgument('campaignSenderName');
        $title = $input->getArgument('title');
        $this->replyEmail = $input->getArgument('campaignReplyEmailAddress');
        $this->senderEmail = $input->getArgument('campaignSenderEmailAddress');

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository('EMSCoreBundle:Template');
        /** @var Template $template */
        $template = $templateRepository->find($templateId);

        if (!isset($template) || $template == null) {
            $notFoundMessage = 'Template ' . $templateId . ' not found.';
            $this->logger->error($notFoundMessage);
            $output->writeln($notFoundMessage);
            return 0;
        }

        /** @var EnvironmentRepository */
        $environmentRepository = $em->getRepository('EMSCoreBundle:Environment');
        /** @var \EMS\CoreBundle\Entity\Environment $environment */
        $environment = $environmentRepository->findOneBy([
            'name' => $environmentName,
            'managed' => true,
        ]);

        if (!isset($environment) || $environment == null) {
            $notFoundMessage = 'Environments ' . $environmentName . ' not found.';
            $this->logger->error($notFoundMessage);
            $output->writeln($notFoundMessage);
            return 0;
        }


        $contentType = $template->getContentType();
        /** @var RevisionRepository $revRepo */
        $revRepo = $em->getRepository('EMSCoreBundle:Revision');

        try {
            $item = $revRepo->findByOuuidAndContentTypeAndEnvironment($contentType, $this->ouuid, $environment);
        } catch (Exception $e) {
            $item = null;
        }

        if (!isset($item) || $item == null) {
            $notFoundMessage = 'Item with OUUID ' . $this->ouuid . ' not found.';
            $this->logger->error($notFoundMessage);
            $output->writeln($notFoundMessage);
            return 0;
        }

        $this->title = $title;

        $body = $this->twig->createTemplate($template->getBody());

        $document = new Document($contentType->getName(), $this->ouuid, $item->getRawData());

        $outputHtml = $body->render([
            'environment' => $environment,
            'contentType' => $template->getContentType(),
            'object' => $document,
            'source' => $document->getSource(),
            '_download' => true,
            'locale' => $this->locale,
            'title' => $title,
        ]);

        $this->description = preg_replace('/\\\\/', '', trim(preg_replace('/\\\\r/', '', preg_replace('/\\\\n/', '', $outputHtml))));
        $this->listId = $input->getArgument('listId');

        $flexmail = $this->initFlexmail();

        $this->createMessage($flexmail);
        $output->writeln('Message ID: #' . $this->messageId);

        $this->createCampaign($flexmail);
        $output->writeln('Campaign ID: #' . $this->campaignId);

        $result = $this->sendCampaign();
        if (isset($result->errorMessage) && $result->errorMessage != '') {
            $output->writeln('Error: ' . $result->errorMessage);
        }

        return 1;
    }

    private function initFlexmail(): FlexmailAPI
    {
        $config = new Config();
        $config->set('wsdl', getenv('FLEXMAIL_WSDL'));
        $config->set('service', getenv('FLEXMAIL_SERVICE'));
        $config->set('user_id', getenv('FLEXMAIL_USER_ID'));
        $config->set('user_token', getenv('FLEXMAIL_USER_TOKEN'));
        $config->set('debug_mode', getenv('FLEXMAIL_DEBUG_MODE'));

        $this->config = $config;

        return new FlexmailAPI($config);
    }

    private function createMessage(FlexmailAPI $flexmail): void
    {
        $message = $flexmail->service('Message')->create([
            'messageType' => [
                'messageName' => $this->title,
                'messageText' => $this->description,
                'messageTextMessage' => $this->description
            ]
        ]);

        $this->messageId = $message->messageId;
    }

    private function createCampaign(FlexmailAPI $flexmail): void
    {
        $titleWithDate = date('d/m/Y H:i:s') . ' - ' . $this->title;
        $campaign = $flexmail->service('Campaign')->create([
            'campaignType' => [
                'campaignName' => mb_substr($titleWithDate, 0, 90, 'utf8'),
                'campaignSubject' => mb_substr($titleWithDate, 0, 140, 'utf8'),
                'campaignSenderName' => $this->senderName,
                'campaignMessageId' => $this->messageId,
                'campaignMailingIds' => [$this->listId],
                'campaignReplyEmailAddress' => $this->replyEmail,
                'campaignSenderEmailAddress' => $this->senderEmail
            ]
        ]);

        $this->campaignId = $campaign->campaignId;

    }

    private function sendCampaign()
    {
        $API_Campaign = new FlexmailAPI_Campaign($this->config);

        return $API_Campaign->send(['campaignId' => $this->campaignId, 'campaignSendTimestamp' => date('c', strtotime('now +2 minutes'))]);
    }
}