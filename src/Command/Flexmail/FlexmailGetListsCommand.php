<?php

namespace App\Command\Flexmail;

use Elasticsearch\Client;
use EMS\CommonBundle\Command\CommandInterface;
use EMS\CoreBundle\Command\EmsCommand;
use Finlet\flexmail\Config\Config;
use Finlet\flexmail\FlexmailAPI\FlexmailAPI;
use Finlet\flexmail\FlexmailAPI\Service\FlexmailAPI_Category;
use Finlet\flexmail\FlexmailAPI\Service\FlexmailAPI_List;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Example command:
 * php bin\console ems:job:flexmail:lists
 */
class FlexmailGetListsCommand extends EmsCommand implements CommandInterface
{
    protected static $defaultName = 'ems:job:flexmail:lists';

    /** @var Config */
    protected $config;

    public function __construct(LoggerInterface $logger, Client $client)
    {
        parent::__construct($logger, $client);
    }

    public function configure(): void
    {
        $this->setDescription('Get the available lists and categories for the currently configured flexmail account');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initFlexmail();

        $API_Category = new FlexmailAPI_Category($this->config);
        $API_List = new FlexmailAPI_List($this->config);

        $categories = $API_Category->getAll()->categoryTypeItems;

        foreach ($categories as $category) {
            $categoryPhrase = "Category: " . $category->categoryName . ' (' . $category->categoryId . ')';
            $output->writeln($categoryPhrase);
            $this->logger->log(100, $categoryPhrase);
            $categoryIdArray = ['categoryId' => $category->categoryId];
            $lists = $API_List->getAll($categoryIdArray);
            foreach ($lists->mailingListTypeItems as $list) {
                $listPhrase = "- List: " . $list->mailingListName . ' (' . $list->mailingListId . ') - '
                    . $list->mailingListLanguage . ' - SubscribersCount: ' . $list->mailingListCount;
                $output->writeln($listPhrase);
                $this->logger->log(100, $listPhrase);
            }
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
}